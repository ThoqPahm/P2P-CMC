<?php
$activePage = (string) ($_GET['page'] ?? 'dashboard');
$isAdmin = $currentUser['role'] === 'admin';
$navItems = $isAdmin ? [
    ['admin-dashboard', 'bi-grid-1x2-fill', 'Tổng quan'],
    ['admin-campaigns', 'bi-megaphone-fill', 'Chiến dịch'],
    ['admin-submissions', 'bi-play-btn-fill', 'Bài nộp UGC'],
    ['admin-ambassadors', 'bi-people-fill', 'Đại sứ'],
    ['admin-leads', 'bi-person-lines-fill', 'Leads'],
    ['admin-rewards', 'bi-award-fill', 'Thưởng & phân hạng'],
    ['admin-moderation', 'bi-shield-check', 'Kiểm duyệt chat'],
] : [
    ['student-dashboard', 'bi-grid-1x2-fill', 'Tổng quan'],
    ['campaigns', 'bi-compass-fill', 'Khám phá nhiệm vụ'],
    ['copilot', 'bi-stars', 'AI Copilot'],
    ['my-submissions', 'bi-camera-reels-fill', 'Bài nộp của tôi'],
    ['my-affiliate', 'bi-link-45deg', 'Link Affiliate'],
    ['wallet', 'bi-wallet2', 'Ví điểm thưởng'],
];
if ($currentUser['role'] === 'ambassador') {
    $navItems[] = ['inbox', 'bi-chat-dots-fill', 'Hộp thư'];
}
?>
<aside class="app-sidebar" id="appSidebar">
    <div class="sidebar-head">
        <a class="brand brand-light sidebar-brand" href="index.php?page=dashboard" aria-label="CMC University Student Connect"><img src="assets/img/cmc-university.svg" alt="CMC University"><span>Student Connect</span></a>
        <button class="btn text-white d-lg-none ms-auto" id="sidebarClose"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sidebar-context">
        <span class="context-icon"><i class="bi <?= $isAdmin ? 'bi-building' : 'bi-mortarboard' ?>"></i></span>
        <div><small><?= $isAdmin ? 'Không gian' : 'Mã sinh viên' ?></small><strong><?= $isAdmin ? 'CMC University' : e($currentUser['student_code'] ?? 'CMC Connect') ?></strong></div>
    </div>
    <nav class="sidebar-nav">
        <p class="sidebar-label">Điều hướng</p>
        <?php foreach ($navItems as [$slug, $icon, $label]): ?>
            <a class="sidebar-link <?= $activePage === $slug || ($activePage === 'dashboard' && str_contains($slug, 'dashboard')) ? 'active' : '' ?>" href="index.php?page=<?= e($slug) ?>"><i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><?php if ($slug === 'inbox'): ?><em><?= (int) scalar("SELECT COUNT(*) FROM conversations WHERE ambassador_id = ? AND status = 'open'", [$currentUser['id']]) ?></em><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a class="sidebar-link" href="index.php?page=dashboard"><i class="bi bi-house-door"></i><span>Về tổng quan</span></a>
        <a class="sidebar-link" href="actions.php?action=logout"><i class="bi bi-box-arrow-left"></i><span>Đăng xuất</span></a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
