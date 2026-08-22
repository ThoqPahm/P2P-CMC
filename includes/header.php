<?php
$currentUser = user();
$flashes = pull_flashes();
$isLogin = ($page ?? '') === 'login';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="CMC Connect - Hệ sinh thái đại sứ sinh viên số và truyền thông ngang hàng.">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · CMC Connect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css?v=2" rel="stylesheet">
    <link href="assets/css/cmc-brand.css?v=2" rel="stylesheet">
</head>
<body class="<?= $isLogin ? 'login-layout' : ($isPublic ? 'public-layout' : 'app-layout') ?>">
<?php if ($isPublic && !$isLogin): ?>
    <nav class="navbar navbar-expand-lg public-nav sticky-top">
        <div class="container">
            <a class="brand cmc-brand" href="index.php?page=home" aria-label="CMC University Student Connect">
                <img src="assets/img/cmc-university-horizontal.png" alt="CMC University">
                <span class="brand-product">Student Connect</span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="publicNavbar">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <a class="nav-link" href="index.php?page=home#about">Về nền tảng</a>
                    <a class="nav-link" href="index.php?page=ambassadors">Gặp đại sứ</a>
                    <a class="nav-link" href="index.php?page=home#how-it-works">Cách hoạt động</a>
                    <?php if ($currentUser && $currentUser['role'] !== 'prospect'): ?>
                        <a class="btn btn-brand ms-lg-2" href="index.php?page=dashboard">Vào hệ thống <i class="bi bi-arrow-up-right"></i></a>
                    <?php elseif ($currentUser): ?>
                        <a class="btn btn-outline-brand ms-lg-2" href="actions.php?action=logout">Đăng xuất</a>
                    <?php else: ?>
                        <a class="btn btn-outline-brand ms-lg-2" href="index.php?page=login">Đăng nhập</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
<?php elseif (!$isPublic): ?>
    <?php require __DIR__ . '/sidebar.php'; ?>
    <main class="app-main">
        <header class="app-topbar">
            <button class="btn icon-btn d-lg-none" id="sidebarToggle" aria-label="Mở menu"><i class="bi bi-list"></i></button>
            <div>
                <p class="topbar-context mb-1"><?= $currentUser['role'] === 'admin' ? 'Trung tâm vận hành' : 'Không gian của bạn' ?></p>
                <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
            </div>
            <div class="topbar-actions ms-auto">
                <button class="btn icon-btn position-relative" aria-label="Thông báo"><i class="bi bi-bell"></i><span class="notification-dot"></span></button>
                <div class="user-chip d-none d-sm-flex">
                    <span class="avatar avatar-sm"><?= e(initials($currentUser['name'])) ?></span>
                    <span><strong><?= e($currentUser['name']) ?></strong><small><?= e(role_label($currentUser['role'])) ?></small></span>
                </div>
            </div>
        </header>
        <div class="app-content">
<?php endif; ?>

<?php foreach ($flashes as $flash): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div class="toast show border-0 shadow" role="alert" data-autohide="true">
            <div class="toast-header"><span class="status-dot bg-<?= e($flash['type']) ?>"></span><strong class="me-auto">CMC Connect</strong><button type="button" class="btn-close" data-bs-dismiss="toast"></button></div>
            <div class="toast-body"><?= e($flash['message']) ?></div>
        </div>
    </div>
<?php endforeach; ?>
