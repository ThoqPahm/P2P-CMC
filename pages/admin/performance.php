<?php
require_auth(['admin']);
$pageTitle = 'Hiệu quả nội dung UGC';

// Dữ liệu hiệu quả nội dung UGC
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
<div class="page-actions admin-analytics-intro">
    <p class="page-intro">Theo dõi lượt tiếp cận và tương tác của các nội dung đã được duyệt.</p>
    <a class="btn btn-light border" href="index.php?page=ambassador-program&tab=quality"><i class="bi bi-chat-square-text"></i> Xem chất lượng tư vấn</a>
</div>

<!-- TỔNG QUAN HIỆU QUẢ UGC -->
<div class="row g-4 metric-grid mb-4">
    <div class="col-sm-6 col-xl-3"><article class="metric-card"><span class="metric-icon blue"><i class="bi bi-eye-fill"></i></span><div><p>Tổng lượt xem UGC</p><h3><?= number_format((int) $summary['views']) ?></h3><small>Từ nội dung đã duyệt</small></div></article></div>
    <div class="col-sm-6 col-xl-3"><article class="metric-card"><span class="metric-icon coral"><i class="bi bi-heart-fill"></i></span><div><p>Lượt thích</p><h3><?= number_format((int) $summary['likes']) ?></h3><small>Tương tác tích cực</small></div></article></div>
    <div class="col-sm-6 col-xl-3"><article class="metric-card"><span class="metric-icon green"><i class="bi bi-chat-square-text-fill"></i></span><div><p>Bình luận & chia sẻ</p><h3><?= number_format((int) $summary['comments'] + (int) $summary['shares']) ?></h3><small><?= number_format((int) $summary['comments']) ?> bình luận · <?= number_format((int) $summary['shares']) ?> chia sẻ</small></div></article></div>
    <div class="col-sm-6 col-xl-3"><article class="metric-card"><span class="metric-icon violet"><i class="bi bi-collection-play-fill"></i></span><div><p>Nền tảng phủ sóng</p><h3><?= (int) $summary['platforms'] ?></h3><small>Đang có dữ liệu UGC</small></div></article></div>
</div>

<section class="panel-card admin-data-panel">
    <div class="panel-head"><div><h3>Hiệu quả theo nội dung</h3><p>Nội dung được sắp xếp theo lượt xem cao nhất.</p></div><span class="panel-chip"><i class="bi bi-shield-check"></i> Nội dung đã duyệt</span></div>
    <div class="table-responsive"><table class="table clean-table admin-analytics-table align-middle"><thead><tr><th>Sinh viên</th><th>Nội dung</th><th>Nền tảng</th><th>Lượt xem</th><th>Lượt thích</th><th>Tương tác khác</th><th><span class="visually-hidden">Thao tác</span></th></tr></thead><tbody>
        <?php foreach ($submissions as $submission): ?>
            <tr><td><div class="person-cell"><span class="avatar avatar-sm"><?= e(initials($submission['student_name'])) ?></span><div><strong><?= e($submission['student_name']) ?></strong><small><?= e($submission['student_code']) ?></small></div></div></td><td><a class="content-link" href="<?= e($submission['content_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-play-circle-fill"></i><span><?= e($submission['campaign_title']) ?></span></a></td><td><span class="badge text-bg-primary-subtle"><?= e($submission['platform']) ?></span></td><td><strong><?= number_format((int) $submission['views']) ?></strong></td><td><strong><?= number_format((int) $submission['likes']) ?></strong></td><td><small><?= number_format((int) $submission['comments']) ?> bình luận · <?= number_format((int) $submission['shares']) ?> chia sẻ</small></td><td><button class="btn btn-sm btn-light border" type="button" data-bs-toggle="modal" data-bs-target="#metrics<?= (int) $submission['id'] ?>">Cập nhật</button></td></tr>
        <?php endforeach; ?>
        <?php if (!$submissions): ?><tr><td colspan="7" class="text-center text-muted py-4">Chưa có nội dung được duyệt để tổng hợp.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php foreach ($submissions as $submission): ?>
    <div class="modal fade" id="metrics<?= (int) $submission['id'] ?>" tabindex="-1" aria-labelledby="metricsTitle<?= (int) $submission['id'] ?>" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="actions.php?action=update_content_metrics"><?= csrf_field() ?><input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>"><div class="modal-header"><div><p class="eyebrow mb-1">CHỈ SỐ NỘI DUNG</p><h2 class="modal-title fs-5" id="metricsTitle<?= (int) $submission['id'] ?>"><?= e($submission['campaign_title']) ?></h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><div class="review-metrics"><div><label class="form-label">Lượt xem</label><input class="form-control" type="number" name="views" min="0" value="<?= (int) $submission['views'] ?>"></div><div><label class="form-label">Lượt thích</label><input class="form-control" type="number" name="likes" min="0" value="<?= (int) $submission['likes'] ?>"></div><div><label class="form-label">Bình luận</label><input class="form-control" type="number" name="comments" min="0" value="<?= (int) $submission['comments'] ?>"></div><div><label class="form-label">Chia sẻ</label><input class="form-control" type="number" name="shares" min="0" value="<?= (int) $submission['shares'] ?>"></div></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-brand" type="submit">Lưu chỉ số</button></div></form></div></div></div>
<?php endforeach; ?>
