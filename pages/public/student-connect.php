<?php
$pageTitle = 'Trò chuyện cùng sinh viên CMCU';
$featuredAmbassadors = rows("SELECT * FROM eligible_ambassadors ORDER BY (avatar IS NULL), is_online DESC, id LIMIT 4");
$widgetUrl = Routes::url('widget');
?>
<main class="connect-page">
    <section class="connect-hero">
        <div class="container connect-hero-grid">
            <div class="connect-hero-copy">
                <p class="connect-eyebrow"><i class="bi bi-chat-square-text"></i> Kết nối cùng sinh viên CMCU</p>
                <h1>Hỏi người đang học,<br><span>hiểu điều bạn sắp chọn.</span></h1>
                <p class="connect-lead">Trò chuyện trực tiếp với đại sứ sinh viên để tìm hiểu ngành học, môi trường, hoạt động và cuộc sống tại Trường Đại học CMC.</p>
                <div class="connect-actions">
                    <button class="btn btn-connect-primary btn-lg" type="button" data-open-eambassador>Trò chuyện ngay <i class="bi bi-arrow-up-right"></i></button>
                    <a class="btn btn-connect-ghost btn-lg" href="#bat-dau">Tìm hiểu cách hoạt động</a>
                </div>
                <p class="connect-trust"><i class="bi bi-shield-check"></i> Miễn phí · Đại sứ được xác minh · Có thể nhắn khi offline</p>
            </div>
            <figure class="connect-hero-media">
                <img src="assets/img/cmc-connect-hero.png" alt="Minh họa kết nối trong hệ sinh thái số CMCU">
                <figcaption><span class="connect-live-dot"></span><strong><?= count($featuredAmbassadors) ?> đại sứ nổi bật</strong><small>Sẵn sàng chia sẻ trải nghiệm</small></figcaption>
            </figure>
        </div>
    </section>

    <nav class="connect-anchor-nav" aria-label="Nội dung trang"><div class="container"><a href="#gioi-thieu">Giới thiệu</a><a href="#co-the-hoi">Bạn có thể hỏi gì?</a><a href="#dai-su">Đại sứ nổi bật</a><a href="#bat-dau">Cách bắt đầu</a></div></nav>

    <section class="connect-intro connect-section" id="gioi-thieu">
        <div class="container connect-copy-grid">
            <div><p class="connect-section-label">Student Connect</p><h2>Một góc nhìn chân thực trước quyết định quan trọng.</h2></div>
            <div class="connect-rich-copy"><p>Không phải câu hỏi nào cũng có thể trả lời bằng brochure. Đại sứ sinh viên CMCU giúp bạn hiểu rõ hơn trải nghiệm học tập, cách thích nghi với môi trường đại học và những điều diễn ra trong đời sống sinh viên hằng ngày.</p><p>Bạn có thể chọn người phù hợp theo ngành, năm học, quê quán hoặc sở thích; xem hồ sơ rồi bắt đầu trò chuyện ngay trong widget ở góc màn hình.</p></div>
        </div>
    </section>

    <section class="connect-section connect-topics" id="co-the-hoi">
        <div class="container">
            <header class="connect-heading"><p class="connect-section-label">Nội dung trao đổi</p><h2>Hãy hỏi về trải nghiệm mà bạn thực sự quan tâm.</h2><p>Đại sứ chia sẻ từ hành trình của chính mình, bằng góc nhìn của một sinh viên đang học tại CMCU.</p></header>
            <div class="connect-topic-grid">
                <article><i class="bi bi-mortarboard"></i><h3>Học tập & ngành học</h3><p>Môn học, cách học, bài tập nhóm, đồ án và những năng lực nên chuẩn bị.</p></article>
                <article><i class="bi bi-people"></i><h3>Đời sống sinh viên</h3><p>Câu lạc bộ, sự kiện, bạn bè, cơ sở học tập và cách hòa nhập môi trường mới.</p></article>
                <article><i class="bi bi-briefcase"></i><h3>Dự án & định hướng</h3><p>Portfolio, kỳ thực tập, hoạt động ngoại khóa và trải nghiệm làm dự án thực tế.</p></article>
                <article><i class="bi bi-house-heart"></i><h3>Thích nghi & trưởng thành</h3><p>Quản lý thời gian, sống xa nhà, kết nối bạn mới và cân bằng cuộc sống.</p></article>
            </div>
            <aside class="connect-boundary"><i class="bi bi-info-circle"></i><div><h3>Thông tin nào cần hỏi nhà trường?</h3><p>Đại sứ chia sẻ trải nghiệm cá nhân, không thay thế bộ phận tuyển sinh trong việc xác nhận hồ sơ, điều kiện xét tuyển, học phí, học bổng hoặc các chính sách chính thức.</p></div><a href="https://cmcu.edu.vn/" target="_blank" rel="noopener">Kênh thông tin CMCU <i class="bi bi-arrow-up-right"></i></a></aside>
        </div>
    </section>

    <section class="connect-section connect-ambassadors" id="dai-su">
        <div class="container">
            <header class="connect-heading connect-heading-row"><div><p class="connect-section-label">Người thật, trải nghiệm thật</p><h2>Gặp một vài đại sứ CMCU.</h2></div><a href="<?= e(Routes::url('ambassadors')) ?>">Xem tất cả đại sứ <i class="bi bi-arrow-right"></i></a></header>
            <div class="connect-people-grid">
                <?php foreach ($featuredAmbassadors as $ambassador): ?>
                    <article class="connect-person">
                        <div class="connect-person-photo"><?= AmbassadorProfiles::avatarHtml($ambassador) ?><span class="<?= (int)$ambassador['is_online'] ? 'is-online' : '' ?>"></span></div>
                        <div><p><?= (int)$ambassador['is_online'] ? 'Đang online' : 'Có thể để lại lời nhắn' ?></p><h3><?= e($ambassador['name']) ?></h3><span><?= e($ambassador['major']) ?> · Năm <?= (int)$ambassador['study_year'] ?></span><a href="<?= e(Routes::url('ambassadors', ['ambassador'=>(int)$ambassador['id']])) ?>">Xem hồ sơ <i class="bi bi-arrow-up-right"></i></a></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="connect-section connect-steps" id="bat-dau">
        <div class="container connect-steps-grid">
            <div class="connect-steps-copy"><p class="connect-section-label">Bắt đầu trong vài phút</p><h2>Từ một băn khoăn đến một cuộc trò chuyện có ích.</h2><p>Không cần đăng nhập để khám phá. Phiên trò chuyện được lưu trong trình duyệt để bạn có thể quay lại khi nhận phản hồi.</p><button class="btn btn-connect-primary" type="button" data-open-eambassador>Mở widget trò chuyện <i class="bi bi-chat-dots"></i></button></div>
            <ol>
                <li><span>01</span><div><h3>Tìm người phù hợp</h3><p>Lọc theo ngành học, quê quán hoặc điều bạn quan tâm.</p></div></li>
                <li><span>02</span><div><h3>Xem hồ sơ đại sứ</h3><p>Đọc sở thích, hoạt động, dự án và chủ đề có thể tư vấn.</p></div></li>
                <li><span>03</span><div><h3>Đặt câu hỏi tự nhiên</h3><p>Nhắn như một cuộc trò chuyện bình thường; nếu đại sứ offline, bạn vẫn có thể gửi lời nhắn hoặc đặt lịch.</p></div></li>
            </ol>
        </div>
    </section>

    <section class="connect-final"><div class="container"><div><p class="connect-section-label">Bạn muốn biết điều gì về CMCU?</p><h2>Bắt đầu bằng câu hỏi của chính bạn.</h2></div><button class="btn btn-light btn-lg" type="button" data-open-eambassador>Trò chuyện cùng đại sứ <i class="bi bi-arrow-right"></i></button></div></section>
</main>
<script src="assets/js/eambassador-widget.js?v=2" data-widget-url="<?= e($widgetUrl) ?>" data-position="right" data-label="Hỏi đại sứ CMCU" async></script>
<script>
document.addEventListener('click', function (event) {
    if (!event.target.closest('[data-open-eambassador]')) return;
    const root = document.getElementById('eambassador-widget-root');
    const button = root && root.shadowRoot && root.shadowRoot.querySelector('.launcher');
    if (button && button.getAttribute('aria-expanded') !== 'true') button.click();
});
</script>
