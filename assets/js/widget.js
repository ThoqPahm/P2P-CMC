(() => {
    'use strict';

    const config = window.eAmbassadorWidget || { token: '', ambassadors: [], content: [] };
    const ambassadors = Array.isArray(config.ambassadors) ? config.ambassadors : [];
    const publishedContent = Array.isArray(config.content) ? config.content : [];
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const views = $$('.widget-view');
    const backButton = $('#widgetBack');
    let currentView = 'directoryView';
    let backTarget = null;
    let selectedAmbassador = null;
    let availability = 'all';
    let contentType = 'all';
    let conversation = null;
    let messagePoll = null;
    let offlineContact = null;
    let pendingOfflineMessage = '';
    const aiSuggestionCache = new Map();

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

    const stopMessagePolling = () => {
        if (!messagePoll) return;
        window.clearInterval(messagePoll);
        messagePoll = null;
    };

    const startMessagePolling = () => {
        stopMessagePolling();
        messagePoll = window.setInterval(loadMessages, 4000);
    };

    const setActiveNavigation = (activeButton) => {
        $$('.widget-navigation button').forEach((button) => button.classList.toggle('is-active', button === activeButton));
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
                <span class="ambassador-card-head"><span class="ambassador-avatar">${escapeHtml(item.initials)}</span><span class="ambassador-copy"><strong>${escapeHtml(item.name)} <i class="bi bi-patch-check-fill"></i></strong><span>${escapeHtml(item.major)} · Năm ${item.study_year}</span></span><span class="availability ${item.online ? 'online' : 'offline'}"><i></i>${item.online ? 'Online' : 'Offline'}</span></span>
                <span class="ambassador-bio">${escapeHtml(item.bio || 'Sẵn sàng chia sẻ trải nghiệm học tập và đời sống tại CMC.')}</span>
                <span class="ambassador-facts"><span><small>Quê quán</small><strong>${escapeHtml(item.hometown)}</strong></span><span><small>Có thể chia sẻ</small><strong>${escapeHtml((item.interests || []).slice(0, 2).join(', ') || 'Đời sống CMC')}</strong></span></span>
                <span class="ambassador-cta">Gửi tin nhắn <i class="bi bi-arrow-right"></i></span>
            </button>`).join('');
        $('#widgetEmpty').classList.toggle('is-hidden', filtered.length > 0);
        $$('.widget-ambassador').forEach((button) => button.addEventListener('click', () => openProfile(Number(button.dataset.ambassadorId))));
    };

    const openSchedule = (previous = 'profileView', contact = null) => {
        if (!selectedAmbassador) return;
        const form = $('#scheduleForm');
        $('#scheduleAmbassadorId').value = selectedAmbassador.id;
        $('#scheduleAmbassadorName').textContent = selectedAmbassador.name;
        if (contact) {
            form.elements.namedItem('name').value = contact.name || '';
            form.elements.namedItem('email').value = contact.email || '';
            form.elements.namedItem('question').value = contact.question || '';
        }
        showView('scheduleView', previous);
    };

    const setChatHeader = (online) => {
        $('#chatHeaderAvatar').textContent = selectedAmbassador.initials;
        $('#chatHeaderName').textContent = selectedAmbassador.name;
        const status = $('#chatHeaderStatus');
        status.classList.toggle('is-offline', !online);
        status.innerHTML = `<i></i> ${online ? 'Đang online' : 'Đang offline · sẽ phản hồi sau'}`;
    };

    const openChat = (previous = 'profileView') => {
        if (!selectedAmbassador) return;
        stopMessagePolling();
        if (conversation?.ambassadorId !== selectedAmbassador.id) {
            conversation = null;
            offlineContact = null;
            pendingOfflineMessage = '';
        }
        setChatHeader(selectedAmbassador.online);
        const list = $('#widgetMessages');
        delete list.dataset.loaded;
        if (conversation) {
            loadMessages();
            startMessagePolling();
        } else if (selectedAmbassador.online) {
            list.innerHTML = '<div class="widget-empty"><i class="bi bi-chat-heart"></i><h2>Bắt đầu trò chuyện</h2><p>Nhập câu hỏi bên dưới và gửi như một cuộc chat bình thường.</p></div>';
        } else {
            list.innerHTML = `<div class="offline-chat-note"><i class="bi bi-clock-history"></i><p><strong>${escapeHtml(selectedAmbassador.name)} đang offline</strong><span>Cứ nhắn như bình thường. eAmbassador sẽ báo qua email khi có phản hồi.</span></p></div>`;
        }
        showChatAiSuggestions();
        showView('chatView', previous);
        setActiveNavigation($('[data-widget-tab="chat"]'));
        $('#widgetMessageInput').focus();
    };

    const openProfile = (id) => {
        selectedAmbassador = ambassadors.find((item) => item.id === id) || null;
        if (!selectedAmbassador) return;
        const online = selectedAmbassador.online;
        $('#profileView').classList.toggle('is-online', online);
        $('#profileView').classList.toggle('is-offline', !online);
        $('#profileAvatar').textContent = selectedAmbassador.initials;
        $('#profileName').textContent = selectedAmbassador.name;
        $('#profileMajor').textContent = `${selectedAmbassador.major} · Sinh viên năm ${selectedAmbassador.study_year}`;
        $('#profileFieldMajor').textContent = selectedAmbassador.major;
        $('#profileStudyYear').textContent = `Năm ${selectedAmbassador.study_year}`;
        $('#profileLocation').textContent = selectedAmbassador.hometown;
        $('#profileAboutLead').textContent = `Mình là ${selectedAmbassador.name}, hiện là sinh viên năm ${selectedAmbassador.study_year} ngành ${selectedAmbassador.major} tại CMC University và đến từ ${selectedAmbassador.hometown}.`;
        $('#profileBio').textContent = selectedAmbassador.bio;
        $('#profileTags').innerHTML = (selectedAmbassador.interests || []).map((interest) => `<span><i class="bi bi-check2"></i>${escapeHtml(interest)}</span>`).join('');
        const shareTopics = [
            `Trải nghiệm học tập ngành ${selectedAmbassador.major}`,
            `Nhịp sống của sinh viên năm ${selectedAmbassador.study_year}`,
            ...(selectedAmbassador.interests || []).slice(0, 2).map((interest) => `Góc nhìn thực tế về ${interest.toLocaleLowerCase('vi')}`),
        ];
        $('#profileShareList').innerHTML = shareTopics.map((topic) => `<li><i class="bi bi-chat-square-heart"></i><span>${escapeHtml(topic)}</span></li>`).join('');
        $('#profileStatusLabel').textContent = online ? 'Đang online và sẵn sàng trò chuyện' : 'Hiện đang offline';
        $('#profileStatusDetail').textContent = online
            ? 'Bạn có thể gửi câu hỏi ngay để nhận chia sẻ trực tiếp.'
            : 'Bạn vẫn có thể nhắn tin. Khi có phản hồi, eAmbassador sẽ thông báo qua email.';
        $('#profileResponseBadge').textContent = online ? 'Có thể phản hồi ngay' : 'Sẽ phản hồi sau';
        $('#profilePresenceIcon').className = `bi ${online ? 'bi-chat-heart-fill' : 'bi-envelope-check-fill'}`;
        $('#profileAction').innerHTML = '<button class="primary-action" id="openChatForm" type="button"><i class="bi bi-send-fill"></i> Gửi tin nhắn</button>'
            + (selectedAmbassador.online ? '' : '<button class="secondary-action" id="openScheduleForm" type="button"><i class="bi bi-calendar2-check"></i> Đặt lịch tư vấn</button>');
        $('#openChatForm')?.addEventListener('click', () => openChat());
        $('#openScheduleForm')?.addEventListener('click', () => openSchedule());
        loadAiSuggestions();
        showView('profileView', 'directoryView');
    };

    const renderContent = () => {
        const search = $('#contentSearch').value.trim().toLocaleLowerCase('vi');
        const filtered = publishedContent.filter((item) => {
            const haystack = [item.title, item.excerpt, item.author, item.authorMajor, item.format].join(' ').toLocaleLowerCase('vi');
            return (!search || haystack.includes(search)) && (contentType === 'all' || item.type === contentType);
        });
        $('#contentGrid').innerHTML = filtered.map((item) => `
            <button class="content-card" type="button" data-content-id="${item.id}">
                <span class="content-card-cover type-${item.type}"><span>${escapeHtml(item.format)}</span><i class="bi ${item.type === 'blog' ? 'bi-journal-richtext' : 'bi-play-circle'}"></i></span>
                <span class="content-card-body"><span class="content-card-title">${escapeHtml(item.title)}</span><span class="content-card-excerpt">${escapeHtml(item.excerpt)}</span><span class="content-card-author"><span>${escapeHtml(item.authorInitials)}</span><span><strong>${escapeHtml(item.author)}</strong><small>${escapeHtml(item.authorMajor)} · ${escapeHtml(item.publishedAt)}</small></span></span><span class="content-card-meta"><span><i class="bi bi-eye"></i> ${Number(item.views).toLocaleString('vi-VN')}</span><span><i class="bi bi-heart"></i> ${Number(item.likes).toLocaleString('vi-VN')}</span><i class="bi bi-arrow-right"></i></span></span>
            </button>`).join('');
        $('#contentEmpty').classList.toggle('is-hidden', filtered.length > 0);
        $$('.content-card').forEach((button) => button.addEventListener('click', () => openContent(Number(button.dataset.contentId))));
    };

    const openContent = (id) => {
        const item = publishedContent.find((content) => content.id === id);
        if (!item) return;
        $('#detailFormat').textContent = item.format;
        $('#detailIcon').className = `bi ${item.type === 'blog' ? 'bi-journal-richtext' : 'bi-play-circle'}`;
        $('#detailTitle').textContent = item.title;
        $('#detailExcerpt').textContent = item.excerpt;
        $('#detailAuthorAvatar').textContent = item.authorInitials;
        $('#detailAuthor').textContent = item.author;
        $('#detailAuthorMeta').textContent = `${item.authorMajor} · ${item.publishedAt}`;
        const body = String(item.body || '').trim();
        $('#detailBody').innerHTML = body
            ? body.split(/\n{2,}/).map((paragraph) => `<p>${escapeHtml(paragraph).replace(/\n/g, '<br>')}</p>`).join('')
            : `<p>Nội dung này được đại sứ chia sẻ trên ${escapeHtml(item.format)}. Bạn có thể mở bài đăng gốc để xem đầy đủ.</p>`;
        const source = $('#detailSource');
        const hasExternalSource = /^https?:\/\//i.test(item.url || '');
        source.classList.toggle('is-hidden', !hasExternalSource);
        if (hasExternalSource) source.href = item.url;
        showView('contentDetailView', 'contentView');
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

    const useAiQuestion = (question, openChatFirst = false) => {
        if (openChatFirst) openChat('profileView');
        const input = $('#widgetMessageInput');
        input.value = question;
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
    };

    const renderAiQuestions = (container, suggestions, openChatFirst = false) => {
        container.innerHTML = suggestions.map((question) => `<button type="button" data-ai-question="${escapeHtml(question)}"><i class="bi bi-chat-square-text"></i><span>${escapeHtml(question)}</span></button>`).join('');
        $$('[data-ai-question]', container).forEach((button) => button.addEventListener('click', () => useAiQuestion(button.dataset.aiQuestion, openChatFirst)));
    };

    const showChatAiSuggestions = () => {
        const panel = $('#chatAiPanel');
        const suggestions = selectedAmbassador ? aiSuggestionCache.get(selectedAmbassador.id) : null;
        panel.classList.toggle('is-hidden', Boolean(conversation) || !suggestions?.length);
        if (suggestions?.length) renderAiQuestions($('#chatAiSuggestions'), suggestions);
    };

    const loadAiSuggestions = async (force = false) => {
        if (!selectedAmbassador) return;
        const ambassadorId = selectedAmbassador.id;
        const container = $('#profileAiSuggestions');
        const cached = aiSuggestionCache.get(ambassadorId);
        if (cached && !force) {
            renderAiQuestions(container, cached, true);
            showChatAiSuggestions();
            return;
        }
        container.innerHTML = '<span class="ai-loading"><i class="bi bi-arrow-repeat"></i> AI đang chuẩn bị gợi ý...</span>';
        $('#refreshAiQuestions').disabled = true;
        try {
            const result = await api('widget_ai_suggestions', { ambassador_id: ambassadorId });
            if (!selectedAmbassador || selectedAmbassador.id !== ambassadorId) return;
            aiSuggestionCache.set(ambassadorId, result.suggestions);
            renderAiQuestions(container, result.suggestions, true);
            showChatAiSuggestions();
        } catch (error) {
            container.innerHTML = `<span class="ai-error"><i class="bi bi-exclamation-circle"></i> ${escapeHtml(error.message)}</span>`;
        } finally {
            $('#refreshAiQuestions').disabled = false;
        }
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

    const showEmailPrompt = (message) => {
        pendingOfflineMessage = message;
        $('#offlineEmailStep').classList.remove('is-hidden');
        $('#offlineMessageTitle').textContent = selectedAmbassador.online ? 'Xác nhận email để gửi' : 'Nhận phản hồi qua email';
        $('#offlineEmailDescription').textContent = selectedAmbassador.online
            ? `Nhập email để duy trì cuộc trò chuyện với ${selectedAmbassador.name}.`
            : `${selectedAmbassador.name} đang offline. Nhập email để nhận thông báo khi có phản hồi.`;
        $('#offlineReplyEmailLabel').textContent = selectedAmbassador.online ? 'Email của bạn' : 'Email nhận phản hồi';
        $('#scheduleBeforeMessage').classList.toggle('is-hidden', selectedAmbassador.online);
        $('#offlineEmailActions').classList.toggle('is-single', selectedAmbassador.online);
        $('#offlineEmailError').classList.add('is-hidden');
        const dialog = $('#offlineMessageDialog');
        dialog.showModal();
        $('#offlineReplyEmail').focus();
    };

    $('#widgetMessageForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = $('#widgetMessageInput');
        const content = input.value.trim();
        if (!content) return;
        if (!conversation) {
            showEmailPrompt(content);
            return;
        }
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

    $('#offlineEmailForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const email = $('#offlineReplyEmail').value.trim();
        const button = $('button[type="submit"]', form);
        const error = $('#offlineEmailError');
        error.classList.add('is-hidden');
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-arrow-repeat"></i> Đang gửi...';
        try {
            const result = await api('widget_start_chat', {
                ambassador_id: selectedAmbassador.id,
                name: 'Khách tư vấn',
                email,
                major: selectedAmbassador.major,
                message: pendingOfflineMessage,
            });
            const ambassadorOnline = Boolean(result.ambassador_online);
            conversation = { id: result.conversation_id, token: result.conversation_token, ambassadorOnline, ambassadorId: selectedAmbassador.id };
            $('#chatAiPanel').classList.add('is-hidden');
            setChatHeader(ambassadorOnline);
            offlineContact = { name: '', email, question: pendingOfflineMessage };
            $('#widgetMessageInput').value = '';
            await loadMessages();
            startMessagePolling();
            $('#offlineMessageDialog').close();
            $('#widgetMessageInput').focus();
        } catch (caught) {
            error.textContent = caught.message;
            error.classList.remove('is-hidden');
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-send-fill"></i> Gửi tin nhắn';
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
    $('#refreshAiQuestions').addEventListener('click', () => loadAiSuggestions(true));
    $('#widgetAiRewrite').addEventListener('click', async (event) => {
        if (!selectedAmbassador) return;
        const input = $('#widgetMessageInput');
        const draft = input.value.trim();
        if (!draft) {
            showToast('Hãy nhập câu hỏi nháp trước.');
            input.focus();
            return;
        }
        const button = event.currentTarget;
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-arrow-repeat"></i> AI đang chỉnh...';
        try {
            const result = await api('widget_ai_rewrite', { ambassador_id: selectedAmbassador.id, draft });
            input.value = result.question;
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
            showToast('AI đã làm rõ câu hỏi. Bạn hãy kiểm tra trước khi gửi.');
        } catch (error) {
            showToast(error.message);
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-magic"></i> AI làm rõ câu hỏi';
        }
    });
    $('[data-widget-tab="chat"]').addEventListener('click', (event) => {
        setActiveNavigation(event.currentTarget);
        if (selectedAmbassador) {
            openChat(currentView === 'profileView' ? 'profileView' : 'directoryView');
            return;
        }
        availability = 'all';
        renderAmbassadors();
        showView('directoryView');
        showToast('Chọn một đại sứ để bắt đầu nhắn tin.');
    });
    $$('[data-availability]').forEach((button) => button.addEventListener('click', () => {
        availability = button.dataset.availability;
        setActiveNavigation(button);
        renderAmbassadors();
        if (currentView === 'chatView') stopMessagePolling();
        if (currentView !== 'directoryView') showView('directoryView');
    }));
    $('[data-widget-tab="content"]').addEventListener('click', (event) => {
        setActiveNavigation(event.currentTarget);
        if (currentView === 'chatView') stopMessagePolling();
        renderContent();
        showView('contentView');
    });
    $('#clearFilters').addEventListener('click', () => {
        $('#widgetSearch').value = '';
        $('#majorFilter').value = '';
        $('#hometownFilter').value = '';
        $('#yearFilter').value = '';
        availability = 'all';
        const allButton = $('[data-availability="all"]');
        setActiveNavigation(allButton);
        renderAmbassadors();
    });
    $('#contentSearch').addEventListener('input', renderContent);
    $$('[data-content-type]').forEach((button) => button.addEventListener('click', () => {
        contentType = button.dataset.contentType;
        $$('[data-content-type]').forEach((item) => item.classList.toggle('is-active', item === button));
        renderContent();
    }));
    $('#clearContentFilters').addEventListener('click', () => {
        $('#contentSearch').value = '';
        contentType = 'all';
        $$('[data-content-type]').forEach((item) => item.classList.toggle('is-active', item.dataset.contentType === 'all'));
        renderContent();
    });
    backButton.addEventListener('click', () => {
        const target = backTarget || 'directoryView';
        if (currentView === 'chatView') stopMessagePolling();
        showView(target, backTarget === 'profileView' ? 'directoryView' : null);
        if (target === 'chatView' && conversation) {
            loadMessages();
            startMessagePolling();
        }
    });
    $('#backToDirectory').addEventListener('click', () => {
        setActiveNavigation($('[data-availability="all"]'));
        showView('directoryView');
    });
    $('#offlineDialogClose').addEventListener('click', () => $('#offlineMessageDialog').close());
    $('#scheduleBeforeMessage').addEventListener('click', () => {
        offlineContact = { name: '', email: $('#offlineReplyEmail').value.trim(), question: pendingOfflineMessage };
        $('#offlineMessageDialog').close();
        openSchedule('chatView', offlineContact);
    });
    $('#widgetClose').addEventListener('click', () => window.parent.postMessage({ type: 'eambassador:close' }, '*'));

    const minimumDate = new Date(Date.now() + (60 * 60 * 1000));
    minimumDate.setMinutes(minimumDate.getMinutes() - minimumDate.getTimezoneOffset());
    $('#preferredAt').min = minimumDate.toISOString().slice(0, 16);
    renderAmbassadors();
    renderContent();
    const contentMatch = window.location.hash.match(/^#content-(\d+)$/);
    if (contentMatch) {
        const contentButton = $('[data-widget-tab="content"]');
        setActiveNavigation(contentButton);
        openContent(Number(contentMatch[1]));
    }
})();
