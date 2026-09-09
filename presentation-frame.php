<?php

declare(strict_types=1);

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/PresentationMode.php';

$grant = PresentationMode::verify((string)($_GET['token'] ?? ''));
if (!$grant) {
    http_response_code(403);
    exit('Phiên trình diễn không hợp lệ hoặc đã hết hạn.');
}

$targets = [
    'admin-campaigns' => ['page' => 'admin-campaigns', 'role' => 'admin'],
    'student-dashboard' => ['page' => 'student-dashboard', 'role' => 'ambassador'],
    'campaigns' => ['page' => 'campaigns', 'role' => 'ambassador'],
    'widget' => ['page' => 'widget', 'role' => 'prospect'],
    'inbox' => ['page' => 'inbox', 'role' => 'ambassador', 'params' => ['conversation' => 1]],
    'admin-moderation' => ['page' => 'admin-moderation', 'role' => 'admin', 'params' => ['conversation' => 1]],
    'admin-dashboard' => ['page' => 'admin-dashboard', 'role' => 'admin'],
];
$targetKey = (string)($_GET['target'] ?? 'campaigns');
if (!isset($targets[$targetKey])) {
    http_response_code(404);
    exit('Màn trình diễn không tồn tại.');
}

session_name('cmc_ch4_' . substr(hash('sha256', (string)$grant['nonce']), 0, 18));
session_start();
$database = PresentationMode::bindDatabase((string)$grant['nonce']);

require_once __DIR__ . '/app/bootstrap.php';
if (empty($_SESSION['presentation_seeded'])) {
    PresentationMode::seed($database);
    $_SESSION['presentation_seeded'] = true;
}

$manualPage = (string)($_GET['page'] ?? '');
if ($manualPage !== '') {
    if (!isset(Routes::PAGES[$manualPage])) {
        http_response_code(404);
        exit('Màn trình diễn không tồn tại.');
    }
    $pageParams = $_GET;
    unset($pageParams['token'], $pageParams['target'], $pageParams['stage'], $pageParams['page']);
    $_GET = ['page' => $manualPage] + array_filter($pageParams, static fn(mixed $value): bool => is_scalar($value));
    $target = ['page' => $manualPage, 'params' => array_slice($_GET, 1)];
} else {
    PresentationMode::setStage($database, (string)($_GET['stage'] ?? ''));
    $target = $targets[$targetKey];
    $_SESSION['user_id'] = PresentationMode::userId($database, $target['role']);
    $_GET = ['page' => $target['page']] + ($target['params'] ?? []);
}
$frameScript = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/presentation-frame.php'));
$baseDirectory = rtrim(dirname($frameScript), '/.');
$_SERVER['SCRIPT_NAME'] = ($baseDirectory === '' ? '' : $baseDirectory) . '/index.php';
$_SERVER['REQUEST_URI'] = Routes::url($target['page'], $target['params'] ?? []);

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
require __DIR__ . '/index.php';
