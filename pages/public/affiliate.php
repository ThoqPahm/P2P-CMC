<?php
$code = trim((string) ($_GET['ref'] ?? ''));
$link = rows('SELECT al.*, u.name AS student_name, c.title AS campaign_title FROM affiliate_links al JOIN users u ON u.id = al.user_id JOIN campaigns c ON c.id = al.campaign_id WHERE al.code = ?', [$code])[0] ?? null;
if ($link) {
    $statement = $db->prepare('UPDATE affiliate_links SET clicks = clicks + 1 WHERE id = ?');
    $statement->execute([$link['id']]);
}
$pageTitle = 'Đăng ký tư vấn';
?>
<section class="login-section"><div class="container"><div class="row justify-content-center"><div class="col-lg-7"><div class="lead-form-card">
    <?php if (!$link): ?><div class="empty-state"><i class="bi bi-link-45deg"></i><h2>Link giới thiệu không hợp lệ</h2><a class="btn btn-brand" href="index.php?page=home">Về trang chủ</a></div>
    <?php else: ?><span class="soft-label">ĐƯỢC GIỚI THIỆU BỞI <?= e(mb_strtoupper($link['student_name'])) ?></span><h1>Nhận tư vấn tuyển sinh CMC</h1><p>Chiến dịch: <strong><?= e($link['campaign_title']) ?></strong></p><form method="post" action="lead.php" class="row g-3 mt-3"><?= csrf_field() ?><input type="hidden" name="affiliate_id" value="<?= (int) $link['id'] ?>"><div class="col-md-6"><label class="form-label">Họ và tên</label><input class="form-control" name="full_name" required></div><div class="col-md-6"><label class="form-label">Số điện thoại</label><input class="form-control" name="phone" required></div><div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div><div class="col-md-6"><label class="form-label">Ngành quan tâm</label><select class="form-select" name="major"><option>Marketing</option><option>Công nghệ thông tin</option><option>Thiết kế đồ họa</option><option>Quản trị kinh doanh</option></select></div><div class="col-12"><button class="btn btn-brand btn-lg w-100">Gửi yêu cầu tư vấn</button></div></form><?php endif; ?>
</div></div></div></div></section>
