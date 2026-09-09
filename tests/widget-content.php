<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$page = file_get_contents($root . '/pages/public/widget.php');
$script = file_get_contents($root . '/assets/js/widget.js');
$schema = file_get_contents($root . '/app/Database.php');

function content_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

content_check(str_contains($page, "s.cover_image, s.source_label"), 'Widget query must include editorial media metadata.');
content_check(str_contains($page, "'coverImage' =>"), 'Widget payload must expose cover images.');
content_check(str_contains($page, 'id="detailMedia"'), 'Widget detail needs an inline media region.');
content_check(str_contains($page, 'Câu chuyện từ đại sứ CMCU'), 'Widget content heading must use the CMCU name.');
content_check(str_contains($script, 'https://www.tiktok.com/player/v1/'), 'TikTok content must use the official player.');
content_check(str_contains($script, 'safeContentImage'), 'Content images must be restricted to repository assets.');
content_check(str_contains($schema, '7675607526141873429') && str_contains($schema, '7674889049940823316'), 'Both approved TikTok fixtures must ship with the demo.');
content_check(substr_count($schema, 'assets/img/content/') >= 5, 'The demo needs real visual assets for its editorial feed.');

echo "PASS widget Content has long-form stories, local imagery and both CMCU TikTok embeds.\n";
