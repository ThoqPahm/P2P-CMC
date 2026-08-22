(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const csrf = $('meta[name="csrf-token"]')?.content || '';

    const sidebar = $('#appSidebar');
    const overlay = $('#sidebarOverlay');
    const closeSidebar = () => {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('open');
    };
    $('#sidebarToggle')?.addEventListener('click', () => {
        sidebar?.classList.add('open');
        overlay?.classList.add('open');
    });
    $('#sidebarClose')?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    $$('.toast').forEach((element) => {
        if (window.bootstrap) {
            const toast = bootstrap.Toast.getOrCreateInstance(element, { delay: 3500 });
            toast.show();
        }
    });

    $$('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.innerHTML = `<i class="bi ${show ? 'bi-eye-slash' : 'bi-eye'}"></i> ${show ? 'Ẩn' : 'Hiện'}`;
            button.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        });
    });

    $$('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.copy);
                const original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check-lg"></i> Đã chép';
                setTimeout(() => { button.innerHTML = original; }, 1800);
            } catch (_) {
                const input = button.parentElement.querySelector('input');
                input?.select();
                document.execCommand('copy');
            }
        });
    });

    const chatPanel = $('#chatPanel');
    const chatOverlay = $('#chatOverlay');
    const chatWelcome = $('#chatWelcome');
    const chatRoom = $('#chatRoom');
    let messagePoll = null;

    const closeChat = () => {
        chatPanel?.classList.remove('open');
        chatOverlay?.classList.remove('open');
        chatPanel?.setAttribute('aria-hidden', 'true');
        if (messagePoll) clearInterval(messagePoll);
    };

    const openChat = (button) => {
        if (!chatPanel) return;
        $('#chatAmbassadorId').value = button.dataset.ambassadorId;
        $('#chatName').textContent = button.dataset.ambassadorName;
        $('#chatMajor').textContent = `${button.dataset.ambassadorMajor} · Đang trực tuyến`;
        $('#chatAvatar').textContent = button.dataset.ambassadorInitials;
        chatWelcome?.classList.remove('d-none');
        chatRoom?.classList.add('d-none');
        chatPanel.classList.add('open');
        chatOverlay?.classList.add('open');
        chatPanel.setAttribute('aria-hidden', 'false');
    };

    $$('.chat-trigger').forEach((button) => button.addEventListener('click', () => openChat(button)));
    $('#chatClose')?.addEventListener('click', closeChat);
    chatOverlay?.addEventListener('click', closeChat);
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeChat(); });

    if (window.autoOpenAmbassador) {
        const button = $(`.chat-trigger[data-ambassador-id="${window.autoOpenAmbassador}"]`);
        if (button) setTimeout(() => openChat(button), 250);
    }

    async function api(action, options = {}) {
        const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
                ...(options.headers || {})
            }
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể thực hiện yêu cầu.');
        return data;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    }

    $('#copilotForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const result = $('#copilotResult');
        const button = $('button[type="submit"]', form);
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang xây hướng kể...';
        result.classList.remove('empty');
        result.innerHTML = '<div class="copilot-loading"><span></span><span></span><span></span><p>Đang đọc brief và kiểm tra brand voice...</p></div>';
        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            const data = await api('copilot_generate', { method: 'POST', body: JSON.stringify(payload) });
            const output = data.result;
            const directions = output.directions.map((direction, index) => `
                <article class="direction-card">
                    <div class="direction-index">0${index + 1}</div>
                    <div class="direction-content">
                        <span>${escapeHtml(direction.format)}</span>
                        <h3>${escapeHtml(direction.title)}</h3>
                        <blockquote>${escapeHtml(direction.hook)}</blockquote>
                        <ol>${direction.beats.map((beat) => `<li>${escapeHtml(beat)}</li>`).join('')}</ol>
                        <p class="direction-cta"><strong>CTA</strong> ${escapeHtml(direction.cta)}</p>
                    </div>
                </article>`).join('');
            result.innerHTML = `
                <div class="copilot-result-head">
                    <div><p class="topbar-context">3 hướng gợi ý</p><h2>${escapeHtml(output.campaign)}</h2></div>
                    <div class="brand-score"><span>Brand score</span><strong>${Number(output.brand_score)}/100</strong></div>
                </div>
                <div class="direction-list">${directions}</div>
                <div class="copilot-delivery">
                    <div><span><i class="bi bi-hash"></i> Hashtag</span><p>${output.hashtags.map(escapeHtml).join(' ')}</p></div>
                    <div><span><i class="bi bi-clock"></i> Thời điểm thử nghiệm</span><p>${escapeHtml(output.schedule)}</p></div>
                    <div><span><i class="bi bi-shield-check"></i> Kiểm tra an toàn</span><ul>${output.warnings.map((warning) => `<li>${escapeHtml(warning)}</li>`).join('')}</ul></div>
                </div>`;
            result.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        } catch (error) {
            result.classList.add('empty');
            result.innerHTML = `<i class="bi bi-exclamation-circle"></i><h3>Chưa thể tạo gợi ý</h3><p>${escapeHtml(error.message)}</p>`;
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });

    async function loadMessages(conversationId) {
        const list = $('#messageList');
        if (!list || !conversationId) return;
        try {
            const response = await fetch(`api.php?action=messages&conversation_id=${conversationId}`);
            const data = await response.json();
            if (!data.ok) return;
            const nearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 80;
            list.innerHTML = data.messages.map((message) => {
                const mine = Number(message.sender_id) === Number(data.current_user_id);
                const time = new Date(message.created_at.replace(' ', 'T')).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                return `<div class="message ${mine ? 'mine' : ''}"><b>${escapeHtml(message.sender_name)}</b><p>${escapeHtml(message.content)}</p><time>${time}</time></div>`;
            }).join('') || '<div class="empty-state compact">Hãy gửi lời chào đầu tiên nhé.</div>';
            if (nearBottom || !list.dataset.loaded) list.scrollTop = list.scrollHeight;
            list.dataset.loaded = 'true';
        } catch (_) {
            // Polling can silently retry on the next interval.
        }
    }

    $('#startChatForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = $('button[type="submit"]', form);
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang kết nối...';
        try {
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());
            const data = await api('start_chat', { method: 'POST', body: JSON.stringify(payload) });
            $('#conversationId').value = data.conversation_id;
            chatWelcome.classList.add('d-none');
            chatRoom.classList.remove('d-none');
            await loadMessages(data.conversation_id);
            messagePoll = setInterval(() => loadMessages(data.conversation_id), 4000);
            $('#messageInput')?.focus();
        } catch (error) {
            window.alert(error.message);
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });

    $('#messageForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const conversationId = Number($('#conversationId')?.value || 0);
        const input = $('#messageInput');
        const content = input?.value.trim();
        if (!conversationId || !content) return;
        input.disabled = true;
        try {
            await api('send_message', { method: 'POST', body: JSON.stringify({ conversation_id: conversationId, content }) });
            input.value = '';
            await loadMessages(conversationId);
        } catch (error) {
            window.alert(error.message);
        } finally {
            input.disabled = false;
            input.focus();
        }
    });

    const inboxConversation = Number($('.inbox-shell')?.dataset.inboxConversation || 0);
    if (inboxConversation) {
        loadMessages(inboxConversation);
        messagePoll = setInterval(() => loadMessages(inboxConversation), 4000);
    }

    $$('textarea').forEach((textarea) => {
        textarea.addEventListener('input', () => {
            if (textarea.closest('.chat-composer')) {
                textarea.style.height = 'auto';
                textarea.style.height = `${Math.min(textarea.scrollHeight, 110)}px`;
            }
        });
        textarea.addEventListener('keydown', (event) => {
            if (textarea.closest('.chat-composer') && event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                textarea.closest('form')?.requestSubmit();
            }
        });
    });
})();
