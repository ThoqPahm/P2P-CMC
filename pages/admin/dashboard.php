<?php
require_auth(['admin']);
$pageTitle = 'Tổng quan hệ sinh thái';
$campaignCount = (int) scalar("SELECT COUNT(*) FROM campaigns WHERE status = 'active'");
$submissionCount = (int) scalar('SELECT COUNT(*) FROM submissions');
$pendingSubmissions = (int) scalar("SELECT COUNT(*) FROM submissions WHERE status = 'pending'");
$approvedSubmissions = (int) scalar("SELECT COUNT(*) FROM submissions WHERE status = 'approved'");
$leadCount = (int) scalar('SELECT COUNT(*) FROM leads');
$qualifiedLeads = (int) scalar("SELECT COUNT(*) FROM leads WHERE status = 'qualified'");
$ambassadorCount = (int) scalar("SELECT COUNT(*) FROM users WHERE role = 'ambassador' AND status = 'active'");
$conversationCount = (int) scalar('SELECT COUNT(*) FROM conversations');
$recentSubmissions = rows('SELECT s.*, u.name AS student_name, c.title AS campaign_title FROM submissions s JOIN users u ON u.id = s.user_id JOIN campaigns c ON c.id = s.campaign_id ORDER BY s.id DESC LIMIT 5');
$topAmbassadors = rows(<<<'SQL'
    SELECT u.name, u.major, u.is_online, COALESCE(c.chats,0) AS chats, COALESCE(al.leads,0) AS leads
    FROM users u
    LEFT JOIN (
        SELECT ambassador_id, COUNT(*) AS chats
        FROM conversations
        GROUP BY ambassador_id
    ) c ON c.ambassador_id = u.id
    LEFT JOIN (
        SELECT user_id, SUM(leads) AS leads
        FROM affiliate_links
        GROUP BY user_id
    ) al ON al.user_id = u.id
    WHERE u.role = 'ambassador'
    ORDER BY leads DESC, chats DESC
    LIMIT 4
SQL);
?>

<div class="route-dashboard route-dashboard-admin">
    <section class="journey-board operations-board" aria-labelledby="operationsTitle">
        <header class="route-board-head">
            <div><h2 id="operationsTitle">Bản đồ vận hành hôm nay</h2><p><?= date('d/m/Y') ?> · Theo dõi luồng công việc và điểm bàn giao</p></div>
            <a class="btn btn-brand" href="index.php?page=admin-campaigns">Tạo chiến dịch <i class="bi bi-plus-lg"></i></a>
        </header>

        <div class="operations-summary" aria-label="Tổng quan vận hành">
            <div><span class="route-item-icon"><i class="bi bi-megaphone"></i></span><p><small>Chiến dịch đang chạy</small><strong><?= number_format($campaignCount) ?></strong></p></div>
            <div><span class="route-item-icon"><i class="bi bi-camera-video"></i></span><p><small>Bài nộp UGC</small><strong><?= number_format($submissionCount) ?></strong></p></div>
            <div><span class="route-item-icon"><i class="bi bi-person-lines-fill"></i></span><p><small>Leads đã ghi nhận</small><strong><?= number_format($leadCount) ?></strong></p></div>
            <div><span class="route-item-icon"><i class="bi bi-people"></i></span><p><small>Đại sứ hoạt động</small><strong><?= number_format($ambassadorCount) ?></strong></p></div>
        </div>

        <div class="ops-map" aria-label="Luồng vận hành">
            <article class="ops-route-row">
                <header><span class="route-item-icon"><i class="bi bi-megaphone"></i></span><div><strong>Chiến dịch</strong><small>Điều phối brief</small></div></header>
                <div class="ops-route-flow"><span class="ops-node is-complete">Khởi tạo <b><?= $campaignCount + 1 ?></b></span><i class="bi bi-arrow-right"></i><span class="ops-node is-active">Đang chạy <b><?= $campaignCount ?></b></span><i class="bi bi-arrow-right"></i><span class="ops-node">Tổng kết <b>0</b></span></div>
                <a href="index.php?page=admin-campaigns" aria-label="Quản lý chiến dịch"><i class="bi bi-chevron-right"></i></a>
            </article>
            <article class="ops-route-row">
                <header><span class="route-item-icon"><i class="bi bi-camera-video"></i></span><div><strong>Bài nộp UGC</strong><small>Kiểm tra nội dung</small></div></header>
                <div class="ops-route-flow"><span class="ops-node is-complete">Đã nộp <b><?= $submissionCount ?></b></span><i class="bi bi-arrow-right"></i><span class="ops-node is-active">Chờ duyệt <b><?= $pendingSubmissions ?></b></span><i class="bi bi-arrow-right"></i><span class="ops-node">Đã duyệt <b><?= $approvedSubmissions ?></b></span></div>
                <a href="index.php?page=admin-submissions" aria-label="Duyệt bài UGC"><i class="bi bi-chevron-right"></i></a>
            </article>
            <article class="ops-route-row">
                <header><span class="route-item-icon"><i class="bi bi-person-check"></i></span><div><strong>Leads</strong><small>Xác minh và chuyển đổi</small></div></header>
                <div class="ops-route-flow"><span class="ops-node is-complete">Đã ghi nhận <b><?= $leadCount ?></b></span><i class="bi bi-arrow-right"></i><span class="ops-node is-active">Cần xác minh <b><?= max(0, $leadCount - $qualifiedLeads) ?></b></span><i class="bi bi-arrow-right"></i><span class="ops-node">Đạt chuẩn <b><?= $qualifiedLeads ?></b></span></div>
                <a href="index.php?page=admin-leads" aria-label="Quản lý leads"><i class="bi bi-chevron-right"></i></a>
            </article>
            <article class="ops-route-row">
                <header><span class="route-item-icon"><i class="bi bi-chat-dots"></i></span><div><strong>Đại sứ P2P</strong><small>Hội thoại và chất lượng</small></div></header>
                <div class="ops-route-flow"><span class="ops-node is-complete">Hoạt động <b><?= $ambassadorCount ?></b></span><i class="bi bi-arrow-right"></i><span class="ops-node is-active">Hội thoại <b><?= $conversationCount ?></b></span><i class="bi bi-arrow-right"></i><span class="ops-node">Kiểm duyệt <b><?= (int) scalar('SELECT COUNT(*) FROM messages WHERE is_flagged = 1') ?></b></span></div>
                <a href="index.php?page=admin-moderation" aria-label="Kiểm duyệt hội thoại"><i class="bi bi-chevron-right"></i></a>
            </article>
        </div>

        <section class="route-table-block" aria-labelledby="queueTitle">
            <div class="section-title-row"><div><h3 id="queueTitle">Hàng đợi công việc</h3><p>Bài nộp mới nhất cần kiểm tra hoặc phản hồi.</p></div><a href="index.php?page=admin-submissions">Mở hàng đợi</a></div>
            <div class="table-responsive"><table class="table clean-table operations-table"><thead><tr><th>Sinh viên</th><th>Chiến dịch</th><th>Trạng thái</th><th>Ngày nộp</th><th></th></tr></thead><tbody><?php foreach ($recentSubmissions as $submission): ?><tr><td><div class="person-cell"><span class="avatar avatar-xs"><?= e(initials($submission['student_name'])) ?></span><strong><?= e($submission['student_name']) ?></strong></div></td><td><?= e($submission['campaign_title']) ?></td><td><span class="status-label status-<?= $submission['status'] === 'approved' ? 'success' : 'warning' ?>"><?= e(ucfirst($submission['status'])) ?></span></td><td><?= date('d/m/Y', strtotime($submission['submitted_at'])) ?></td><td><a class="table-action" href="index.php?page=admin-submissions"><i class="bi bi-arrow-up-right"></i></a></td></tr><?php endforeach; ?></tbody></table></div>
        </section>
    </section>

    <aside class="status-rail" aria-label="Ưu tiên vận hành">
        <section class="rail-section rail-summary">
            <div class="rail-heading"><h2>Ưu tiên hôm nay</h2><span class="status-label status-warning">Cần xử lý</span></div>
            <div class="priority-number"><strong><?= $pendingSubmissions + max(0, $leadCount - $qualifiedLeads) ?></strong><span>mục đang chờ</span></div>
            <div class="rail-list"><p><span>UGC chờ duyệt</span><strong><?= $pendingSubmissions ?></strong></p><p><span>Leads cần xác minh</span><strong><?= max(0, $leadCount - $qualifiedLeads) ?></strong></p><p><span>Hội thoại cần kiểm tra</span><strong><?= (int) scalar('SELECT COUNT(*) FROM messages WHERE is_flagged = 1') ?></strong></p></div>
            <a class="btn btn-brand w-100" href="index.php?page=admin-submissions">Xử lý ngay</a>
        </section>

        <section class="rail-section">
            <div class="rail-heading"><h2>Đại sứ nổi bật</h2><a href="index.php?page=admin-ambassadors">Quản lý</a></div>
            <div class="ranking-list compact-ranking"><?php foreach ($topAmbassadors as $i => $ambassador): ?><div class="ranking-item"><b><?= $i + 1 ?></b><span class="avatar avatar-sm"><?= e(initials($ambassador['name'])) ?></span><div><strong><?= e($ambassador['name']) ?></strong><small><?= e($ambassador['major']) ?></small></div><p><strong><?= (int) $ambassador['leads'] ?></strong><small>leads</small></p></div><?php endforeach; ?></div>
        </section>

        <section class="rail-section">
            <div class="rail-heading"><h2>Tín hiệu hệ thống</h2><span class="status-label status-success">Ổn định</span></div>
            <div class="rail-list"><p><span>Cuộc trò chuyện</span><strong><?= $conversationCount ?></strong></p><p><span>Lead đạt chuẩn</span><strong><?= $qualifiedLeads ?></strong></p><p><span>Tỷ lệ xác minh</span><strong><?= $leadCount ? number_format(($qualifiedLeads / $leadCount) * 100, 0) : 0 ?>%</strong></p></div>
        </section>
    </aside>
</div>
