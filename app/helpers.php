<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cachedUser = false;
    if ($cachedUser === false) {
        $statement = Database::connection()->prepare('SELECT * FROM users WHERE id = ?');
        $statement->execute([(int) $_SESSION['user_id']]);
        $cachedUser = $statement->fetch() ?: null;
    }

    return $cachedUser ?: null;
}

function require_auth(array $roles = []): void
{
    $current = user();
    if (!$current) {
        flash('warning', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('index.php?page=login');
    }
    if ($roles && !in_array($current['role'], $roles, true)) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập trang này.');
    }
}

function is_super_admin(?array $account = null): bool
{
    $account ??= user();
    if (!$account || ($account['role'] ?? '') !== 'admin') {
        return false;
    }
    $configured = trim((string) getenv('SUPER_ADMIN_EMAILS'));
    $emails = $configured !== '' ? preg_split('/\s*,\s*/', mb_strtolower($configured)) : ['admin@cmc.edu.vn'];
    return in_array(mb_strtolower((string) ($account['email'] ?? '')), $emails ?: [], true);
}

function require_super_admin(): void
{
    if (!is_super_admin()) {
        http_response_code(404);
        exit('Trang không tồn tại.');
    }
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Phiên làm việc đã hết hạn. Vui lòng tải lại trang.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function scalar(string $sql, array $params = []): mixed
{
    $statement = Database::connection()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

function rows(string $sql, array $params = []): array
{
    $statement = Database::connection()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $letters = array_map(static fn(string $part): string => mb_substr($part, 0, 1), array_slice($parts, -2));
    return mb_strtoupper(implode('', $letters));
}

function status_badge(string $status): string
{
    return match ($status) {
        'active', 'approved', 'qualified', 'open' => 'success',
        'pending', 'new' => 'warning',
        'draft' => 'secondary',
        'rejected', 'closed' => 'danger',
        default => 'primary',
    };
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Quản trị viên',
        'ambassador' => 'Đại sứ sinh viên',
        'student' => 'Sinh viên',
        'prospect' => 'Học sinh THPT',
        default => $role,
    };
}

function ui_setting_defaults(): array
{
    return [
        'login_theme' => 'eyes',
        'widget_ai_enabled' => '1',
        'widget_ai_provider' => 'gemini',
        'widget_ai_name' => 'CMC AI',
        'widget_ai_welcome' => 'Chào bạn, mình là CMC AI. Bạn đang quan tâm điều gì ở CMC? Cứ nói tự nhiên nhé, chưa biết bắt đầu từ đâu cũng không sao.',
        'widget_ai_rules' => 'Chỉ trả lời từ dữ liệu được phê duyệt. Không suy đoán học phí, học bổng, điểm chuẩn, cam kết việc làm hoặc chính sách tuyển sinh. Khi thiếu dữ liệu, nói rõ và gợi ý đại sứ phù hợp.',
        'widget_theme_primary' => '#008fd5',
        'widget_theme_navy' => '#002757',
        'widget_theme_soft' => '#f2f8fb',
        'widget_theme_accent' => '#00dedf',
    ];
}

function ui_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = ui_setting_defaults();
    foreach (rows('SELECT key, value FROM ui_settings') as $row) {
        if (array_key_exists($row['key'], $settings)) {
            $settings[$row['key']] = (string) $row['value'];
        }
    }
    return $settings;
}

function login_theme_registry(): array
{
    return [
        'eyes' => [
            'name' => 'Eyes Follow Mouse',
            'description' => 'Bốn nhân vật phẳng phản ứng theo chuột và trạng thái mật khẩu.',
            'file' => 'pages/public/login-themes/eyes.php',
            'script' => 'assets/js/login-eyes.js?v=4',
        ],
        'particles' => [
            'name' => 'CMC Particle Logo',
            'description' => 'Biểu trưng CMC tạo từ các từ khóa chuyển động theo con trỏ.',
            'file' => 'pages/public/login-themes/particles.php',
            'script' => 'assets/js/login-particles.js?v=9',
        ],
    ];
}

function active_login_theme(): string
{
    $selected = ui_settings()['login_theme'];
    return array_key_exists($selected, login_theme_registry()) ? $selected : 'eyes';
}
