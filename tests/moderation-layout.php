<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Render-only fixtures: no real database, authentication session or writes.
set_error_handler(static function(int $severity, string $message): never { throw new RuntimeException($message); });
function require_auth(array $roles): void {}
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function initials(string $name): string { return 'QA'; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="fixture">'; }
function rows(string $sql, array $params = []): array { return str_contains($sql, 'FROM conversations') ? $GLOBALS['fixtureConversations'] : $GLOBALS['fixtureMessages']; }
function renderModeration(array $conversations, array $messages, array $query = []): string {
    $GLOBALS['fixtureConversations'] = $conversations;
    $GLOBALS['fixtureMessages'] = $messages;
    $_GET = $query;
    ob_start();
    require __DIR__ . '/../pages/admin/moderation.php';
    return ob_get_clean();
}
$checks = 0;
function check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    $GLOBALS['checks']++;
    echo "PASS: $label\n";
}
$empty = renderModeration([], []);
check(str_contains($empty, 'Chưa có cuộc trò chuyện nào'), 'empty conversation list');
check(!str_contains($empty, 'trò chuyện #0'), 'no misleading conversation zero');
$conversation = ['id'=>1, 'prospect_name'=>'Người hỏi', 'ambassador_name'=>'Đại sứ', 'message_count'=>1, 'flagged_count'=>1, 'quality_score'=>150, 'crm_status'=>'active'];
$noMessages = renderModeration([$conversation], []);
check(str_contains($noMessages, 'Chưa có tin nhắn'), 'empty message state');
check(str_contains($noMessages, 'aria-current="true"'), 'selected conversation accessible');
check(str_contains($noMessages, 'width: 100%'), 'meter safely constrained');
$message = ['id'=>7, 'sender_name'=>'Tên <script>', 'sender_role'=>'ambassador', 'created_at'=>'2026-09-06 12:00:00', 'content'=>"Dòng một\n<script>alert(1)</script>", 'is_flagged'=>1, 'moderation_categories'=>'["spam"]', 'moderation_provider'=>'ai-compatible', 'moderation_model'=>'test-model', 'moderation_confidence'=>0.9, 'moderation_reason'=>'Nội dung cần xem lại'];
$html = renderModeration([$conversation], [$message]);
check(str_contains($html, 'Đã ẩn · Cần kiểm duyệt'), 'flagged status explicit');
check(str_contains($html, 'Spam / lừa đảo'), 'category retained');
check(str_contains($html, '90% tin cậy'), 'confidence retained');
check(!str_contains($html, '<script>'), 'message and name escaped');
check(str_contains($html, 'actions.php?action=flag_message') && str_contains($html, 'value="7"'), 'moderation action retained');
check(str_contains($html, 'actions.php?action=update_support_status'), 'support action retained');
check(str_contains($html, 'Cho phép hiển thị'), 'restore action retained');
$invalid = renderModeration([$conversation], [], ['conversation'=>999]);
check(str_contains($invalid, 'Chưa có hội thoại được chọn'), 'unknown selection state');
echo "$checks checks passed.\n";
