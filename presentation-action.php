<?php

declare(strict_types=1);

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/PresentationMode.php';

$grant = PresentationMode::verify((string)($_GET['token'] ?? ''));
if (!$grant) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    exit('{"ok":false,"message":"Phiên trình diễn không hợp lệ hoặc đã hết hạn."}');
}

session_name('cmc_ch4_' . substr(hash('sha256', (string)$grant['nonce']), 0, 18));
session_start();
PresentationMode::bindDatabase((string)$grant['nonce']);
define('PRESENTATION_RUNTIME', true);

$source = (string)($_GET['source'] ?? 'actions');
if ($source === 'program') {
    require __DIR__ . '/program-actions.php';
} else {
    require __DIR__ . '/actions.php';
}
