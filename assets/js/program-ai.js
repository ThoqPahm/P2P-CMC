(() => {
    'use strict';
    document.querySelectorAll('[data-ai-assist]').forEach(panel => {
        const button = panel.querySelector('[data-ai-run]');
        const output = panel.querySelector('[data-ai-output]');
        const paragraph = (label, value) => {
            if (!value) return;
            const p = document.createElement('p');
            p.style.whiteSpace = 'pre-wrap';
            const strong = document.createElement('strong');
            strong.textContent = `${label}: `;
            p.append(strong, document.createTextNode(value));
            output.append(p);
        };
        const insertButton = (label, selector, text) => {
            const target = document.querySelector(selector);
            if (!target || !text) return;
            const action = document.createElement('button');
            action.type = 'button';
            action.className = 'btn btn-sm btn-outline-brand me-2 mb-2';
            action.textContent = label;
            action.addEventListener('click', () => {
                if (target.value.trim() && !window.confirm('Thay bản đang soạn bằng gợi ý này?')) return;
                target.value = text;
                target.dispatchEvent(new Event('input', {bubbles: true}));
                if (selector === '#escalateReason') {
                    window.bootstrap.Modal.getOrCreateInstance(document.querySelector('#escalateModal')).show();
                } else target.focus();
            });
            output.append(action);
        };
        button.addEventListener('click', async () => {
            button.disabled = true;
            output.textContent = 'Đang đọc ngữ cảnh và chuẩn bị gợi ý…';
            try {
                const response = await fetch(`api.php?action=${encodeURIComponent(panel.dataset.aiAssist)}`, {
                    method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''},
                    body: JSON.stringify({conversation_id: Number(panel.dataset.conversationId || 0)})
                });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.message || 'Chưa tạo được gợi ý.');
                const result = data.result;
                output.replaceChildren();
                paragraph('Tóm tắt', result.summary);
                const labels = {general: 'Thông tin phổ biến', experience: 'Trải nghiệm sinh viên', policy: 'Cần cán bộ xác nhận', sensitive: 'Cần hỗ trợ riêng'};
                paragraph('Phân loại gợi ý', labels[result.category]);
                paragraph('Gợi ý trả lời', result.draft);
                paragraph('Câu hỏi cần xác nhận', result.escalation_note);
                (result.sources || []).forEach(source => paragraph('Nguồn', `${source.title} · ${source.source_reference || ''}`));
                (result.actions || []).forEach(action => paragraph('Đề xuất', action));
                paragraph('Giới hạn', result.limitations);
                insertButton('Đưa vào ô soạn (chưa gửi)', '#messageInput', result.draft);
                insertButton('Xem lại câu hỏi chuyển cán bộ', '#escalateReason', result.escalation_note);
            } catch (error) {
                output.textContent = error instanceof SyntaxError ? 'Máy chủ chưa hoàn tất phản hồi. Hãy thử lại.' : error.message;
            } finally { button.disabled = false; }
        });
    });
})();
