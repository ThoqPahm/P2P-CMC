<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$resolved = Routes::resolve($_SERVER['REQUEST_URI'] ?? '/');
if ($resolved === null) {
    $_GET['page'] = 'not-found';
} elseif ($resolved) {
    // Path parameters take precedence over query-string overrides.
    $_GET = array_replace($_GET, $resolved);
} elseif (isset($_GET['page']) && is_string($_GET['page']) && isset(Routes::PAGES[$_GET['page']]) && in_array($_SERVER['REQUEST_METHOD'], ['GET','HEAD'], true)) {
    $params = $_GET;
    unset($params['page']);
    header('Location: '.Routes::url($_GET['page'], $params), true, 302);
    exit;
}
if ($resolved === [] && !isset($_GET['page']) && basename((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) === 'index.php' && in_array($_SERVER['REQUEST_METHOD'], ['GET','HEAD'], true)) {
    header('Location: '.Routes::url('dashboard', $_GET), true, 302);
    exit;
}
ob_start([Routes::class, 'html']);

$page = (string) ($_GET['page'] ?? (user() ? 'dashboard' : 'login'));
$page = $page === 'home' ? (user() ? 'dashboard' : 'login') : $page;

if ($page === 'widget') {
    require __DIR__ . '/pages/public/widget.php';
    exit;
}

$publicPages = ['home', 'ambassadors', 'login'];

if ($page === 'dashboard' && user()) {
    $page = user()['role'] === 'admin' ? 'admin-dashboard' : (user()['role'] === 'prospect' ? 'ambassadors' : 'student-dashboard');
}

$routes = [
    'home' => 'pages/public/home.php',
    'ambassadors' => 'pages/public/ambassadors.php',
    'login' => 'pages/public/login.php',
    'admin-dashboard' => 'pages/admin/dashboard.php',
    'admin-campaigns' => 'pages/admin/campaigns.php',
    'admin-submissions' => 'pages/admin/submissions.php',
    'admin-ambassadors' => 'pages/admin/ambassadors.php',
    'admin-performance' => 'pages/admin/performance.php',
    'admin-widget' => 'pages/admin/widget.php',
    'admin-moderation' => 'pages/admin/moderation.php',
    'admin-rewards' => 'pages/admin/rewards.php',
    'appearance-studio' => 'pages/admin/appearance.php',
    'super-admin' => 'pages/admin/super-admin.php',
    'ambassador-program' => 'pages/program.php',
    'student-dashboard' => 'pages/student/dashboard.php',
    'campaigns' => 'pages/student/campaigns.php',
    'my-submissions' => 'pages/student/submissions.php',
    'my-performance' => 'pages/student/performance.php',
    'wallet' => 'pages/student/wallet.php',
    'copilot' => 'pages/student/copilot.php',
    'inbox' => 'pages/student/inbox.php',
];

if (!isset($routes[$page])) {
    http_response_code(404);
    $page = 'not-found';
}

$isPublic = in_array($page, $publicPages, true) || $page === 'not-found';
$pageTitle = 'eAmbassador';

ob_start();
if ($page === 'not-found') {
    echo '<section class="container py-5"><div class="empty-state"><h1>404</h1><p>Trang bạn tìm không tồn tại.</p><a class="btn btn-primary" href="index.php">Về trang đăng nhập</a></div></section>';
} else {
    require __DIR__ . '/' . $routes[$page];
}
$content = ob_get_clean();

require __DIR__ . '/includes/header.php';
echo $content;
require __DIR__ . '/includes/footer.php';
