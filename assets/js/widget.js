(() => {
    'use strict';

    const config = window.eAmbassadorWidget || { token: '', ambassadors: [] };
    const ambassadors = Array.isArray(config.ambassadors) ? config.ambassadors : [];
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const views = $$('.widget-view');
    const backButton = $('#widgetBack');
    let currentView = 'directoryView';
    let backTarget = null;
    let selectedAmbassador = null;
    let availability = 'all';
    let conversation = null;
    let messagePoll = null;

    const escapeHtml = (value) => {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    };

    const showView = (id, previous = null) => {
        views.forEach((view) => view.classList.toggle('is-hidden', view.id !== id));
        currentView = id;
        backTarget = previous;
        backButton.classList.toggle('is-hidden', !previous);
        window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    };

    const showToast = (message) => {
        const toast = $('#widgetToast');
        toast.textContent = message;
        toast.classList.remove('is-hidden');
        window.setTimeout(() => toast.classList.add('is-hidden'), 2600);
    };

    const unique = (key) => [...new Set(ambassadors.map((item) => item[key]).filter(Boolean))].sort((a, b) => String(a).localeCompare(String(b), 'vi'));
    const fillSelect = (selector, values, formatter = (value) => value) => {
        const select = $(selector);
        values.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = formatter(value);
            select.appendChild(option);
        });
    };

    fillSelect('#majorFilter', unique('major'));
    fillSelect('#hometownFilter', unique('hometown'));
    fillSelect('#yearFilter', unique('study_year').sort((a, b) => a - b), (year) => `Năm ${year}`);

    const renderAmbassadors = () => {
        const search = $('#widgetSearch').value.trim().toLocaleLowerCase('vi');
        const major = $('#majorFilter').value;
        const hometown = $('#hometownFilter').value;
        const year = $('#yearFilter').value;
        const filtered = ambassadors.filter((item) => {
            const haystack = [item.name, item.major, item.hometown, item.bio, ...(item.interests || [])].join(' ').toLocaleLowerCase('vi');
            return (!search || haystack.includes(search))
                && (!major || item.major === major)
                && (!hometown || item.hometown === hometown)
                && (!year || String(item.study_year) === year)
                && (availability === 'all' || (availability === 'online' ? item.online : !item.online));
        });
        $('#resultCount').textContent = `${filtered.length} đại sứ phù hợp`;
        $('#ambassadorList').innerHTML = filtered.map((item) => `
            <button class="widget-ambassador" type="button" data-ambassador-id="${item.id}">
                <span class="ambassador-avatar">${escapeHtml(item.initials)}</span>
                <span class="ambassador-copy"><strong>${escapeHtml(item.name)} <i class="bi bi-patch-check-fill"></i></strong><span>${escapeHtml(item.major)} · Năm ${item.study_year}</span><span class="ambassador-meta"><span><i class="bi bi-geo-alt"></i> ${escapeHtml(item.hometown)}</span><span><i class="bi bi-stars"></i> ${escapeHtml((item.interests || [])[0] || 'Đời sống CMC')}</span></span></span>
                <span class="ambassador-availability"><span class="availability ${item.online ? 'online' : 'offline'}"><i></i>${item.online ? 'Online' : 'Offline'}</span><i class="bi bi-chevron-right"></i></span>
            </button>`).join('');
        $('#widgetEmpty').classList.toggle('is-hidden', filtered.length > 0);
        $$('.widget-ambassador').forEach((button) => button.addEventListener('click', () => openProfile(Number(button.dataset.ambassadorId))));
    };

    const openProfile = (id) => {
        selectedAmbassador = ambassadors.find((item) => item.id === id) || null;
        if (!selectedAmbassador) return;
        $('#profileAvatar').textContent = selectedAmbassador.initials;
        $('#profileName').textContent = selectedAmbassador.name;
        $('#profileMajor').innerHTML = `<i class="bi bi-book"></i> ${escapeHtml(selectedAmbassador.major)} · Năm ${selectedAmbassador.study_year}`;
        $('#profileLocation').innerHTML = `<i class="bi bi-geo-alt"></i> ${escapeHtml(selectedAmbassador.hometown)}`;
        $('#profileBio').textContent = selectedAmbassador.bio;
        $('#profileTags').innerHTML = (selectedAmbassador.interests || []).slice(0, 4).map((interest) => `<span>${escapeHtml(interest)}</span>`).join('');
        $('#profileStatus').innerHTML = `<i></i> ${selectedAmbassador.online ? 'Đang online' : 'Hiện đang offline'}`;
        $('#profileAction').innerHTML = selectedAmbassador.online
            ? '<button class="primary-action" id="openChatForm" type="button"><i class="bi bi-chat-dots-fill"></i> Chat ngay với đại sứ</button>'
            : '<button class="secondary-action" id="openScheduleForm" type="button"><i class="bi bi-calendar2-check"></i> Đặt lịch tư vấn</button>';
        $('#openChatForm')?.addEventListener('click', () => {
            $('#chatAmbassadorId').value = selectedAmbassador.id;
            $('#chatMajor').value = selectedAmbassador.major;
            showView('chatStartView', 'profileView');
        });
        $('#openScheduleForm')?.addEventListener('click', () => {
            $('#scheduleAmbassadorId').value = selectedAmbassador.id;
            $('#scheduleAmbassadorName').textContent = selectedAmbassador.name;
            showView('scheduleView', 'profileView');
        });
        showView('profileView', 'directoryView');
    };

    const api = async (action, data = {}, method = 'POST') => {
        const payload = { widget_token: config.token, ...data };
        const url = method === 'GET'
            ? `api.php?action=${encodeURIComponent(action)}&${new URLSearchParams(payload)}`
            : `api.php?action=${encodeURIComponent(action)}`;
        const response = await fetch(url, {
            method,
            headers: method === 'POST' ? { 'Content-Type': 'application/json' } : {},
            body: method === 'POST' ? JSON.stringify(payload) : undefined,
        });
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.message || 'Không thể thực hiện yêu cầu.');
        return result;
    };

    const loadMessages = async () => {
        if (!conversation) return;
        try {
            const result = await api('widget_messages', {
                conversation_id: conversation.id,
                conversation_token: conversation.token,
            }, 'GET');
            const list = $('#widgetMessages');
            const nearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 90;
            list.innerHTML = result.messages.map((message) => {
                const mine = Number(message.sender_id) === Number(result.current_user_id);
                const time = new Date(message.created_at.replace(' ', 'T')).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                return `<div class="widget-message ${mine ? 'mine' : ''}"><p>${escapeHtml(message.content)}</p><small>${mine ? 'Bạn' : escapeHtml(message.sender_name)} · ${time}</small></div>`;
            }).join('') || '<div class="widget-empty"><i class="bi bi-chat-heart"></i><h2>Hãy gửi lời chào đầu tiên</h2></div>';
            if (nearBottom || !list.dataset.loaded) list.scrollTop = list.scrollHeight;
            list.dataset.loaded = 'true';
        } catch (error) {
            showToast(error.message);
        }
    };

    $('#widgetChatForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = $('button[type="submit"]', form);
        const error = $('#chatError');
        error.classList.add('is-hidden');
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-arrow-repeat"></i> Đang kết nối...';
        try {
            const result = await api('widget_start_chat', Object.fromEntries(new FormData(form).entries()));
            conversation = { id: result.conversation_id, token: result.conversation_token };
            $('#chatHeaderAvatar').textContent = selectedAmbassador.initials;
            $('#chatHeaderName').textContent = selectedAmbassador.name;
            showView('chatView', 'profileView');
            await loadMessages();
            messagePoll = window.setInterval(loadMessages, 4000);
            $('#widgetMessageInput').focus();
        } catch (caught) {
            error.textContent = caught.message;
            error.classList.remove('is-hidden');
        } finally {
            button.disabled = false;
            button.innerHTML = 'Kết nối với đại sứ <i class="bi bi-arrow-right"></i>';
        }
    });

    $('#widgetMessageForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = $('#widgetMessageInput');
        const content = input.value.trim();
        if (!content || !conversation) return;
        input.disabled = true;
        try {
            await api('widget_send_message', { conversation_id: conversation.id, conversation_token: conversation.token, content });
            input.value = '';
            await loadMessages();
        } catch (error) {
            showToast(error.message);
        } finally {
            input.disabled = false;
            input.focus();
        }
    });

    $('#scheduleForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = $('button[type="submit"]', form);
        const error = $('#scheduleError');
        error.classList.add('is-hidden');
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-arrow-repeat"></i> Đang ghi nhận...';
        try {
            await api('widget_schedule', Object.fromEntries(new FormData(form).entries()));
            form.reset();
            showView('successView', null);
        } catch (caught) {
            error.textContent = caught.message;
            error.classList.remove('is-hidden');
        } finally {
            button.disabled = false;
            button.innerHTML = 'Gửi yêu cầu đặt lịch <i class="bi bi-calendar2-check"></i>';
        }
    });

    ['#widgetSearch', '#majorFilter', '#hometownFilter', '#yearFilter'].forEach((selector) => $(selector).addEventListener('input', renderAmbassadors));
    $$('[data-availability]').forEach((button) => button.addEventListener('click', () => {
        availability = button.dataset.availability;
        $$('[data-availability]').forEach((item) => item.classList.toggle('is-active', item === button));
        renderAmbassadors();
    }));
    $('#clearFilters').addEventListener('click', () => {
        $('#widgetSearch').value = '';
        $('#majorFilter').value = '';
        $('#hometownFilter').value = '';
        $('#yearFilter').value = '';
        availability = 'all';
        $$('[data-availability]').forEach((item) => item.classList.toggle('is-active', item.dataset.availability === 'all'));
        renderAmbassadors();
    });
    backButton.addEventListener('click', () => {
        if (currentView === 'chatView' && messagePoll) window.clearInterval(messagePoll);
        showView(backTarget || 'directoryView', backTarget === 'profileView' ? 'directoryView' : null);
    });
    $('#backToDirectory').addEventListener('click', () => showView('directoryView'));
    $('#widgetClose').addEventListener('click', () => window.parent.postMessage({ type: 'eambassador:close' }, '*'));

    const minimumDate = new Date(Date.now() + (60 * 60 * 1000));
    minimumDate.setMinutes(minimumDate.getMinutes() - minimumDate.getTimezoneOffset());
    $('#preferredAt').min = minimumDate.toISOString().slice(0, 16);
    renderAmbassadors();
})();
