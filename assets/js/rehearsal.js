(() => {
    'use strict';

    const presentation = document.querySelector('#rehearsalPresentation');
    const pane = document.querySelector('.script-pane');
    const roleBadge = document.querySelector('#roleBadge');
    const stepCounter = document.querySelector('#stepCounter');
    const title = document.querySelector('#scriptTitle');
    const screenCue = document.querySelector('#screenCue');
    const copy = document.querySelector('#scriptCopy');
    const outline = document.querySelector('#scriptOutline');
    const focusMode = document.querySelector('#focusMode');
    const focusPrompt = document.querySelector('#focusPrompt');
    const progress = document.querySelector('#progressBar');
    const previous = document.querySelector('#previousStep');
    const next = document.querySelector('#nextStep');
    let current = 0;
    let ready = false;

    const steps = [
        {
            role: 'NHÀ TRƯỜNG', roleClass: 'role-school', title: 'Nhà trường định hướng chủ đề',
            screen: 'Tổng quan quản trị → Chiến dịch → mở brief Digital Marketing.',
            paragraphs: [
                'Giả sử Nhà trường muốn giúp học sinh hiểu rõ hơn về ngành Digital Marketing. Nhà trường tạo chủ đề “Một ngày đi học ngành Digital Marketing”, kèm yêu cầu cụ thể.',
            ],
            outline: ['Nhà trường xác định điều học sinh cần hiểu.', 'Tạo chủ đề và brief cụ thể.', 'Mở đầu luồng truyền thông chủ động.'],
        },
        {
            role: 'ĐẠI SỨ', roleClass: 'role-ambassador', title: 'Đại sứ nhận nhiệm vụ và gửi nội dung',
            screen: 'Chuyển sang Tổng quan sinh viên → Chiến dịch nội dung → điền bài nộp.',
            paragraphs: [
                'Đại sứ nhận nhiệm vụ phù hợp, chia sẻ trải nghiệm của mình và gửi nội dung để rà soát. Nếu bài viết đề cập đến thông tin chính thức, phần đó cần được đối chiếu với nguồn của Nhà trường; còn cách kể vẫn giữ góc nhìn cá nhân của sinh viên.',
                'Như vậy, đại sứ không chỉ được huy động để đăng bài, mà được định hướng, giao nhiệm vụ, hỗ trợ và ghi nhận trong một quy trình thống nhất.',
            ],
            outline: ['Đại sứ nhận nhiệm vụ phù hợp.', 'Trải nghiệm cá nhân được giữ nguyên.', 'Thông tin chính thức phải được đối chiếu.', 'Đại sứ được định hướng và ghi nhận.'],
        },
        {
            role: 'HỌC SINH', roleClass: 'role-student', title: 'Nội dung tiếp cận học sinh',
            screen: 'Mở widget trên website trường → chọn Content → mở bài đã duyệt.',
            paragraphs: [
                'Sau khi nội dung được lan tỏa, học sinh có thể đã quan tâm nhưng vẫn còn những câu hỏi riêng.',
                'Bài chia sẻ đã được rà soát trở thành điểm chạm đầu tiên, giúp học sinh hình dung về ngành học trước khi đặt câu hỏi sâu hơn.',
            ],
            outline: ['Nội dung đã duyệt tiếp cận học sinh.', 'Tạo hình dung ban đầu về ngành học.', 'Nội dung chung gợi ra câu hỏi riêng.'],
        },
        {
            role: 'HỌC SINH', roleClass: 'role-student', title: 'Học sinh tìm đúng đại sứ',
            screen: 'Quay lại danh sách → lọc Digital Marketing → mở hồ sơ Trần Minh Anh.',
            paragraphs: [
                'Ví dụ, một học sinh hướng nội muốn biết mình có phù hợp với ngành Digital Marketing hay không.',
                'Trên bản mẫu, học sinh có thể chọn ngành, xem hồ sơ và chủ đề mà từng đại sứ có thể chia sẻ, từ đó tìm người có trải nghiệm phù hợp để trao đổi. Nếu đại sứ chưa trực tuyến, học sinh có thể để lại lời nhắn hoặc đặt lịch.',
                'Điểm quan trọng là học sinh không chỉ có thêm một kênh hỏi đáp, mà biết mình đang hỏi ai và người đó có trải nghiệm gì liên quan.',
            ],
            outline: ['Học sinh có băn khoăn cá nhân.', 'Lọc theo ngành và xem hồ sơ.', 'Chọn người có trải nghiệm phù hợp.', 'Biết rõ mình đang hỏi ai.'],
        },
        {
            role: 'HỌC SINH', roleClass: 'role-student', title: 'Trao đổi trải nghiệm, nhận diện giới hạn',
            screen: 'Từ hồ sơ đại sứ → Gửi tin nhắn → theo dõi cuộc hội thoại.',
            paragraphs: [
                'Tuy nhiên, tìm đúng người mới chỉ là bước đầu; giải pháp còn phải xác định người đó được trả lời đến đâu.',
                'Với câu hỏi về việc một người hướng nội học ngành này sẽ gặp khó khăn gì, đại sứ có thể chia sẻ trải nghiệm, cách mình thích nghi và những điều học sinh nên chuẩn bị.',
            ],
            outline: ['Tìm đúng người chưa đủ.', 'Đại sứ trả lời bằng trải nghiệm cá nhân.', 'Chia sẻ khó khăn, cách thích nghi và điều cần chuẩn bị.'],
        },
        {
            role: 'ĐẠI SỨ', roleClass: 'role-ambassador', title: 'Đại sứ chuyển câu hỏi cần xác nhận',
            screen: 'Tổng quan sinh viên → Hộp thư → Mai Thu → Chuyển Ban Tuyển sinh.',
            paragraphs: [
                'Nhưng nếu học sinh hỏi tiếp: “Với kết quả của em, em có chắc chắn nhận được học bổng không?”, trách nhiệm trả lời sẽ khác. Đại sứ không tự kết luận mà chuyển câu hỏi đến cán bộ phụ trách.',
                'Trong quá trình này, AI hỗ trợ tìm nguồn đã được duyệt, gợi ý phản hồi và phân loại câu hỏi. Đại sứ chia sẻ trải nghiệm; cán bộ xác nhận thông tin chính thức. Nhờ vậy, tốc độ phản hồi được cải thiện nhưng trách nhiệm vẫn thuộc về con người.',
            ],
            outline: ['Học bổng cần độ chính xác cao.', 'Đại sứ không tự kết luận.', 'Chuyển nguyên câu hỏi cho cán bộ.', 'AI hỗ trợ; con người chịu trách nhiệm.'],
        },
        {
            role: 'NHÀ TRƯỜNG', roleClass: 'role-school', title: 'Nhà trường tiếp nhận và xác nhận',
            screen: 'Tổng quan quản trị → Kiểm duyệt chat → vụ việc Mai Thu → nhập xác nhận.',
            paragraphs: [
                'Và cuộc trao đổi này không kết thúc sau khi học sinh nhận được câu trả lời.',
                'Ở phía Nhà trường, cán bộ có thể theo dõi bài nộp, các nội dung cần rà soát và những câu hỏi đang chờ xác nhận. Cán bộ đối chiếu nguồn trước khi đưa ra thông tin chính thức.',
            ],
            outline: ['Cán bộ tiếp nhận câu hỏi chuyển tuyến.', 'Theo dõi nội dung cần rà soát.', 'Đối chiếu nguồn.', 'Xác nhận thông tin chính thức.'],
        },
        {
            role: 'NHÀ TRƯỜNG', roleClass: 'role-school', title: 'Phản hồi quay lại cải thiện truyền thông',
            screen: 'Từ Kiểm duyệt chat → mở sidebar → Tổng quan → nhìn lại luồng Chiến dịch.',
            paragraphs: [
                'Nếu nhiều học sinh cùng băn khoăn về người hướng nội có phù hợp với ngành Digital Marketing hay không, Nhà trường có thể đưa vấn đề này thành chủ đề cho chiến dịch tiếp theo và giao cho đại sứ có trải nghiệm phù hợp.',
                'Như vậy, phản hồi từ tư vấn quay trở lại cải thiện nội dung truyền thông. Đây chính là điểm nối giữa hai luồng mà nhóm vừa trình bày.',
                'Qua tình huống trên, CMC-eSA minh họa cách kết nối nhu cầu của học sinh, trải nghiệm của sinh viên và thông tin chính thức của Nhà trường trong cùng một quy trình. Tuy nhiên, đây mới là bản mẫu với dữ liệu mô phỏng; Chương 5 sẽ trình bày lộ trình, nguồn lực và điều kiện để đưa giải pháp vào thí điểm.',
            ],
            outline: ['Câu hỏi lặp lại trở thành dữ liệu đầu vào.', 'Nhà trường tạo chủ đề cho chiến dịch tiếp theo.', 'Inbound quay lại cải thiện Outbound.', 'Chuyển sang lộ trình thí điểm ở Chương 5.'],
        },
    ];

    const render = (index) => {
        current = Math.max(0, Math.min(steps.length - 1, index));
        const step = steps[current];
        roleBadge.className = `role-badge ${step.roleClass}`;
        roleBadge.textContent = step.role;
        stepCounter.textContent = `STEP ${current + 1} / ${steps.length}`;
        title.textContent = step.title;
        screenCue.textContent = step.screen;
        copy.innerHTML = step.paragraphs.map((paragraph) => `<p>${paragraph}</p>`).join('');
        outline.innerHTML = step.outline.map((item) => `<li>${item}</li>`).join('');
        focusPrompt.textContent = step.title;
        progress.style.width = `${((current + 1) / steps.length) * 100}%`;
        previous.disabled = !ready || current === 0;
        next.disabled = !ready || current === steps.length - 1;
        next.textContent = current === steps.length - 1 ? 'Đã hết 8 step' : 'Step tiếp →';
        document.querySelector('#scriptCard').scrollTop = 0;
    };

    const requestMove = (direction) => {
        if (!ready || (direction < 0 && current === 0) || (direction > 0 && current === steps.length - 1)) return;
        ready = false;
        previous.disabled = true;
        next.disabled = true;
        presentation.contentWindow?.postMessage({ type: 'cmc-presentation-go', direction }, window.location.origin);
    };

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin || event.source !== presentation.contentWindow) return;
        if (event.data?.type !== 'cmc-presentation-scene') return;
        ready = event.data.phase === 'ready';
        render(Number(event.data.index) || 0);
    });

    document.querySelectorAll('[data-script-tab]').forEach((button) => button.addEventListener('click', () => {
        pane.dataset.tab = button.dataset.scriptTab;
        document.querySelectorAll('[data-script-tab]').forEach((item) => {
            const active = item === button;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', String(active));
        });
    }));

    focusMode.addEventListener('click', () => {
        const active = pane.classList.toggle('is-focus');
        focusMode.setAttribute('aria-pressed', String(active));
        focusMode.textContent = active ? 'Hiện lời' : 'Ẩn lời để tập';
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
