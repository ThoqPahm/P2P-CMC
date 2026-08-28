<?php
$pageTitle = 'Kết nối thật, chọn đúng tương lai';
$ambassadors = rows("SELECT * FROM users WHERE role = 'ambassador' AND status = 'active' ORDER BY is_online DESC, id LIMIT 3");
$stats = [
    'ambassadors' => scalar("SELECT COUNT(*) FROM users WHERE role = 'ambassador' AND status = 'active'"),
    'conversations' => scalar('SELECT COUNT(*) FROM conversations'),
    'submissions' => scalar("SELECT COUNT(*) FROM submissions WHERE status = 'approved'"),
];
?>
<section class="cmc-hero">
    <div class="container">
        <div class="cmc-hero-grid">
            <div class="cmc-hero-copy">
                <span class="hero-kicker"><i class="bi bi-patch-check-fill"></i> Sinh viên CMC đã xác minh</span>
                <h1>Nghe người đang học. <span>Chọn tương lai của bạn.</span></h1>
                <p class="hero-lead">Hỏi thật về ngành học, môi trường và đời sống tại CMC University.</p>
                <div class="hero-actions">
                    <a class="btn btn-brand btn-lg" href="index.php?page=ambassadors">Tìm người phù hợp <i class="bi bi-arrow-right"></i></a>
                    <a class="btn btn-ghost btn-lg" href="#how-it-works">Cách hoạt động</a>
                </div>
            </div>
            <figure class="cmc-hero-art">
                <img src="assets/img/cmc-connect-hero.png" alt="Minh họa kết nối cộng đồng sinh viên CMC University">
                <figcaption><i class="bi bi-shield-check"></i> Kết nối an toàn, có kiểm duyệt</figcaption>
            </figure>
        </div>
    </div>
</section>

<section class="cmc-proof" aria-label="Số liệu đang hoạt động">
    <div class="container">
        <div class="cmc-proof-grid">
            <p><strong><?= (int) $stats['ambassadors'] ?></strong><span>đại sứ đang hoạt động</span></p>
            <p><strong><?= (int) $stats['conversations'] ?></strong><span>cuộc trò chuyện đã mở</span></p>
            <p><strong><?= (int) $stats['submissions'] ?></strong><span>nội dung sinh viên đã được duyệt</span></p>
            <p class="proof-statement"><i class="bi bi-person-check"></i><span>Người thật, thông tin thật, góc nhìn riêng</span></p>
        </div>
    </div>
</section>

<section class="section-space cmc-about" id="about">
    <div class="container">
        <div class="section-heading cmc-heading-split">
            <h2>Những điều brochure không thể kể hết.</h2>
            <p>Mỗi câu hỏi được trả lời bởi chính sinh viên đang học, để bạn hiểu một lựa chọn trước khi sống cùng lựa chọn đó.</p>
        </div>
        <div class="cmc-capability-grid">
            <article class="capability-main">
                <span class="feature-icon"><i class="bi bi-mortarboard"></i></span>
                <div><h3>Ngành này có thật sự hợp với mình?</h3><p>Đi thẳng vào môn học, khối lượng bài tập, cách học và năng lực cần chuẩn bị.</p><a href="index.php?page=ambassadors">Tìm người cùng ngành <i class="bi bi-arrow-right"></i></a></div>
            </article>
            <article>
                <span class="feature-icon"><i class="bi bi-people"></i></span>
                <div><h3>Sống ở CMCU ra sao?</h3><p>CLB, sự kiện, bạn bè và những nhịp sống thường ngày.</p></div>
            </article>
            <article>
                <span class="feature-icon"><i class="bi bi-briefcase"></i></span>
                <div><h3>Đi tiếp bằng con đường nào?</h3><p>Kinh nghiệm làm dự án, portfolio và bước đầu với doanh nghiệp.</p></div>
            </article>
        </div>
    </div>
</section>

<section class="section-space cmc-stories">
    <div class="container">
        <div class="cmc-story-grid">
            <div class="story-copy">
                <span class="section-kicker">Humans of CMCU</span>
                <h2>Một trường đại học được tạo nên từ nhiều câu chuyện.</h2>
                <p>Từ công nghệ, kinh doanh đến thiết kế, mỗi sinh viên có một điểm bắt đầu và một cách trưởng thành khác nhau.</p>
                <a class="text-link" href="index.php?page=ambassadors">Khám phá cộng đồng <i class="bi bi-arrow-right"></i></a>
            </div>
            <figure class="story-image story-image-main"><img src="assets/img/student-khanh-linh.jpg" alt="Sinh viên CMC University trong dự án Humans of CMCU"></figure>
            <figure class="story-image"><img src="assets/img/student-minh-anh.jpg" alt="Chân dung sinh viên CMC University"></figure>
            <figure class="story-image"><img src="assets/img/student-duc-nam.jpg" alt="Sinh viên ngành công nghệ tại CMC University"></figure>
        </div>
    </div>
</section>

<section class="section-space ambassadors-preview">
    <div class="container">
        <div class="section-heading d-lg-flex align-items-end justify-content-between">
            <div><h2>Gặp người bạn muốn lắng nghe.</h2><p>Chọn theo ngành học, sở thích hoặc trải nghiệm bạn đang quan tâm.</p></div>
            <a class="text-link" href="index.php?page=ambassadors">Xem tất cả đại sứ <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row g-4 mt-2">
            <?php foreach ($ambassadors as $index => $ambassador): ?>
                <div class="col-md-6 col-lg-4">
                    <article class="ambassador-card h-100">
                        <div class="ambassador-cover cover-<?= ($index % 3) + 1 ?>"><span class="profile-monogram"><?= e(initials($ambassador['name'])) ?></span><?php if ($ambassador['is_online']): ?><span class="online-badge"><i></i> Đang online</span><?php endif; ?></div>
                        <div class="ambassador-body"><span class="verified"><i class="bi bi-patch-check-fill"></i> Sinh viên đã xác minh</span><h3><?= e($ambassador['name']) ?></h3><p class="major"><i class="bi bi-book"></i> <?= e($ambassador['major']) ?> | Năm <?= (int) $ambassador['study_year'] ?></p><p><?= e($ambassador['bio']) ?></p><div class="tag-row"><?php foreach (array_slice(explode(',', $ambassador['interests']), 0, 2) as $interest): ?><span><?= e(trim($interest)) ?></span><?php endforeach; ?></div><a class="btn btn-card w-100 mt-3" href="index.php?page=ambassadors&ambassador=<?= (int) $ambassador['id'] ?>">Mở cuộc trò chuyện <i class="bi bi-arrow-up-right"></i></a></div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-space how-section" id="how-it-works">
    <div class="container">
        <div class="section-heading"><h2>Ba chuyển động để hiểu rõ lựa chọn.</h2></div>
        <div class="cmc-steps">
            <article><span>01</span><i class="bi bi-search"></i><h3>Chọn người phù hợp</h3><p>Lọc theo ngành, sở thích hoặc quê quán.</p></article>
            <article><span>02</span><i class="bi bi-chat-square-text"></i><h3>Đặt câu hỏi thật</h3><p>Nói rõ điều bạn còn băn khoăn, hoàn toàn miễn phí.</p></article>
            <article><span>03</span><i class="bi bi-compass"></i><h3>Tự tin quyết định</h3><p>Đối chiếu góc nhìn thực tế với mong muốn của bạn.</p></article>
        </div>
    </div>
</section>

<section class="cta-section"><div class="container"><div class="cta-card"><div><h2>Câu trả lời gần hơn bạn nghĩ.</h2><p>Bắt đầu bằng một câu hỏi. Phần còn lại là một cuộc trò chuyện thật.</p></div><a class="btn btn-light btn-lg" href="index.php?page=ambassadors">Gặp đại sứ <i class="bi bi-arrow-right"></i></a></div></div></section>
