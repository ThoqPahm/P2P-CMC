<?php
require_auth(['admin']);
$pageTitle = 'Điểm thưởng và phân hạng';
$members = rows(<<<'SQL'
    SELECT u.*,
        COALESCE((SELECT SUM(s.views) FROM submissions s WHERE s.user_id = u.id AND s.status = 'approved'), 0) AS views,
        COALESCE((SELECT ROUND(AVG(c.quality_score)) FROM conversations c WHERE c.ambassador_id = u.id), 0) AS chat_quality,
        COALESCE((SELECT SUM(CASE WHEN wt.type = 'credit' THEN wt.points ELSE -wt.points END) FROM wallet_transactions wt WHERE wt.user_id = u.id), 0) AS points
    FROM users u
    WHERE u.role IN ('student', 'ambassador')
    ORDER BY CASE u.ambassador_tier WHEN 'senior' THEN 1 ELSE 2 END, u.name
SQL);
$approved = (int) scalar("SELECT COUNT(*) FROM users WHERE role IN ('student','ambassador') AND policy_status = 'approved'");
$activeAmbassadors = (int) scalar("SELECT COUNT(*) FROM users WHERE role = 'ambassador' AND status = 'active'");
$rewardReview = rows('SELECT r.*,u.name,c.title,s.status FROM submission_reward_state r JOIN submissions s ON s.id=r.submission_id JOIN users u ON u.id=s.user_id JOIN campaigns c ON c.id=s.campaign_id WHERE r.legacy_review_required=1 ORDER BY r.submission_id');
$tierLabels = ['junior' => 'Junior', 'senior' => 'Senior'];
$policyLabels = ['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'suspended' => 'Tạm dừng'];
$violationLabels = ['none' => 'Không có', 'yellow' => 'Mức vàng', 'orange' => 'Mức cam', 'red' => 'Mức đỏ'];
$submissionStatusLabels = ['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Cần chỉnh sửa'];
?>
<?php if ($rewardReview): ?>
<section class="panel-card reward-review-card mb-4"><div><span class="metric-icon amber"><i class="bi bi-exclamation-circle"></i></span><div><h3>Điểm lịch sử cần đối soát</h3><p>Giữ nguyên số dư hiện tại trong khi kiểm tra để tránh cộng hoặc trừ điểm hai lần.</p></div></div><details><summary>Xem <?= count($rewardReview) ?> bài cần kiểm tra <i class="bi bi-chevron-down"></i></summary><div class="table-responsive"><table class="table"><thead><tr><th>Bài nộp</th><th>Thành viên</th><th>Chiến dịch</th><th>Trạng thái</th><th>Điểm đã ghi</th></tr></thead><tbody><?php foreach ($rewardReview as $review): ?><tr><td>#<?= (int)$review['submission_id'] ?></td><td><?= e($review['name']) ?></td><td><?= e($review['title']) ?></td><td><?= e($submissionStatusLabels[$review['status']] ?? $review['status']) ?></td><td><?= number_format((int)$review['awarded_points']) ?></td></tr><?php endforeach; ?></tbody></table></div></details></section>
<?php endif; ?>
<div class="reward-overview admin-reward-overview">
    <div><h2>Quy tắc tính điểm</h2><p>Điểm được ghi nhận khi bài nộp được duyệt và chỉ thay đổi khi quản trị viên duyệt lại kết quả.</p><ul class="reward-rule-list"><li>Thưởng thêm 40 điểm khi nội dung đạt một trong các ngưỡng hiệu quả.</li><li>Điểm chất lượng tư vấn được theo dõi riêng, chưa quy đổi thành điểm thưởng.</li></ul></div>
    <div class="reward-formula" aria-label="Công thức tính điểm"><span>Điểm chiến dịch + thưởng thêm</span><i class="bi bi-x-lg"></i><strong>Hệ số cấp độ</strong></div>
</div>

<div class="row g-4 metric-grid mb-4">
    <div class="col-md-4"><article class="metric-card"><span class="metric-icon blue"><i class="bi bi-people-fill"></i></span><div><p>Đại sứ hoạt động</p><h3><?= $activeAmbassadors ?></h3><small>Có quyền tư vấn trong hộp thư</small></div></article></div>
    <div class="col-md-4"><article class="metric-card"><span class="metric-icon green"><i class="bi bi-file-earmark-check-fill"></i></span><div><p>Đã duyệt chính sách</p><h3><?= $approved ?></h3><small>Hồ sơ đủ điều kiện</small></div></article></div>
    <div class="col-md-4"><article class="metric-card"><span class="metric-icon violet"><i class="bi bi-trophy-fill"></i></span><div><p>Lượt xem toàn đội</p><h3><?= number_format((int) scalar("SELECT COALESCE(SUM(views),0) FROM submissions WHERE status = 'approved'")) ?></h3><small>Từ UGC đã được duyệt</small></div></article></div>
</div>

<section class="panel-card admin-data-panel">
    <div class="panel-head"><div><h3>Phân hạng thành viên</h3><p>Kiểm tra điều kiện, hiệu quả hoạt động và trạng thái tuân thủ.</p></div><span class="panel-chip">Junior 1.0× · Senior 1.3×</span></div>
    <div class="table-responsive"><table class="table clean-table reward-table admin-analytics-table align-middle"><thead><tr><th>Thành viên</th><th>Điều kiện</th><th>Hiệu quả</th><th>Cấp độ</th><th>Chính sách</th><th>Vi phạm</th><th><span class="visually-hidden">Thao tác</span></th></tr></thead><tbody>
        <?php foreach ($members as $member): ?>
        <tr>
            <td><div class="person-cell"><span class="avatar avatar-sm"><?= e(initials($member['name'])) ?></span><div><strong><?= e($member['name']) ?></strong><small><?= e($member['student_code']) ?> · <?= e(role_label($member['role'])) ?></small></div></div></td>
            <td><strong>GPA <?= number_format((float) $member['gpa'], 2) ?></strong><small class="d-block text-muted"><?= number_format((int) $member['followers']) ?> người theo dõi</small></td>
            <td><strong><?= number_format((int) $member['views']) ?> lượt xem</strong><small class="d-block text-muted">Tư vấn <?= (int) $member['chat_quality'] ?>/100 · <?= number_format((int) $member['points']) ?> điểm</small></td>
            <td><span class="tier-badge tier-<?= e($member['ambassador_tier']) ?>"><?= e($tierLabels[$member['ambassador_tier']] ?? $member['ambassador_tier']) ?></span></td>
            <td><span class="policy-status policy-<?= e($member['policy_status']) ?>"><?= e($policyLabels[$member['policy_status']] ?? $member['policy_status']) ?></span></td>
            <td><span class="violation violation-<?= e($member['violation_level']) ?>"><?= e($violationLabels[$member['violation_level']] ?? $member['violation_level']) ?></span></td>
            <td><button class="btn btn-sm btn-light border" type="button" data-bs-toggle="modal" data-bs-target="#profile<?= (int) $member['id'] ?>">Cập nhật</button></td>
        </tr>
        <?php endforeach; ?>
    </tbody></table></div>
</section>
<?php foreach ($members as $member): ?>
<div class="modal fade" id="profile<?= (int) $member['id'] ?>" tabindex="-1" aria-labelledby="profileTitle<?= (int) $member['id'] ?>" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="actions.php?action=update_ambassador_profile"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>"><div class="modal-header"><div><p class="eyebrow mb-1">HỒ SƠ ĐẠI SỨ</p><h2 class="modal-title fs-5" id="profileTitle<?= (int) $member['id'] ?>"><?= e($member['name']) ?></h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><div class="row g-3"><div class="col-sm-6"><label class="form-label">GPA</label><input class="form-control" type="number" name="gpa" min="0" max="4" step="0.01" value="<?= e((string) $member['gpa']) ?>"></div><div class="col-sm-6"><label class="form-label">Người theo dõi</label><input class="form-control" type="number" name="followers" min="0" value="<?= (int) $member['followers'] ?>"></div><div class="col-sm-4"><label class="form-label">Cấp độ</label><select class="form-select" name="ambassador_tier"><?php foreach (['junior','senior'] as $tier): ?><option value="<?= $tier ?>" <?= $member['ambassador_tier'] === $tier ? 'selected' : '' ?>><?= e($tierLabels[$tier]) ?></option><?php endforeach; ?></select></div><div class="col-sm-4"><label class="form-label">Chính sách</label><select class="form-select" name="policy_status"><?php foreach (['pending','approved','suspended'] as $policy): ?><option value="<?= $policy ?>" <?= $member['policy_status'] === $policy ? 'selected' : '' ?>><?= e($policyLabels[$policy]) ?></option><?php endforeach; ?></select></div><div class="col-sm-4"><label class="form-label">Vi phạm</label><select class="form-select" name="violation_level"><?php foreach (['none','yellow','orange','red'] as $level): ?><option value="<?= $level ?>" <?= $member['violation_level'] === $level ? 'selected' : '' ?>><?= e($violationLabels[$level]) ?></option><?php endforeach; ?></select></div></div><div class="eligibility-note"><i class="bi bi-info-circle"></i><span>Tiêu chí gợi ý: GPA đạt yêu cầu, tối thiểu 500 người theo dõi, không có kỷ luật và đã xác nhận chính sách.</span></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-brand" type="submit">Lưu hồ sơ</button></div></form></div></div></div>
<?php endforeach; ?>
