<?php
require_auth(['student', 'ambassador']);
$current = user();
$nameParts = explode(' ', $current['name']);
$pageTitle = 'Chào ' . end($nameParts) . '!';
$balance = (int) scalar("SELECT COALESCE(SUM(CASE WHEN type='credit' THEN points ELSE -points END),0) FROM wallet_transactions WHERE user_id = ?", [$current['id']]);
$submissionCount = (int) scalar('SELECT COUNT(*) FROM submissions WHERE user_id = ?', [$current['id']]);
$campaigns = rows("SELECT * FROM campaigns WHERE status = 'active' AND deadline >= date('now', '+7 hours') ORDER BY deadline ASC LIMIT 3");
$userCampaignRows = rows(<<<'SQL'
    SELECT c.*, s.status AS submission_status, s.submitted_at
    FROM submissions s
    JOIN campaigns c ON c.id = s.campaign_id
    WHERE s.user_id = ? AND c.status = 'active' AND c.deadline >= date('now', '+7 hours')
    ORDER BY s.id DESC
    LIMIT 1
SQL, [$current['id']]);
$recommendedCampaignRows = rows(<<<'SQL'
    SELECT c.*
    FROM campaigns c
    WHERE c.status = 'active' AND c.deadline >= date('now', '+7 hours')
      AND NOT EXISTS (
          SELECT 1 FROM submissions s WHERE s.campaign_id = c.id AND s.user_id = ?
      )
    ORDER BY c.deadline ASC
    LIMIT 1
SQL, [$current['id']]);
$userCampaign = $userCampaignRows[0] ?? null;
$activeCampaign = $userCampaign ?? ($recommendedCampaignRows[0] ?? ($campaigns[0] ?? null));
$routeStatus = (string) ($userCampaign['submission_status'] ?? 'not_started');
$routeProgressByStatus = ['not_started' => 0, 'rejected' => 25, 'pending' => 75, 'approved' => 100];
$journeyProgress = $routeProgressByStatus[$routeStatus] ?? 0;
$completedMilestones = (int) floor($journeyProgress / 25);
$transactions = rows('SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$current['id']]);
$views = (int) scalar("SELECT COALESCE(SUM(views),0) FROM submissions WHERE user_id = ? AND status = 'approved'", [$current['id']]);
$likes = (int) scalar("SELECT COALESCE(SUM(likes),0) FROM submissions WHERE user_id = ? AND status = 'approved'", [$current['id']]);
$engagements = (int) scalar("SELECT COALESCE(SUM(likes + comments + shares),0) FROM submissions WHERE user_id = ? AND status = 'approved'", [$current['id']]);
$engagementRate = $views > 0 ? ($engagements / $views) * 100 : 0;
$leaderboard = rows(<<<'SQL'
    SELECT u.id, u.name, u.ambassador_tier, COALESCE(w.points,0) AS points, COALESCE(a.views,0) AS views
    FROM users u
    LEFT JOIN (
        SELECT user_id, SUM(points) AS points
        FROM wallet_transactions
        WHERE type = 'credit'
        GROUP BY user_id
    ) w ON w.user_id = u.id
    LEFT JOIN (
        SELECT user_id, SUM(views) AS views
        FROM submissions
        WHERE status = 'approved'
        GROUP BY user_id
    ) a ON a.user_id = u.id
    WHERE u.role IN ('student','ambassador')
    ORDER BY points DESC, views DESC
    LIMIT 5
SQL);
?>

<div class="route-dashboard route-dashboard-student">
    <section class="journey-board" aria-labelledby="journeyTitle">
        <header class="route-board-head">
            <div><h2 id="journeyTitle">Lộ trình hôm nay</h2><p><?= date('d/m/Y') ?> · Ưu tiên một nhiệm vụ quan trọng</p></div>
            <a class="btn btn-brand" href="index.php?page=campaigns">Tìm nhiệm vụ <i class="bi bi-arrow-right"></i></a>
        </header>

        <div class="route-progress" aria-label="Tiến độ hành trình <?= $journeyProgress ?> phần trăm">
            <span style="--progress: <?= $journeyProgress ?>%"></span>
            <p><strong><?= $journeyProgress ?>%</strong> · <?= $completedMilestones ?>/4 mốc hoạt động đã hoàn thành</p>
        </div>

        <div class="route-track">
            <article class="route-stop <?= $routeStatus === 'not_started' ? 'is-active' : 'is-complete' ?>">
                <span class="route-marker"><i class="bi <?= $routeStatus === 'not_started' ? 'bi-compass' : 'bi-check-lg' ?>"></i></span>
                <div class="route-stop-copy">
                    <small><?= $routeStatus === 'not_started' ? 'Bước hiện tại' : 'Đã hoàn thành' ?></small>
                    <h3><?= $routeStatus === 'not_started' ? 'Chọn nhiệm vụ phù hợp' : 'Nhiệm vụ đã được chọn' ?></h3>
                    <p><?= $routeStatus === 'not_started' ? e($activeCampaign['title'] ?? 'Khám phá brief đang mở') : 'Brief và yêu cầu đã được kiểm tra.' ?></p>
                </div>
                <?php if ($routeStatus === 'not_started'): ?>
                    <a class="btn btn-brand" href="index.php?page=campaigns<?= $activeCampaign ? '&campaign=' . (int) $activeCampaign['id'] : '' ?>">Xem brief <i class="bi bi-arrow-up-right"></i></a>
                <?php else: ?>
                    <span class="status-label status-success">Đã xác minh</span>
                <?php endif; ?>
            </article>
            <article class="route-stop <?= $routeStatus === 'rejected' ? 'is-active' : (in_array($routeStatus, ['pending', 'approved'], true) ? 'is-complete' : 'is-next') ?>">
                <span class="route-marker"><i class="bi <?= in_array($routeStatus, ['pending', 'approved'], true) ? 'bi-check-lg' : 'bi-camera-reels' ?>"></i></span>
                <div class="route-stop-copy">
                    <small><?= $routeStatus === 'rejected' ? 'Cần chỉnh sửa' : (in_array($routeStatus, ['pending', 'approved'], true) ? 'Đã hoàn thành' : 'Bước tiếp theo') ?></small>
                    <h3><?= e($activeCampaign['title'] ?? 'Khám phá nhiệm vụ mới') ?></h3>
                    <p><?= $routeStatus === 'rejected' ? 'Nội dung cần được cập nhật theo phản hồi trước khi gửi lại.' : e($activeCampaign['description'] ?? 'Chọn một brief để bắt đầu hành trình nội dung của bạn.') ?></p>
                    <?php if ($activeCampaign): ?><span class="route-meta"><i class="bi bi-clock"></i> Còn <?= max(0, (int) ((strtotime($activeCampaign['deadline']) - time()) / 86400)) ?> ngày · +<?= (int) $activeCampaign['reward_points'] ?> điểm</span><?php endif; ?>
                </div>
                <?php if ($routeStatus === 'rejected'): ?>
                    <a class="btn btn-brand" href="index.php?page=campaigns<?= $activeCampaign ? '&campaign=' . (int) $activeCampaign['id'] : '' ?>">Chỉnh sửa <i class="bi bi-arrow-up-right"></i></a>
                <?php elseif (in_array($routeStatus, ['pending', 'approved'], true)): ?>
                    <span class="status-label status-success">Đã nộp</span>
                <?php endif; ?>
            </article>
            <article class="route-stop <?= $routeStatus === 'pending' ? 'is-active' : ($routeStatus === 'approved' ? 'is-complete' : 'is-locked') ?>">
                <span class="route-marker"><i class="bi <?= $routeStatus === 'approved' ? 'bi-check-lg' : 'bi-upload' ?>"></i></span>
                <div class="route-stop-copy">
                    <small><?= $routeStatus === 'pending' ? 'Đang xử lý' : ($routeStatus === 'approved' ? 'Đã hoàn thành' : 'Chưa mở khóa') ?></small>
                    <h3><?= $routeStatus === 'pending' ? 'Nội dung đang chờ duyệt' : ($routeStatus === 'approved' ? 'Nội dung đã được duyệt' : 'Gửi nội dung để duyệt') ?></h3>
                    <p><?= $routeStatus === 'pending' ? 'CMC đang kiểm tra nội dung và sẽ phản hồi trên bài nộp.' : 'Hệ thống lưu trạng thái và phản hồi ngay trên bài nộp.' ?></p>
                </div>
                <?php if ($routeStatus === 'pending'): ?><a class="text-link" href="index.php?page=my-submissions">Xem bài nộp</a><?php endif; ?>
            </article>
            <article class="route-stop <?= $routeStatus === 'approved' ? 'is-complete' : 'is-locked' ?>">
                <span class="route-marker"><i class="bi <?= $routeStatus === 'approved' ? 'bi-check-lg' : 'bi-gift' ?>"></i></span>
                <div class="route-stop-copy"><small><?= $routeStatus === 'approved' ? 'Đã hoàn tất' : 'Kết quả' ?></small><h3>Nhận điểm và theo dõi hiệu quả</h3><p><?= $routeStatus === 'approved' ? 'Nội dung đã được xác minh; bạn có thể theo dõi chỉ số UGC theo nền tảng.' : 'Mở khóa sau khi nội dung của nhiệm vụ hiện tại được duyệt.' ?></p></div>
                <?php if ($routeStatus === 'approved'): ?><span class="status-label status-success">Đã ghi nhận</span><?php endif; ?>
            </article>
        </div>

        <section class="route-table-block" aria-labelledby="missionsTitle">
            <div class="section-title-row"><div><h3 id="missionsTitle">Nhiệm vụ đang chạy</h3><p>Brief phù hợp với vai trò và tiến độ của bạn.</p></div><a href="index.php?page=campaigns">Xem tất cả</a></div>
            <div class="route-table" role="table" aria-label="Nhiệm vụ đang chạy">
                <?php foreach ($campaigns as $campaign): ?>
                    <a class="route-table-row" role="row" href="index.php?page=campaigns&campaign=<?= (int) $campaign['id'] ?>">
                        <span class="route-item-icon"><i class="bi bi-megaphone"></i></span>
                        <span><small>Chiến dịch</small><strong><?= e($campaign['title']) ?></strong></span>
                        <span><small>Nền tảng</small><strong><?= e($campaign['platform']) ?></strong></span>
                        <span><small>Hạn hoàn thành</small><strong><?= date('d/m/Y', strtotime($campaign['deadline'])) ?></strong></span>
                        <span><small>Phần thưởng</small><strong>+<?= (int) $campaign['reward_points'] ?> điểm</strong></span>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endforeach; ?>
                <?php if (!$campaigns): ?><div class="empty-state compact"><h3>Chưa có nhiệm vụ mới</h3><p>Quay lại sau để xem brief tiếp theo.</p></div><?php endif; ?>
            </div>
        </section>
    </section>

    <aside class="status-rail" aria-label="Trạng thái của bạn">
        <section class="rail-section rail-summary">
            <div class="rail-heading"><h2>Tiến độ của bạn</h2><span><?= $journeyProgress ?>%</span></div>
            <div class="rail-progress"><span style="--progress: <?= $journeyProgress ?>%"></span></div>
            <dl class="rail-stats">
                <div><dt>Ví điểm</dt><dd><?= number_format($balance) ?></dd></div>
                <div><dt>Bài đã nộp</dt><dd><?= $submissionCount ?></dd></div>
                <div><dt>Lượt xem</dt><dd><?= number_format($views) ?></dd></div>
                <div><dt>Lượt thích</dt><dd><?= number_format($likes) ?></dd></div>
            </dl>
        </section>

        <section class="rail-section">
            <div class="rail-heading"><h2>Hiệu quả nội dung</h2><span class="status-label status-info">Đã tổng hợp</span></div>
            <div class="rail-key-number"><strong><?= number_format($engagementRate, 1) ?>%</strong><span>Tỷ lệ tương tác</span></div>
            <div class="rail-list"><p><span>Tổng lượt xem</span><strong><?= number_format($views) ?> views</strong></p><p><span>Tương tác</span><strong><?= number_format($engagements) ?></strong></p></div>
            <a class="rail-link" href="index.php?page=my-performance">Xem chỉ số <i class="bi bi-arrow-right"></i></a>
        </section>

        <section class="rail-section">
            <div class="rail-heading"><h2>Điểm gần đây</h2><a href="index.php?page=wallet">Xem ví</a></div>
            <div class="rail-transactions">
                <?php foreach (array_slice($transactions, 0, 3) as $transaction): ?>
                    <div><span class="transaction-icon"><i class="bi bi-stars"></i></span><p><strong><?= e($transaction['description']) ?></strong><small><?= date('d/m', strtotime($transaction['created_at'])) ?></small></p><b class="<?= $transaction['type'] === 'credit' ? 'positive' : 'negative' ?>"><?= $transaction['type'] === 'credit' ? '+' : '-' ?><?= (int) $transaction['points'] ?></b></div>
                <?php endforeach; ?>
                <?php if (!$transactions): ?><p class="text-muted mb-0">Chưa có giao dịch điểm.</p><?php endif; ?>
            </div>
        </section>
    </aside>
</div>

<section class="panel-card leaderboard-panel section-block">
    <div class="section-title-row"><div><h2>Bảng xếp hạng tích lũy</h2><p>Xếp theo tổng điểm đã được hệ thống xác minh.</p></div><span class="status-label status-neutral"><i class="bi bi-shield-check"></i> Dữ liệu đã kiểm tra</span></div>
    <div class="leaderboard-list">
        <?php foreach ($leaderboard as $rank => $member): ?><div class="leaderboard-row <?= (int) $member['id'] === (int) $current['id'] ? 'is-me' : '' ?>"><span class="rank-number"><?= $rank + 1 ?></span><span class="avatar avatar-sm"><?= e(initials($member['name'])) ?></span><p><strong><?= e($member['name']) ?><?= (int) $member['id'] === (int) $current['id'] ? ' (Bạn)' : '' ?></strong><small><?= e(ucfirst($member['ambassador_tier'])) ?> · <?= number_format((int) $member['views']) ?> views</small></p><b><?= number_format((int) $member['points']) ?> điểm</b></div><?php endforeach; ?>
    </div>
</section>
