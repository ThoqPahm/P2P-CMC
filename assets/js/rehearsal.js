(() => {
    'use strict';

    const config = window.CMC_REHEARSAL || {};
    const presentation = document.querySelector('#rehearsalPresentation');
    const slideStage = document.querySelector('#slideStage');
    const slideImage = document.querySelector('#slideImage');
    const visualType = document.querySelector('#visualType');
    const visualHint = document.querySelector('#visualHint');
    const fullscreenLink = document.querySelector('#fullscreenLink');
    const pane = document.querySelector('.script-pane');
    const roleBadge = document.querySelector('#roleBadge');
    const stepCounter = document.querySelector('#stepCounter');
    const title = document.querySelector('#scriptTitle');
    const screenCue = document.querySelector('#screenCue');
    const copy = document.querySelector('#scriptCopy');
    const outline = document.querySelector('#scriptOutline');
    const editor = document.querySelector('#scriptEditor');
    const editButton = document.querySelector('#editScript');
    const cancelButton = document.querySelector('#cancelEdit');
    const saveButton = document.querySelector('#saveScript');
    const saveStatus = document.querySelector('#saveStatus');
    const focusMode = document.querySelector('#focusMode');
    const focusPrompt = document.querySelector('#focusPrompt');
    const progress = document.querySelector('#progressBar');
    const previous = document.querySelector('#previousStep');
    const next = document.querySelector('#nextStep');
    let current = 0;
    let prototypeIndex = 0;
    let prototypeActive = false;
    let editing = false;

    const steps = [
        {
            key: 'slide-22', kind: 'slide', slide: 22, image: 'assets/img/rehearsal-slide-22.png',
            alt: 'Slide 22 - Bốn nhóm giải pháp trả lời năm vấn đề',
            role: 'TỔNG QUAN GIẢI PHÁP', roleClass: 'role-school', title: 'Bốn nhóm giải pháp trả lời năm vấn đề',
            screen: 'Slide 22 · Chỉ dẫn nhanh bốn nhóm GP1 đến GP4, không đọc lại toàn bộ slide.',
            paragraphs: [
                'Từ năm vấn đề ở Chương 3, nhóm xây dựng bốn nhóm giải pháp: chuẩn hóa thông tin; kết nối học sinh với đúng đại sứ; tổ chức đội ngũ đại sứ; và hỗ trợ phản hồi có kiểm soát.',
                'Bốn nhóm cùng hướng tới ba điều: đúng thông tin, đúng người và đúng thẩm quyền; còn độ chính xác, quyền riêng tư và trách nhiệm là yêu cầu xuyên suốt.',
                'Bốn nhóm này không vận hành riêng lẻ, mà được kết nối thành hai luồng bổ sung cho nhau.',
            ],
            outline: ['Năm vấn đề ở Chương 3 dẫn tới bốn nhóm giải pháp.', 'Ba đích đến: đúng thông tin, đúng người, đúng thẩm quyền.', 'P5 là yêu cầu xuyên suốt.', 'Chuyển sang hai luồng vận hành.'],
        },
        {
            key: 'slide-27', kind: 'slide', slide: 27, image: 'assets/img/rehearsal-slide-27.png',
            alt: 'Slide 27 - Hai luồng truyền thông vận hành bổ sung cho nhau',
            role: 'LUỒNG VẬN HÀNH', roleClass: 'role-student', title: 'Hai luồng truyền thông bổ sung cho nhau',
            screen: 'Slide 27 · Đi lần lượt Outbound → Inbound → vòng phản hồi ở cuối slide.',
            paragraphs: [
                'Luồng Outbound đi từ Nhà trường định hướng chủ đề, đại sứ kể trải nghiệm, nội dung được rà soát rồi lan tỏa.',
                'Khi học sinh muốn tìm hiểu sâu hơn, luồng Inbound bắt đầu: học sinh nêu nhu cầu, chọn đại sứ phù hợp để trao đổi; câu hỏi cần độ chính xác cao được chuyển cán bộ xác nhận.',
                'Nội dung tạo ra câu hỏi, còn câu hỏi lại trở thành đầu vào cho nội dung tiếp theo.',
                'Để minh họa vòng vận hành này, nhóm xây dựng bản mẫu CMC-eSA cho ba bên: học sinh, đại sứ và Nhà trường.',
            ],
            outline: ['Outbound: định hướng → kể → rà soát → lan tỏa.', 'Inbound: nhu cầu → chọn người → trao đổi → xác nhận.', 'Câu hỏi quay lại cải thiện nội dung.', 'Chuyển vào bản mẫu CMC-eSA.'],
        },
        {
            key: 'prototype-1', kind: 'prototype', role: 'NHÀ TRƯỜNG', roleClass: 'role-school', title: 'Nhà trường định hướng chủ đề',
            screen: 'Tổng quan quản trị → Chiến dịch → mở brief Digital Marketing.',
            paragraphs: ['Giả sử Nhà trường muốn giúp học sinh hiểu rõ hơn về ngành Digital Marketing. Nhà trường tạo chủ đề “Một ngày đi học ngành Digital Marketing”, kèm yêu cầu cụ thể.'],
            outline: ['Nhà trường xác định điều học sinh cần hiểu.', 'Tạo chủ đề và brief cụ thể.', 'Mở đầu luồng truyền thông chủ động.'],
        },
        {
            key: 'prototype-2', kind: 'prototype', role: 'ĐẠI SỨ', roleClass: 'role-ambassador', title: 'Đại sứ nhận nhiệm vụ và gửi nội dung',
            screen: 'Chuyển sang Tổng quan sinh viên → Chiến dịch nội dung → điền bài nộp.',
            paragraphs: ['Đại sứ nhận nhiệm vụ phù hợp, chia sẻ trải nghiệm của mình và gửi nội dung để rà soát. Nếu bài viết đề cập đến thông tin chính thức, phần đó cần được đối chiếu với nguồn của Nhà trường; còn cách kể vẫn giữ góc nhìn cá nhân của sinh viên.', 'Như vậy, đại sứ không chỉ được huy động để đăng bài, mà được định hướng, giao nhiệm vụ, hỗ trợ và ghi nhận trong một quy trình thống nhất.'],
            outline: ['Đại sứ nhận nhiệm vụ phù hợp.', 'Trải nghiệm cá nhân được giữ nguyên.', 'Thông tin chính thức phải được đối chiếu.', 'Đại sứ được định hướng và ghi nhận.'],
        },
        {
            key: 'prototype-3', kind: 'prototype', role: 'HỌC SINH', roleClass: 'role-student', title: 'Nội dung tiếp cận học sinh',
            screen: 'Mở widget trên website trường → chọn Content → mở bài đã duyệt.',
            paragraphs: ['Sau khi nội dung được lan tỏa, học sinh có thể đã quan tâm nhưng vẫn còn những câu hỏi riêng.', 'Bài chia sẻ đã được rà soát trở thành điểm chạm đầu tiên, giúp học sinh hình dung về ngành học trước khi đặt câu hỏi sâu hơn.'],
            outline: ['Nội dung đã duyệt tiếp cận học sinh.', 'Tạo hình dung ban đầu về ngành học.', 'Nội dung chung gợi ra câu hỏi riêng.'],
        },
        {
            key: 'prototype-4', kind: 'prototype', role: 'HỌC SINH', roleClass: 'role-student', title: 'Học sinh tìm đúng đại sứ',
            screen: 'Quay lại danh sách → lọc Digital Marketing → mở hồ sơ Trần Minh Anh.',
            paragraphs: ['Ví dụ, một học sinh hướng nội muốn biết mình có phù hợp với ngành Digital Marketing hay không.', 'Trên bản mẫu, học sinh có thể chọn ngành, xem hồ sơ và chủ đề mà từng đại sứ có thể chia sẻ, từ đó tìm người có trải nghiệm phù hợp để trao đổi. Nếu đại sứ chưa trực tuyến, học sinh có thể để lại lời nhắn hoặc đặt lịch.', 'Điểm quan trọng là học sinh không chỉ có thêm một kênh hỏi đáp, mà biết mình đang hỏi ai và người đó có trải nghiệm gì liên quan.'],
            outline: ['Học sinh có băn khoăn cá nhân.', 'Lọc theo ngành và xem hồ sơ.', 'Chọn người có trải nghiệm phù hợp.', 'Biết rõ mình đang hỏi ai.'],
        },
        {
            key: 'prototype-5', kind: 'prototype', role: 'HỌC SINH', roleClass: 'role-student', title: 'Trao đổi trải nghiệm, nhận diện giới hạn',
            screen: 'Từ hồ sơ đại sứ → Gửi tin nhắn → theo dõi cuộc hội thoại.',
            paragraphs: ['Tuy nhiên, tìm đúng người mới chỉ là bước đầu; giải pháp còn phải xác định người đó được trả lời đến đâu.', 'Với câu hỏi về việc một người hướng nội học ngành này sẽ gặp khó khăn gì, đại sứ có thể chia sẻ trải nghiệm, cách mình thích nghi và những điều học sinh nên chuẩn bị.'],
            outline: ['Tìm đúng người chưa đủ.', 'Đại sứ trả lời bằng trải nghiệm cá nhân.', 'Chia sẻ khó khăn, cách thích nghi và điều cần chuẩn bị.'],
        },
        {
            key: 'prototype-6', kind: 'prototype', role: 'ĐẠI SỨ', roleClass: 'role-ambassador', title: 'Đại sứ chuyển câu hỏi cần xác nhận',
            screen: 'Tổng quan sinh viên → Hộp thư → Mai Thu → Chuyển Ban Tuyển sinh.',
            paragraphs: ['Nhưng nếu học sinh hỏi tiếp: “Với kết quả của em, em có chắc chắn nhận được học bổng không?”, trách nhiệm trả lời sẽ khác. Đại sứ không tự kết luận mà chuyển câu hỏi đến cán bộ phụ trách.', 'Trong quá trình này, AI hỗ trợ tìm nguồn đã được duyệt, gợi ý phản hồi và phân loại câu hỏi. Đại sứ chia sẻ trải nghiệm; cán bộ xác nhận thông tin chính thức. Nhờ vậy, tốc độ phản hồi được cải thiện nhưng trách nhiệm vẫn thuộc về con người.'],
            outline: ['Học bổng cần độ chính xác cao.', 'Đại sứ không tự kết luận.', 'Chuyển nguyên câu hỏi cho cán bộ.', 'AI hỗ trợ; con người chịu trách nhiệm.'],
        },
        {
            key: 'prototype-7', kind: 'prototype', role: 'NHÀ TRƯỜNG', roleClass: 'role-school', title: 'Nhà trường tiếp nhận và xác nhận',
            screen: 'Tổng quan quản trị → Kiểm duyệt chat → vụ việc Mai Thu → nhập xác nhận.',
            paragraphs: ['Và cuộc trao đổi này không kết thúc sau khi học sinh nhận được câu trả lời.', 'Ở phía Nhà trường, cán bộ có thể theo dõi bài nộp, các nội dung cần rà soát và những câu hỏi đang chờ xác nhận. Cán bộ đối chiếu nguồn trước khi đưa ra thông tin chính thức.'],
            outline: ['Cán bộ tiếp nhận câu hỏi chuyển tuyến.', 'Theo dõi nội dung cần rà soát.', 'Đối chiếu nguồn.', 'Xác nhận thông tin chính thức.'],
        },
        {
            key: 'prototype-8', kind: 'prototype', role: 'NHÀ TRƯỜNG', roleClass: 'role-school', title: 'Phản hồi quay lại cải thiện truyền thông',
            screen: 'Từ Kiểm duyệt chat → mở sidebar → Tổng quan → nhìn lại luồng Chiến dịch.',
            paragraphs: ['Nếu nhiều học sinh cùng băn khoăn về người hướng nội có phù hợp với ngành Digital Marketing hay không, Nhà trường có thể đưa vấn đề này thành chủ đề cho chiến dịch tiếp theo và giao cho đại sứ có trải nghiệm phù hợp.', 'Như vậy, phản hồi từ tư vấn quay trở lại cải thiện nội dung truyền thông. Đây chính là điểm nối giữa hai luồng mà nhóm vừa trình bày.', 'Qua tình huống trên, CMC-eSA minh họa cách kết nối nhu cầu của học sinh, trải nghiệm của sinh viên và thông tin chính thức của Nhà trường trong cùng một quy trình. Tuy nhiên, đây mới là bản mẫu với dữ liệu mô phỏng; Chương 5 sẽ trình bày lộ trình, nguồn lực và điều kiện để đưa giải pháp vào thí điểm.'],
            outline: ['Câu hỏi lặp lại trở thành dữ liệu đầu vào.', 'Nhà trường tạo chủ đề cho chiến dịch tiếp theo.', 'Inbound quay lại cải thiện Outbound.', 'Chuyển sang lộ trình thí điểm ở Chương 5.'],
        },
    ];

    const textToParagraphs = (text) => text.split(/\n\s*\n/).map((part) => part.trim()).filter(Boolean);
    Object.entries(config.savedScripts || {}).forEach(([key, content]) => {
        const step = steps.find((item) => item.key === key);
        if (step && typeof content === 'string' && content.trim()) step.paragraphs = textToParagraphs(content);
    });

    const replaceTextItems = (container, items, tagName) => {
        container.replaceChildren(...items.map((item) => {
            const element = document.createElement(tagName);
            element.textContent = item;
            return element;
        }));
    };

    const updateVisual = () => {
        const step = steps[current];
        if (step.kind === 'slide') {
            prototypeActive = false;
            slideImage.src = step.image;
            slideImage.alt = step.alt;
            slideStage.classList.remove('is-hidden');
            presentation.classList.add('is-hidden');
            visualType.textContent = `SLIDE ${step.slide}`;
            visualHint.textContent = 'Dùng phím mũi tên để đi xuyên suốt phần trình bày';
            fullscreenLink.href = step.image;
            fullscreenLink.textContent = 'Mở slide ↗';
            return;
        }
        prototypeActive = true;
        slideStage.classList.add('is-hidden');
        presentation.classList.remove('is-hidden');
        visualType.textContent = 'PROTOTYPE';
        visualHint.textContent = 'Thao tác trực tiếp hoặc dùng phím mũi tên';
        fullscreenLink.href = 'presentation.php';
        fullscreenLink.textContent = 'Mở prototype ↗';
    };

    const render = (index) => {
        current = Math.max(0, Math.min(steps.length - 1, index));
        const step = steps[current];
        roleBadge.className = `role-badge ${step.roleClass}`;
        roleBadge.textContent = step.role;
        stepCounter.textContent = `PHẦN ${current + 1} / ${steps.length}`;
        title.textContent = step.title;
        screenCue.textContent = step.screen;
        replaceTextItems(copy, step.paragraphs, 'p');
        replaceTextItems(outline, step.outline, 'li');
        focusPrompt.textContent = step.title;
        progress.style.width = `${((current + 1) / steps.length) * 100}%`;
        previous.disabled = editing || current === 0;
        next.disabled = editing || current === steps.length - 1;
        next.textContent = current === steps.length - 1 ? 'Đã hết 10 phần' : 'Phần tiếp →';
        document.querySelector('#scriptCard').scrollTop = 0;
        saveStatus.textContent = '';
        saveStatus.classList.remove('is-error');
        updateVisual();
    };

    const endEditing = () => {
        editing = false;
        pane.classList.remove('is-editing');
        editButton.classList.remove('is-hidden');
        cancelButton.classList.add('is-hidden');
        saveButton.classList.add('is-hidden');
        focusMode.disabled = false;
        previous.disabled = current === 0;
        next.disabled = current === steps.length - 1;
    };

    const requestMove = (direction) => {
        if (editing) return;
        if (current < 2) {
            const wanted = current + direction;
            if (wanted >= 0 && wanted <= 2) render(wanted);
            return;
        }
        if (direction < 0 && current === 2) {
            render(1);
            return;
        }
        if (direction > 0 && current === steps.length - 1) return;
        presentation.contentWindow?.postMessage({ type: 'cmc-presentation-go', direction }, window.location.origin);
    };

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin || event.source !== presentation.contentWindow) return;
        if (event.data?.type === 'cmc-presentation-boundary' && prototypeActive && Number(event.data.direction) < 0) {
            if (editing) endEditing();
            render(1);
            return;
        }
        if (event.data?.type !== 'cmc-presentation-scene') return;
        prototypeIndex = Math.max(0, Math.min(7, Number(event.data.index) || 0));
        if (!prototypeActive) return;
        if (editing) endEditing();
        render(prototypeIndex + 2);
    });

    document.querySelectorAll('[data-script-tab]').forEach((button) => button.addEventListener('click', () => {
        if (editing) return;
        pane.dataset.tab = button.dataset.scriptTab;
        document.querySelectorAll('[data-script-tab]').forEach((item) => {
            const active = item === button;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', String(active));
        });
    }));

    focusMode.addEventListener('click', () => {
        if (editing) return;
        const active = pane.classList.toggle('is-focus');
        focusMode.setAttribute('aria-pressed', String(active));
        focusMode.textContent = active ? 'Hiện lời' : 'Ẩn lời để tập';
    });

    editButton.addEventListener('click', () => {
        editing = true;
        pane.classList.remove('is-focus');
        focusMode.setAttribute('aria-pressed', 'false');
        focusMode.textContent = 'Ẩn lời để tập';
        focusMode.disabled = true;
        editor.value = steps[current].paragraphs.join('\n\n');
        pane.classList.add('is-editing');
        editButton.classList.add('is-hidden');
        cancelButton.classList.remove('is-hidden');
        saveButton.classList.remove('is-hidden');
        previous.disabled = true;
        next.disabled = true;
        saveStatus.textContent = '';
        editor.focus();
    });

    cancelButton.addEventListener('click', endEditing);
    saveButton.addEventListener('click', async () => {
        const content = editor.value.trim();
        if (!content) {
            saveStatus.textContent = 'Lời thuyết trình không được để trống.';
            saveStatus.classList.add('is-error');
            return;
        }
        saveButton.disabled = true;
        saveButton.textContent = 'Đang lưu…';
        saveStatus.textContent = '';
        try {
            const response = await fetch(config.saveUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': config.csrfToken },
                body: JSON.stringify({ stepKey: steps[current].key, content }),
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'Không thể lưu lời thuyết trình.');
            steps[current].paragraphs = textToParagraphs(result.content);
            endEditing();
            replaceTextItems(copy, steps[current].paragraphs, 'p');
            saveStatus.textContent = 'Đã lưu lời cho phần này.';
            saveStatus.classList.remove('is-error');
        } catch (error) {
            saveStatus.textContent = error.message || 'Không thể lưu lời thuyết trình.';
            saveStatus.classList.add('is-error');
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = 'Lưu';
        }
    });

    previous.addEventListener('click', () => requestMove(-1));
    next.addEventListener('click', () => requestMove(1));
    document.addEventListener('keydown', (event) => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target?.tagName)) return;
        if (event.key === 'ArrowRight' || event.key === 'PageDown') { event.preventDefault(); requestMove(1); }
        if (event.key === 'ArrowLeft' || event.key === 'PageUp') { event.preventDefault(); requestMove(-1); }
    });

    pane.dataset.tab = 'full';
    render(0);
})();
