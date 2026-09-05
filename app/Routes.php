<?php
declare(strict_types=1);

final class Routes
{
    public const PAGES = [
        'home'=>'trang-chu', 'login'=>'dang-nhap', 'dashboard'=>'tong-quan',
        'ambassadors'=>'dai-su', 'widget'=>'widget',
        'admin-dashboard'=>'admin/tong-quan', 'admin-campaigns'=>'admin/chien-dich',
        'admin-submissions'=>'admin/bai-nop', 'admin-ambassadors'=>'admin/dai-su',
        'admin-performance'=>'admin/hieu-qua', 'admin-widget'=>'admin/widget',
        'admin-moderation'=>'admin/kiem-duyet', 'admin-rewards'=>'admin/diem-thuong',
        'appearance-studio'=>'admin/giao-dien', 'super-admin'=>'admin/ky-thuat',
        'ambassador-program'=>'chuong-trinh-dai-su', 'student-dashboard'=>'sinh-vien/tong-quan',
        'campaigns'=>'chien-dich', 'my-submissions'=>'bai-nop', 'my-performance'=>'hieu-qua',
        'wallet'=>'vi-diem', 'copilot'=>'tro-ly-noi-dung', 'inbox'=>'hop-thu',
    ];

    public static function base(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        if (!preg_match('~/(?:index|api|actions|program-actions)\.php$~', $script)) {
            return '/'; // PHP development server front-controller fallback.
        }
        return rtrim(dirname($script), '/.') . '/';
    }

    public static function url(string $page, array $params = []): string
    {
        $path = self::PAGES[$page] ?? null;
        if ($path === null) { return self::base().'index.php?'.http_build_query(['page'=>$page]+$params); }
        if (in_array($page, ['inbox','admin-moderation'], true) && isset($params['conversation']) && ctype_digit((string)$params['conversation']) && (int)$params['conversation']>0) {
            $path .= '/'.(int)$params['conversation']; unset($params['conversation']);
        }
        if ($page === 'ambassador-program' && isset($params['tab']) && in_array($params['tab'], ['members','knowledge','quality','reports'], true)) {
            $path .= '/'.['members'=>'thanh-vien','knowledge'=>'nguon-thong-tin','quality'=>'chat-luong','reports'=>'phan-anh'][$params['tab']];
            unset($params['tab']);
        }
        return self::base().$path.($params ? '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
    }

    // Compatibility adapter for existing server-rendered links and redirects.
    // External URLs, API endpoints, form actions and user content are not rewritten.
    public static function legacy(string $url): string
    {
        if (!preg_match('~^(?:'.preg_quote(self::base(),'~').')?index\.php(?:\?([^#]*))?(#.*)?$~', $url, $m)) { return $url; }
        parse_str($m[1] ?? '', $params);
        $page = $params['page'] ?? 'dashboard';
        if (!is_string($page) || !isset(self::PAGES[$page])) { return $url; }
        unset($params['page']);
        return self::url($page, $params).($m[2] ?? '');
    }

    public static function html(string $html): string
    {
        // A base URL keeps assets/forms/fetch working at nested paths; fragment
        // links must explicitly target the current document rather than the base.
        $current = (string)($_SERVER['REQUEST_URI'] ?? self::base());
        if (!str_starts_with($current, self::base()) || str_starts_with($current, '//')) { $current=self::base(); }
        $html = preg_replace_callback('~\bhref=("|\')(#[^"\'<>]*)\1~', static fn(array $m): string => 'href='.$m[1].htmlspecialchars($current, ENT_QUOTES, 'UTF-8').$m[2].$m[1], $html) ?? $html;
        return preg_replace_callback('~\b(href|src)=("|\')((?:/[^"\'<>]*)?index\.php[^"\'<>]*)\2~', static function(array $m): string {
            $url = self::legacy(html_entity_decode($m[3], ENT_QUOTES, 'UTF-8'));
            return $m[1].'='.$m[2].htmlspecialchars($url, ENT_QUOTES, 'UTF-8').$m[2];
        }, $html) ?? $html;
    }

    public static function resolve(string $uri): ?array
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $base = self::base();
        if (!is_string($path) || !str_starts_with($path, $base)) { return null; }
        $path = trim(substr($path, strlen($base)), '/');
        if ($path === '' || $path === 'index.php') { return []; }
        $page = array_search($path, self::PAGES, true);
        if ($page !== false) { return ['page'=>$page]; }
        if (preg_match('~^(hop-thu|admin/kiem-duyet)/([1-9][0-9]*)$~D', $path, $m)) {
            return ['page'=>$m[1]==='hop-thu'?'inbox':'admin-moderation', 'conversation'=>$m[2]];
        }
        if (preg_match('~^chuong-trinh-dai-su/(thanh-vien|nguon-thong-tin|chat-luong|phan-anh)$~D', $path, $m)) {
            return ['page'=>'ambassador-program','tab'=>['thanh-vien'=>'members','nguon-thong-tin'=>'knowledge','chat-luong'=>'quality','phan-anh'=>'reports'][$m[1]]];
        }
        return null;
    }
}
