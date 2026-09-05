<?php
require_auth(['admin']);
$_GET['tab'] ??= 'ugc';
if ($_GET['tab'] !== 'ugc') {
    redirect('index.php?page=ambassador-program&tab=quality');
}
$pageTitle = 'Hiệu quả UGC';
$summary = rows(<<<'SQL'
    SELECT COALESCE(SUM(views), 0) AS views,
           COALESCE(SUM(likes), 0) AS likes,
           COALESCE(SUM(comments), 0) AS comments,
           COALESCE(SUM(shares), 0) AS shares,
           COUNT(DISTINCT platform) AS platforms
    FROM submissions
    WHERE status = 'approved'
SQL)[0];
$submissions = rows(<<<'SQL'
    SELECT s.*, u.name AS student_name, u.student_code, c.title AS campaign_title
    FROM submissions s
    JOIN users u ON u.id = s.user_id
    JOIN campaigns c ON c.id = s.campaign_id
    WHERE s.status = 'approved'
    ORDER BY s.views DESC, s.likes DESC, s.id DESC
SQL);
?>
<div class="page-actions">
    <p class="page-intro">Tổng hợp hiệu quả nội dung của sinh viên và đại sứ ở một nơi, theo từng nền tảng.</p>
    <a class="btn btn-brand" href="index.php?page=admin-submissions"><i class="bi bi-check2-square"></i> Duyệt bài nộp</a>
</div>
<div class="row g-4 metric-grid mb-4">
    <div class="col-sm-6 col-xl-3"><article class="metric-card"><span class="metric-icon blue"><i class="bi bi-eye-fill"></i></span><div><p>Tổng lượt xem</p><h3><?= number_format((int) $summary['views']) ?></h3><small>Từ nội dung đã duyệt</small></div></article></div>
    <div class="col-sm-6 col-xl-3"><article class="metric-card"><span class="metric-icon coral"><i class="bi bi-heart-fill"></i></span><div><p>Lượt thích</p><h3><?= number_format((int) $summary['likes']) ?></h3><small>Tương tác tích cực</small></div></article></div>
    <div class="col-sm-6 col-xl-3"><article class="metric-card"><span class="metric-icon green"><i class="bi bi-chat-square-text-fill"></i></span><div><p>Bình luận & chia sẻ</p><h3><?= number_format((int) $summary['comments'] + (int) $summary['shares']) ?></h3><small><?= number_format((int) $summary['comments']) ?> bình luận · <?= number_format((int) $summary['shares']) ?> chia sẻ</small></div></article></div>
    <div class="col-sm-6 col-xl-3"><article class="metric-card"><span class="metric-icon violet"><i class="bi bi-collection-play-fill"></i></span><div><p>Nền tảng</p><h3><?= (int) $summary['platforms'] ?></h3><small>Đang có dữ liệu</small></div></article></div>
</div>
<section class="panel-card">
    <div class="panel-head"><div><p class="eyebrow">KHO NỘI DUNG ĐÃ DUYỆT</p><h3>Hiệu quả theo bài đăng</h3></div><span class="panel-chip"><i class="bi bi-shield-check"></i> Chỉ nội dung đã kiểm tra</span></div>
    <div class="table-responsive"><table class="table clean-table align-middle"><thead><tr><th>Sinh viên</th><th>Nội dung</th><th>Nền tảng</th><th>Views</th><th>Likes</th><th>Tương tác</th><th></th></tr></thead><tbody>
        <?php foreach ($submissions as $submission): ?>
            <tr><td><div class="person-cell"><span class="avatar avatar-sm"><?= e(initials($submission['student_name'])) ?></span><div><strong><?= e($submission['student_name']) ?></strong><small><?= e($submission['student_code']) ?></small></div></div></td><td><a class="content-link" href="<?= e($submission['content_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-play-circle-fill"></i><span><?= e($submission['campaign_title']) ?></span></a></td><td><span class="badge text-bg-primary-subtle"><?= e($submission['platform']) ?></span></td><td><strong><?= number_format((int) $submission['views']) ?></strong></td><td><strong><?= number_format((int) $submission['likes']) ?></strong></td><td><small><?= number_format((int) $submission['comments']) ?> bình luận · <?= number_format((int) $submission['shares']) ?> chia sẻ</small></td><td><button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#metrics<?= (int) $submission['id'] ?>">Cập nhật</button></td></tr>
        <?php endforeach; ?>
        <?php if (!$submissions): ?><tr><td colspan="7" class="text-center text-muted py-4">Chưa có nội dung được duyệt để tổng hợp.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php foreach ($submissions as $submission): ?>
    <div class="modal fade" id="metrics<?= (int) $submission['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="actions.php?action=update_content_metrics"><?= csrf_field() ?><input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>"><div class="modal-header"><div><p class="eyebrow mb-1">CHỈ SỐ NỘI DUNG</p><h2 class="modal-title fs-5"><?= e($submission['campaign_title']) ?></h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><div class="review-metrics"><div><label class="form-label">Lượt xem</label><input class="form-control" type="number" name="views" min="0" value="<?= (int) $submission['views'] ?>"></div><div><label class="form-label">Lượt thích</label><input class="form-control" type="number" name="likes" min="0" value="<?= (int) $submission['likes'] ?>"></div><div><label class="form-label">Bình luận</label><input class="form-control" type="number" name="comments" min="0" value="<?= (int) $submission['comments'] ?>"></div><div><label class="form-label">Chia sẻ</label><input class="form-control" type="number" name="shares" min="0" value="<?= (int) $submission['shares'] ?>"></div></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-brand" type="submit">Lưu chỉ số</button></div></form></div></div></div>
<?php endforeach; ?>
