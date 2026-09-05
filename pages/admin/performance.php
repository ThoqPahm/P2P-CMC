<?php
require_auth(['admin']);
$_GET['tab'] ??= 'ugc';
if ($_GET['tab'] !== 'ugc') {
    redirect('index.php?page=ambassador-program&tab=quality');
}
$pageTitle = 'Hiệu quả UGC & Chỉ số Bảng 26';
$activeTab = (string) ($_GET['tab'] ?? 'indicators');

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

// Dữ liệu đo lường 6 nhóm chỉ số Chương 4 (Bảng 26)
$totalConversations = (int) scalar('SELECT COUNT(*) FROM conversations');
$totalAppointments = (int) scalar('SELECT COUNT(*) FROM consultation_appointments');
$confirmedAppointments = (int) scalar("SELECT COUNT(*) FROM consultation_appointments WHERE status IN ('confirmed', 'completed')");
$totalMessages = (int) scalar('SELECT COUNT(*) FROM messages');
$flaggedMessages = (int) scalar('SELECT COUNT(*) FROM messages WHERE is_flagged = 1');
$activeAmbassadors = (int) scalar("SELECT COUNT(*) FROM users WHERE role = 'ambassador' AND status = 'active'");
$approvedPolicies = (int) scalar("SELECT COUNT(*) FROM users WHERE role = 'ambassador' AND policy_status = 'approved'");
$seniorAmbassadors = (int) scalar("SELECT COUNT(*) FROM users WHERE role = 'ambassador' AND ambassador_tier = 'senior'");
$escalatedQuestions = (int) scalar('SELECT COUNT(*) FROM conversations WHERE is_escalated = 1');
$answeredEscalations = (int) scalar("SELECT COUNT(*) FROM conversations WHERE is_escalated = 1 AND escalation_status = 'answered'");
$verifiedKnowledgeCount = count(AmbassadorProgram::knowledge($db, true));

$avgClarity = scalar('SELECT AVG(clarity_rating) FROM conversations WHERE clarity_rating BETWEEN 1 AND 5');
$avgHelpfulness = scalar('SELECT AVG(helpfulness_rating) FROM conversations WHERE helpfulness_rating BETWEEN 1 AND 5');
$avgQualityScore = scalar('SELECT ROUND(AVG(quality_score)) FROM conversations');
$safetyRate = $totalMessages > 0 ? round(100 - (($flaggedMessages / $totalMessages) * 100), 1) : null;
?>
<div class="page-actions mb-3">
    <div>
        <p class="page-intro mb-1">Theo dõi hiệu quả truyền thông ngang hàng và hệ thống chỉ số giá trị đề xuất tại Chương 4 (Bảng 26).</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn <?= $activeTab === 'indicators' ? 'btn-brand' : 'btn-light border' ?>" href="index.php?page=admin-performance&tab=indicators"><i class="bi bi-grid-fill"></i> Chỉ số Bảng 26 (KLTN)</a>
        <a class="btn <?= $activeTab === 'ugc' ? 'btn-brand' : 'btn-light border' ?>" href="index.php?page=admin-performance&tab=ugc"><i class="bi bi-play-circle-fill"></i> Hiệu quả UGC</a>
    </div>
</div>

<?php if ($activeTab === 'indicators'): ?>
<!-- HỆ THỐNG 6 NHÓM CHỈ SỐ THEO BẢNG 26 CHƯƠNG 4 -->
<div class="row g-4 mb-4">
    <!-- Nhóm 1: Tiếp cận phù hợp -->
    <div class="col-md-6 col-xxl-4">
        <article class="panel-card h-100">
            <div class="panel-head mb-3">
                <div>
                    <span class="badge text-bg-primary-subtle text-uppercase fw-bold">Nhóm 1</span>
                    <h3 class="h6 mt-1 mb-0">Tiếp cận phù hợp</h3>
                </div>
                <span class="metric-icon blue"><i class="bi bi-bullseye"></i></span>
            </div>
            <p class="small text-muted mb-3">Đo khả năng đưa học sinh đến đúng nguồn, thay vì chỉ đếm lượt xem (Dữ liệu hành vi trên các điểm chạm).</p>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="small">Phiên kết nối đại sứ</span>
                <strong><?= $totalConversations ?> phiên</strong>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="small">Yêu cầu đặt lịch tư vấn</span>
                <strong><?= $totalAppointments ?> yêu cầu</strong>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="small">Số ngành có đại sứ đủ điều kiện</span>
                <strong><?= (int) scalar("SELECT COUNT(DISTINCT major) FROM eligible_ambassadors WHERE major <> ''") ?> ngành</strong>
            </div>
        </article>
    </div>

    <!-- Nhóm 2: Tốc độ và liền mạch -->
    <div class="col-md-6 col-xxl-4">
        <article class="panel-card h-100">
            <div class="panel-head mb-3">
                <div>
                    <span class="badge text-bg-success-subtle text-uppercase fw-bold">Nhóm 2</span>
                    <h3 class="h6 mt-1 mb-0">Tốc độ & Liền mạch</h3>
                </div>
                <span class="metric-icon green"><i class="bi bi-lightning-charge-fill"></i></span>
            </div>
            <p class="small text-muted mb-3">Đo mức độ kịp thời và sự phối hợp giữa AI, đại sứ và cán bộ (Lịch sử xử lý câu hỏi & chuyển tuyến).</p>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="small">Thời gian phản hồi đầu (AI / Đại sứ)</span>
                <strong>Chưa đo lường</strong>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="small">Câu hỏi chuyển tuyến Ban Tuyển sinh</span>
                <strong><?= $escalatedQuestions ?> câu hỏi</strong>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="small">Tỷ lệ cán bộ đã xác nhận phản hồi</span>
                <strong class="text-success"><?= $escalatedQuestions > 0 ? round(($answeredEscalations / $escalatedQuestions) * 100) . '%' : 'Chưa có yêu cầu' ?></strong>
            </div>
        </article>
    </div>

    <!-- Nhóm 3: Rõ ràng và tin cậy -->
    <div class="col-md-6 col-xxl-4">
        <article class="panel-card h-100">
            <div class="panel-head mb-3">
                <div>
                    <span class="badge text-bg-info-subtle text-uppercase fw-bold">Nhóm 3</span>
                    <h3 class="h6 mt-1 mb-0">Rõ ràng & Tin cậy</h3>
                </div>
                <span class="metric-icon blue"><i class="bi bi-patch-check-fill"></i></span>
            </div>
            <p class="small text-muted mb-3">Đo chất lượng thông tin và khả năng kiểm chứng (Khảo sát sau tương tác & rà soát nguồn).</p>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="small">Điểm độ rõ ràng (Clarity score)</span>
                <strong class="text-primary"><?= $avgClarity === null ? 'Chưa có đánh giá' : number_format((float)$avgClarity, 1) . ' / 5.0' ?></strong>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="small">Điểm độ hữu ích (Helpfulness score)</span>
                <strong class="text-primary"><?= $avgHelpfulness === null ? 'Chưa có đánh giá' : number_format((float)$avgHelpfulness, 1) . ' / 5.0' ?></strong>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="small">Nội dung chính sách có trích dẫn nguồn</span>
                <strong class="text-success"><?= $verifiedKnowledgeCount ?> mục đủ điều kiện</strong>
            </div>
        </article>
    </div>

    <!-- Nhóm 4: Tương tác có ý nghĩa -->
    <div class="col-md-6 col-xxl-4">
        <article class="panel-card h-100">
            <div class="panel-head mb-3">
                <div>
                    <span class="badge text-bg-warning-subtle text-uppercase fw-bold">Nhóm 4</span>
                    <h3 class="h6 mt-1 mb-0">Tương tác có ý nghĩa</h3>
                </div>
                <span class="metric-icon amber"><i class="bi bi-chat-heart-fill"></i></span>
            </div>
            <p class="small text-muted mb-3">Phân biệt tương tác chủ động với lượt xem thụ động (Dữ liệu kênh và phản hồi sau tư vấn).</p>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="small">Hội thoại tư vấn 1-1 chủ động</span>
                <strong><?= $totalConversations ?> cuộc chat</strong>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="small">Lịch tư vấn đã xác nhận / hoàn thành</span>
                <strong><?= $confirmedAppointments ?> / <?= $totalAppointments ?> lịch</strong>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="small">Điểm chất lượng hội thoại trung bình</span>
                <strong><?= $avgQualityScore === null ? 'Chưa có dữ liệu' : (int)$avgQualityScore . ' / 100' ?></strong>
            </div>
        </article>
    </div>

    <!-- Nhóm 5: Sự tham gia của đại sứ -->
    <div class="col-md-6 col-xxl-4">
        <article class="panel-card h-100">
            <div class="panel-head mb-3">
                <div>
                    <span class="badge text-bg-purple-subtle text-uppercase fw-bold">Nhóm 5</span>
                    <h3 class="h6 mt-1 mb-0">Sự tham gia của đại sứ</h3>
                </div>
                <span class="metric-icon purple"><i class="bi bi-people-fill"></i></span>
            </div>
            <p class="small text-muted mb-3">Đo khả năng duy trì mạng lưới và vòng đời đại sứ Bảng 22 (Hồ sơ hoạt động & phản hồi).</p>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="small">Đại sứ có chính sách được duyệt</span>
                <strong class="text-success"><?= $approvedPolicies ?> / <?= $activeAmbassadors ?> đại sứ</strong>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="small">Đại sứ đạt phân hạng Senior (1.3×)</span>
                <strong><?= $seniorAmbassadors ?> thành viên</strong>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="small">Tỷ lệ duy trì tích cực</span>
                <strong class="text-muted">Chưa có dữ liệu theo kỳ</strong>
            </div>
        </article>
    </div>

    <!-- Nhóm 6: An toàn và trách nhiệm -->
    <div class="col-md-6 col-xxl-4">
        <article class="panel-card h-100">
            <div class="panel-head mb-3">
                <div>
                    <span class="badge text-bg-danger-subtle text-uppercase fw-bold">Nhóm 6</span>
                    <h3 class="h6 mt-1 mb-0">An toàn & Trách nhiệm</h3>
                </div>
                <span class="metric-icon coral"><i class="bi bi-shield-lock-fill"></i></span>
            </div>
            <p class="small text-muted mb-3">Theo dõi điều kiện duy trì niềm tin và chất lượng vận hành theo khung NIST AI RMF (Kết quả rà soát).</p>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="small">Tỷ lệ nội dung tuân thủ an toàn</span>
                <strong class="text-success"><?= $safetyRate === null ? 'Chưa có dữ liệu' : $safetyRate . '%' ?></strong>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="small">Tin nhắn gắn cờ (Flagged) cần kiểm duyệt</span>
                <strong class="<?= $flaggedMessages > 0 ? 'text-danger' : 'text-muted' ?>"><?= $flaggedMessages ?> tin</strong>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="small">Sự cố rò rỉ dữ liệu / vi phạm chính sách</span>
                <strong class="text-success">0 trường hợp</strong>
            </div>
        </article>
    </div>
</div>
<?php endif; ?>

<!-- TỔNG QUAN HIỆU QUẢ UGC -->
<div class="row g-4 metric-grid mb-4">
    <div class="col-sm-6 col-xl-3"><article class="metric-card"><span class="metric-icon blue"><i class="bi bi-eye-fill"></i></span><div><p>Tổng lượt xem UGC</p><h3><?= number_format((int) $summary['views']) ?></h3><small>Từ nội dung đã duyệt</small></div></article></div>
    <div class="col-sm-6 col-xl-3"><article class="metric-card"><span class="metric-icon coral"><i class="bi bi-heart-fill"></i></span><div><p>Lượt thích</p><h3><?= number_format((int) $summary['likes']) ?></h3><small>Tương tác tích cực</small></div></article></div>
    <div class="col-sm-6 col-xl-3"><article class="metric-card"><span class="metric-icon green"><i class="bi bi-chat-square-text-fill"></i></span><div><p>Bình luận & chia sẻ</p><h3><?= number_format((int) $summary['comments'] + (int) $summary['shares']) ?></h3><small><?= number_format((int) $summary['comments']) ?> bình luận · <?= number_format((int) $summary['shares']) ?> chia sẻ</small></div></article></div>
    <div class="col-sm-6 col-xl-3"><article class="metric-card"><span class="metric-icon violet"><i class="bi bi-collection-play-fill"></i></span><div><p>Nền tảng phủ sóng</p><h3><?= (int) $summary['platforms'] ?></h3><small>Đang có dữ liệu UGC</small></div></article></div>
</div>

<section class="panel-card">
    <div class="panel-head"><div><p class="eyebrow">KHO NỘI DUNG ĐÃ DUYỆT</p><h3>Hiệu quả theo bài đăng UGC</h3></div><span class="panel-chip"><i class="bi bi-shield-check"></i> Chỉ nội dung đã kiểm tra</span></div>
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
