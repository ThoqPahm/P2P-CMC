<?php

declare(strict_types=1);

ini_set('display_errors', '0');
require_once __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function rehearsal_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!is_super_admin()) {
    rehearsal_json(['ok' => false, 'message' => 'Phiên quản trị không còn hợp lệ.'], 404);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rehearsal_json(['ok' => false, 'message' => 'Phương thức không hợp lệ.'], 405);
}

$csrfToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrfToken === '' || !hash_equals(csrf_token(), $csrfToken)) {
    rehearsal_json(['ok' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 419);
}
$payload = json_decode((string) file_get_contents('php://input'), true);
$stepKey = is_array($payload) ? trim((string) ($payload['stepKey'] ?? '')) : '';
$content = is_array($payload) ? trim((string) ($payload['content'] ?? '')) : '';
$allowedKeys = ['slide-22', 'slide-27'];
for ($index = 1; $index <= 10; $index++) {
    $allowedKeys[] = 'prototype-' . $index;
}

if (!in_array($stepKey, $allowedKeys, true) || $content === '' || mb_strlen($content) > 5000) {
    rehearsal_json(['ok' => false, 'message' => 'Nội dung lưu không hợp lệ.'], 422);
}

$statement = $db->prepare(<<<'SQL'
    INSERT INTO rehearsal_scripts(user_id, step_key, content, updated_at)
    VALUES(?, ?, ?, CURRENT_TIMESTAMP)
    ON CONFLICT(user_id, step_key)
    DO UPDATE SET content = excluded.content, updated_at = CURRENT_TIMESTAMP
SQL);
$statement->execute([(int) user()['id'], $stepKey, $content]);

rehearsal_json(['ok' => true, 'content' => $content]);
