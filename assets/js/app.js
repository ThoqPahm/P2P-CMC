(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const csrf = $('meta[name="csrf-token"]')?.content || '';

    const sidebar = $('#appSidebar');
    const overlay = $('#sidebarOverlay');
    const sidebarToggle = $('#sidebarToggle');
    const compactSidebar = window.matchMedia('(max-width: 1199.98px)');
    const sidebarFocusable = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const syncSidebarState = () => {
        const isOpen = sidebar?.classList.contains('open') ?? false;
        sidebarToggle?.setAttribute('aria-expanded', String(isOpen));
        if (compactSidebar.matches) sidebar?.setAttribute('aria-hidden', String(!isOpen));
        else sidebar?.removeAttribute('aria-hidden');
    };
    const closeSidebar = (restoreFocus = false) => {
        if (restoreFocus) sidebarToggle?.focus();
        sidebar?.classList.remove('open');
        overlay?.classList.remove('open');
        syncSidebarState();
    };
    sidebarToggle?.addEventListener('click', () => {
        sidebar?.classList.add('open');
        overlay?.classList.add('open');
        syncSidebarState();
        requestAnimationFrame(() => $('#sidebarClose')?.focus());
    });
    $('#sidebarClose')?.addEventListener('click', () => closeSidebar(true));
    overlay?.addEventListener('click', () => closeSidebar(true));
    compactSidebar.addEventListener('change', () => {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('open');
        syncSidebarState();
    });
    syncSidebarState();

    $$('.toast').forEach((element) => {
        if (window.bootstrap) {
            const toast = bootstrap.Toast.getOrCreateInstance(element, { delay: 3500 });
            toast.show();
        }
    });

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;


    $$('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            const label = show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu';
            const showLabel = button.dataset.toggleLabel === 'visible';
            const labelClass = showLabel ? '' : ' class="visually-hidden"';
            const labelText = showLabel ? (show ? 'Ẩn' : 'Hiện') : label;
            button.innerHTML = `<i class="bi ${show ? 'bi-eye-slash' : 'bi-eye'}" aria-hidden="true"></i><span${labelClass}>${labelText}</span>`;
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
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
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar(sidebar?.classList.contains('open') ?? false);
            closeChat();
            return;
        }
        if (event.key !== 'Tab' || !compactSidebar.matches || !sidebar?.classList.contains('open')) return;
        const focusable = $$(sidebarFocusable, sidebar).filter((element) => element.getClientRects().length > 0);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    if (window.autoOpenAmbassador) {
        const button = $(`.chat-trigger[data-ambassador-id="${window.autoOpenAmbassador}"]`);
        if (button) setTimeout(() => openChat(button), 250);
    }

    async function api(action, options = {}) {
        let response;
        try {
            response = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
                ...options,
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf, ...(options.headers || {})}
            });
        } catch (error) {
            throw new Error('Không kết nối được với máy chủ. Nếu AI đang xử lý lâu, hãy chờ một chút rồi thử lại.');
        }
        let data;
        try { data = await response.json(); }
        catch (error) { throw new Error('Máy chủ trả về phản hồi chưa hoàn chỉnh. Hãy thử lại.'); }
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
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang tạo đề xuất...';
        result.classList.remove('empty');
        result.innerHTML = '<div class="copilot-loading"><span></span><span></span><span></span><p>Đang phân tích yêu cầu và tạo đề xuất...</p></div>';
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
                        <p class="direction-cta"><strong>Lời kêu gọi hành động</strong> ${escapeHtml(direction.cta)}</p>
                    </div>
                </article>`).join('');
            result.innerHTML = `
                <div class="copilot-result-head">
                    <div><p class="topbar-context">3 hướng gợi ý</p><h2>${escapeHtml(output.campaign)}</h2></div>
                    <div class="brand-score"><span>AI hỗ trợ</span><strong>Bản nháp</strong></div>
                </div>
                ${output.clarification ? `<p class="helper-note">${escapeHtml(output.clarification)}</p>` : ''}
                <div class="direction-list">${directions}</div>
                <div class="copilot-delivery">
                    <div><span><i class="bi bi-hash"></i> Hashtag</span><p>${output.hashtags.map(escapeHtml).join(' ')}</p></div>
                    <div><span><i class="bi bi-clock"></i> Thời điểm thử nghiệm</span><p>${escapeHtml(output.schedule)}</p></div>
                    <div><span><i class="bi bi-shield-check"></i> Kiểm tra an toàn</span><ul>${output.warnings.map((warning) => `<li>${escapeHtml(warning)}</li>`).join('')}</ul>${(output.sources || []).map(s => `<p>Nguồn: ${escapeHtml(s.title)} · ${escapeHtml(s.source_reference || '')}</p>`).join('')}</div>
                </div>`;
            result.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
        } catch (error) {
            result.classList.add('empty');
            result.innerHTML = `<i class="bi bi-exclamation-circle"></i><h3>Chưa thể tạo gợi ý</h3><p>${escapeHtml(error.message)}</p>`;
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });

    const messageAiState = {
        activeMessageId: 0,
        status: 'idle',
        result: null,
        error: '',
        requestId: 0,
        typingId: 0
    };

    const wait = (milliseconds) => new Promise(resolve => setTimeout(resolve, milliseconds));

    async function typeAiGuidance(callout, messageId) {
        const typingId = ++messageAiState.typingId;
        const slots = $$('[data-ai-typing]', callout);
        for (const slot of slots) {
            const text = slot.dataset.aiTyping || '';
            if (reduceMotion) {
                slot.textContent = text;
                continue;
            }
            slot.textContent = '';
            slot.classList.add('is-typing');
            for (let index = 0; index < text.length; index += 2) {
                if (typingId !== messageAiState.typingId || messageAiState.activeMessageId !== messageId || !slot.isConnected) return;
                slot.textContent = text.slice(0, index + 2);
                await wait(8);
            }
            slot.textContent = text;
            slot.classList.remove('is-typing');
        }
    }

    function mountMessageAiCallout(animate = false) {
        if (!messageAiState.activeMessageId) return;
        const message = $(`.message[data-message-id="${messageAiState.activeMessageId}"]`);
        const host = $('[data-ai-callout-host]', message);
        if (!host) return;

        const callout = document.createElement('aside');
        callout.className = `message-ai-callout${animate ? ' is-opening' : ''}`;
        callout.setAttribute('aria-live', 'polite');
        callout.setAttribute('aria-label', 'AI hỗ trợ hướng phản hồi');
        const header = document.createElement('header');
        header.innerHTML = '<span><i class="bi bi-magic" aria-hidden="true"></i> Hướng hỗ trợ</span><button type="button" data-ai-callout-close aria-label="Đóng gợi ý AI"><i class="bi bi-x-lg" aria-hidden="true"></i></button>';
        callout.append(header);

        if (messageAiState.status === 'loading') {
            const loading = document.createElement('div');
            loading.className = 'message-ai-thinking';
            loading.innerHTML = '<span></span><span></span><span></span><p>Đang đọc mạch hội thoại và xem kỹ tin nhắn này...</p>';
            callout.append(loading);
        } else if (messageAiState.status === 'error') {
            const error = document.createElement('div');
            error.className = 'message-ai-error';
            const copy = document.createElement('p');
            copy.textContent = messageAiState.error;
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.dataset.askAi = String(messageAiState.activeMessageId);
            retry.textContent = 'Thử lại';
            error.append(copy, retry);
            callout.append(error);
        } else if (messageAiState.result) {
            const result = messageAiState.result;
            const focus = document.createElement('p');
            focus.className = 'message-ai-focus';
            focus.dataset.aiTyping = result.focus || '';
            callout.append(focus);

            const list = document.createElement('ol');
            list.className = 'message-ai-directions';
            (result.directions || []).forEach(direction => {
                const item = document.createElement('li');
                const copy = document.createElement('span');
                copy.dataset.aiTyping = direction;
                item.append(copy);
                list.append(item);
            });
            callout.append(list);

            if (result.clarifying_question) {
                const clarification = document.createElement('div');
                clarification.className = 'message-ai-note';
                clarification.innerHTML = '<i class="bi bi-chat-dots" aria-hidden="true"></i><div><strong>Cần hỏi rõ thêm</strong><p data-ai-typing></p></div>';
                $('[data-ai-typing]', clarification).dataset.aiTyping = result.clarifying_question;
                callout.append(clarification);
            }
            if (result.caution) {
                const caution = document.createElement('div');
                caution.className = 'message-ai-note is-caution';
                caution.innerHTML = '<i class="bi bi-shield-check" aria-hidden="true"></i><div><strong>Lưu ý</strong><p data-ai-typing></p></div>';
                $('[data-ai-typing]', caution).dataset.aiTyping = result.caution;
                callout.append(caution);
            }

            const footer = document.createElement('footer');
            footer.textContent = 'AI gợi ý cách tiếp cận. Đại sứ vẫn là người viết và gửi phản hồi.';
            callout.append(footer);
        }

        host.replaceChildren(callout);
        const trigger = $('[data-ask-ai]', message);
        trigger?.setAttribute('aria-expanded', 'true');
        if (messageAiState.status === 'ready' && messageAiState.result) {
            typeAiGuidance(callout, messageAiState.activeMessageId);
        }
    }

    function closeMessageAiCallout() {
        const activeId = messageAiState.activeMessageId;
        if (!activeId) return;
        const message = $(`.message[data-message-id="${activeId}"]`);
        const callout = $('.message-ai-callout', message);
        messageAiState.typingId += 1;
        $('[data-ask-ai]', message)?.setAttribute('aria-expanded', 'false');
        if (callout && !reduceMotion) {
            callout.classList.add('is-closing');
            setTimeout(() => callout.remove(), 180);
        } else {
            callout?.remove();
        }
        messageAiState.activeMessageId = 0;
        messageAiState.status = 'idle';
        messageAiState.result = null;
        messageAiState.error = '';
    }

    async function requestMessageAiGuidance(messageId) {
        const conversationId = Number($('#conversationId')?.value || $('.inbox-shell')?.dataset.inboxConversation || 0);
        if (!conversationId || !messageId) return;
        if (messageAiState.activeMessageId && messageAiState.activeMessageId !== messageId) closeMessageAiCallout();
        messageAiState.activeMessageId = messageId;
        messageAiState.status = 'loading';
        messageAiState.result = null;
        messageAiState.error = '';
        const requestId = ++messageAiState.requestId;
        mountMessageAiCallout(true);
        try {
            const data = await api('ambassador_message_ai_guidance', {
                method: 'POST',
                body: JSON.stringify({ conversation_id: conversationId, message_id: messageId })
            });
            if (requestId !== messageAiState.requestId || messageAiState.activeMessageId !== messageId) return;
            messageAiState.status = 'ready';
            messageAiState.result = data.result;
            mountMessageAiCallout();
        } catch (error) {
            if (requestId !== messageAiState.requestId || messageAiState.activeMessageId !== messageId) return;
            messageAiState.status = 'error';
            messageAiState.error = error.message || 'Chưa tạo được hướng hỗ trợ. Hãy thử lại.';
            mountMessageAiCallout();
        }
    }

    $('#messageList')?.addEventListener('click', event => {
        const close = event.target.closest('[data-ai-callout-close]');
        if (close) {
            closeMessageAiCallout();
            return;
        }
        const trigger = event.target.closest('[data-ask-ai]');
        if (!trigger) return;
        const messageId = Number(trigger.dataset.askAi || 0);
        if (messageAiState.activeMessageId === messageId && messageAiState.status !== 'error') {
            closeMessageAiCallout();
            return;
        }
        requestMessageAiGuidance(messageId);
    });

    async function loadMessages(conversationId) {
        const list = $('#messageList');
        if (!list || !conversationId) return;
        try {
            const response = await fetch(`api.php?action=messages&conversation_id=${conversationId}`);
            const data = await response.json();
            if (!data.ok) return;
            const nearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 80;
            const ambassadorPerspective = Boolean(document.querySelector('.inbox-shell'));
            const signature = data.messages.map(message => `${message.id}:${message.content}:${message.created_at}`).join('|');
            if (list.dataset.messageSignature === signature) return;
            list.innerHTML = data.messages.map((message) => {
                const mine = ambassadorPerspective
                    ? message.sender_role === 'ambassador' && Number(message.sender_id) === Number(data.current_user_id)
                    : Number(message.sender_id) === Number(data.current_user_id);
                const participantClass = ambassadorPerspective && !mine ? ' participant' : '';
                const askAiEligible = ambassadorPerspective && ['prospect', 'student'].includes(message.sender_role);
                const time = new Date(message.created_at.replace(' ', 'T')).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                const bubble = `<p>${escapeHtml(message.content)}</p>`;
                const messageContent = askAiEligible
                    ? `<div class="message-bubble-row">${bubble}<button class="message-ai-trigger" type="button" data-ask-ai="${Number(message.id)}" aria-expanded="false" aria-label="Nhờ AI gợi ý hướng phản hồi cho tin nhắn này" title="Ask AI"><i class="bi bi-magic" aria-hidden="true"></i><span class="visually-hidden">Ask AI</span></button></div><div class="message-ai-host" data-ai-callout-host></div>`
                    : bubble;
                return `<div class="message${mine ? ' mine' : participantClass}" data-message-id="${Number(message.id)}"><b>${escapeHtml(message.sender_name)}</b>${messageContent}<time>${time}</time></div>`;
            }).join('') || '<div class="empty-state compact">Hãy gửi lời chào đầu tiên nhé.</div>';
            list.dataset.messageSignature = signature;
            mountMessageAiCallout();
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
