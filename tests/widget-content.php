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
content_check(!str_contains($page, 'nội dung đã duyệt'), 'The public content feed must not show an internal moderation count.');
content_check(str_contains($page, 'data-content-type="social">Video</button>'), 'The public filter should use the concise Video label.');
content_check(str_contains($page, "'format' => \$item['content_type'] === 'blog' ? 'Bài viết' : 'Video'"), 'Social cards must use Video instead of a platform name.');
content_check(str_contains($page, "\$item['blog_excerpt'] ?: \$item['campaign_title']"), 'Video descriptions must use their actual editorial excerpt.');
content_check(str_contains($script, 'https://www.tiktok.com/player/v1/'), 'TikTok content must use the official player.');
content_check(str_contains($script, 'safeContentImage'), 'Content images must be restricted to repository assets.');
content_check(str_contains($script, "image ? '' : `<i class=\"bi \${icon}\"></i>`"), 'Thumbnail icons must stay hidden when a real cover image exists.');
content_check(str_contains($script, '<small>Sở thích</small>'), 'Ambassador cards must label interest data correctly.');
content_check(!str_contains($script, '<small>Có thể chia sẻ</small>'), 'Ambassador cards must not mislabel interests as consultation topics.');
content_check(str_contains($schema, '7675607526141873429') && str_contains($schema, '7674889049940823316'), 'Both approved TikTok fixtures must ship with the demo.');
content_check(str_contains($schema, 'màn đổi outfit của đôi bạn'), 'The first TikTok title must match its outfit-change story.');
content_check(str_contains($schema, 'sinh viên Marketing bay theo nghĩa đen'), 'The second TikTok title must match its visual punchline.');
content_check(substr_count($schema, 'assets/img/content/') >= 5, 'The demo needs real visual assets for its editorial feed.');

echo "PASS widget Content has long-form stories, local imagery and both CMCU TikTok embeds.\n";
