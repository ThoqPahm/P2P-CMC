<?php
$currentUser = user();
$isAdminPreview = $currentUser && $currentUser['role'] === 'admin' && ($_GET['preview'] ?? '') === '1';

if ($currentUser && $currentUser['role'] !== 'prospect' && !$isAdminPreview) {
    redirect('index.php?page=dashboard');
}
$pageTitle = 'Đăng nhập';
$demoAccounts = ['admin' => 'admin@cmc.edu.vn', 'student' => 'student@cmc.edu.vn', 'ambassador' => 'ambassador@cmc.edu.vn'];
$demoEmail = $demoAccounts[(string) ($_GET['demo'] ?? '')] ?? '';
$theme = login_theme_registry()[active_login_theme()];
require dirname(__DIR__, 2) . '/' . $theme['file'];
