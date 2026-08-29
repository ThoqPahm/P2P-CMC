<?php

declare(strict_types=1);

final class AiProviderRequestException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 0)
    {
        parent::__construct($message);
    }
}

final class AiProviderManager
{
    public static function registry(): array
    {
        return [
            'gemini' => ['name' => 'Google Gemini', 'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', 'model' => 'gemini-2.5-flash', 'host_suffix' => 'generativelanguage.googleapis.com', 'hint' => 'API key từ Google AI Studio.'],
            'deepseek' => ['name' => 'DeepSeek', 'endpoint' => 'https://api.deepseek.com/chat/completions', 'model' => 'deepseek-chat', 'host_suffix' => 'api.deepseek.com', 'hint' => 'API key từ DeepSeek Platform.'],
            'glm' => ['name' => 'Zhipu GLM', 'endpoint' => 'https://open.bigmodel.cn/api/paas/v4/chat/completions', 'model' => 'glm-5.2', 'host_suffix' => 'open.bigmodel.cn', 'hint' => 'API key từ Zhipu BigModel.'],
            'qwen' => ['name' => 'Alibaba Qwen', 'endpoint' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions', 'model' => 'qwen-plus', 'host_suffix' => 'aliyuncs.com', 'hint' => 'Có thể thay bằng endpoint Workspace cùng region.'],
        ];
    }

    public static function allConfigs(): array
    {
        $stored = [];
        foreach (rows('SELECT * FROM ai_provider_configs') as $row) {
            $stored[(string) $row['provider']] = $row;
        }
        $keyCounts = [];
        foreach (rows('SELECT provider, COUNT(*) AS total, SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END) AS enabled_count FROM ai_provider_keys GROUP BY provider') as $row) {
            $keyCounts[(string) $row['provider']] = ['total' => (int) $row['total'], 'enabled' => (int) $row['enabled_count']];
        }
        $configs = [];
        foreach (self::registry() as $key => $preset) {
            $row = $stored[$key] ?? [];
            $counts = $keyCounts[$key] ?? ['total' => 0, 'enabled' => 0];
            $configs[$key] = [
                'provider' => $key, 'name' => $preset['name'],
                'endpoint' => (string) ($row['endpoint'] ?? $preset['endpoint']),
                'model' => (string) ($row['model'] ?? $preset['model']),
                'enabled' => (bool) ($row['enabled'] ?? true),
                'has_key' => $counts['total'] > 0 || !empty($row['api_key_encrypted']),
                'key_count' => $counts['total'], 'enabled_key_count' => $counts['enabled'],
                'hint' => $preset['hint'],
                'last_test_status' => (string) ($row['last_test_status'] ?? 'untested'),
                'last_test_message' => (string) ($row['last_test_message'] ?? ''),
                'last_tested_at' => $row['last_tested_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }
        return $configs;
    }

    public static function keySlots(string $provider): array
    {
        self::assertProvider($provider);
        return rows('SELECT id, provider, label, key_suffix, enabled, use_count, failure_count, cooldown_until, last_status, last_message, last_used_at, last_tested_at, created_at FROM ai_provider_keys WHERE provider = ? ORDER BY enabled DESC, created_at ASC, id ASC', [$provider]);
    }

    public static function addKey(string $provider, string $label, string $apiKey, int $userId): void
    {
        self::assertProvider($provider);
        $label = mb_substr(trim($label), 0, 60);
        $apiKey = trim($apiKey);
        if ($label === '' || mb_strlen($apiKey) < 12 || mb_strlen($apiKey) > 500) {
            throw new InvalidArgumentException('Hãy nhập tên slot và API key hợp lệ.');
        }
        Database::connection()->prepare('INSERT INTO ai_provider_keys (provider, label, api_key_encrypted, key_suffix, created_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$provider, $label, SecretVault::encrypt($apiKey), mb_substr($apiKey, -4), $userId]);
    }

    public static function toggleKey(int $keyId): void
    {
        self::keyRow($keyId);
        Database::connection()->prepare('UPDATE ai_provider_keys SET enabled = CASE WHEN enabled = 1 THEN 0 ELSE 1 END, cooldown_until = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$keyId]);
    }

    public static function removeKey(int $keyId): void
    {
        self::keyRow($keyId);
        Database::connection()->prepare('DELETE FROM ai_provider_keys WHERE id = ?')->execute([$keyId]);
    }

    public static function activeConfig(): ?array
    {
        return self::config((string) (ui_settings()['widget_ai_provider'] ?? 'gemini'));
    }

    public static function config(string $provider): ?array
    {
        if (!isset(self::registry()[$provider])) {
            return null;
        }
        $row = rows('SELECT endpoint, model, api_key_encrypted, enabled FROM ai_provider_configs WHERE provider = ?', [$provider])[0] ?? null;
        if ($row && !(bool) $row['enabled']) {
            return null;
        }
        $endpoint = trim((string) ($row['endpoint'] ?? self::registry()[$provider]['endpoint']));
        $model = trim((string) ($row['model'] ?? self::registry()[$provider]['model']));
        foreach (self::availableKeyRows($provider) as $candidate) {
            try {
                $apiKey = SecretVault::decrypt((string) $candidate['api_key_encrypted']);
                if ($endpoint !== '' && $model !== '' && $apiKey !== '') {
                    return ['provider' => $provider, 'endpoint' => $endpoint, 'api_key' => $apiKey, 'model' => $model, 'key_id' => (int) $candidate['id']];
                }
            } catch (Throwable $error) {
                self::markKeyFailure((int) $candidate['id'], 'decrypt_error', 'Không thể giải mã key.', 3600);
                error_log('Could not decrypt ' . $provider . ' API key: ' . $error->getMessage());
            }
        }
        $apiKey = self::legacyKey($provider, (string) ($row['api_key_encrypted'] ?? ''));
        if ($endpoint === '' || $model === '' || $apiKey === '') {
            return null;
        }
        return ['provider' => $provider, 'endpoint' => $endpoint, 'api_key' => $apiKey, 'model' => $model];
    }

    public static function save(string $provider, string $endpoint, string $model, string $apiKey, bool $enabled, bool $clearKey, int $userId): void
    {
        self::assertProvider($provider);
        $endpoint = rtrim(trim($endpoint), '/');
        self::assertEndpoint($provider, $endpoint);
        $model = trim($model);
        if ($model === '' || mb_strlen($model) > 120) {
            throw new InvalidArgumentException('Tên model không hợp lệ.');
        }
        $existing = rows('SELECT api_key_encrypted FROM ai_provider_configs WHERE provider = ?', [$provider])[0] ?? null;
        $encrypted = $clearKey ? '' : (string) ($existing['api_key_encrypted'] ?? '');
        if (trim($apiKey) !== '') {
            $encrypted = SecretVault::encrypt(trim($apiKey));
        }
        Database::connection()->prepare("INSERT INTO ai_provider_configs (provider, endpoint, model, api_key_encrypted, enabled, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP) ON CONFLICT(provider) DO UPDATE SET endpoint = excluded.endpoint, model = excluded.model, api_key_encrypted = excluded.api_key_encrypted, enabled = excluded.enabled, updated_by = excluded.updated_by, updated_at = CURRENT_TIMESTAMP")
            ->execute([$provider, $endpoint, $model, $encrypted, $enabled ? 1 : 0, $userId]);
    }

    public static function test(string $provider): array
    {
        $config = self::config($provider);
        if ($config === null) {
            return ['ok' => false, 'message' => 'Chưa có API key khả dụng hoặc provider đang tắt.'];
        }
        try {
            $content = self::request(self::testMessages(), $config, 20);
            $message = 'Kết nối thành công · ' . mb_substr(trim($content), 0, 60);
            self::storeTestResult($provider, true, $message);
            return ['ok' => true, 'message' => $message];
        } catch (Throwable $error) {
            $message = mb_substr($error->getMessage(), 0, 240);
            self::storeTestResult($provider, false, $message);
            return ['ok' => false, 'message' => $message];
        }
    }

    public static function testKey(int $keyId): array
    {
        $key = self::keyRow($keyId);
        $provider = (string) $key['provider'];
        $base = rows('SELECT endpoint, model, enabled FROM ai_provider_configs WHERE provider = ?', [$provider])[0] ?? null;
        if ($base && !(bool) $base['enabled']) {
            return ['ok' => false, 'message' => 'Provider đang tắt. Hãy bật provider trước khi test.'];
        }
        try {
            $config = ['provider' => $provider, 'endpoint' => (string) ($base['endpoint'] ?? self::registry()[$provider]['endpoint']), 'model' => (string) ($base['model'] ?? self::registry()[$provider]['model']), 'api_key' => SecretVault::decrypt((string) $key['api_key_encrypted']), 'key_id' => $keyId];
            self::markKeyAttempt($keyId);
            $content = self::requestOnce(self::testMessages(), $config, 20);
            $message = 'Slot “' . $key['label'] . '” hoạt động · ' . mb_substr(trim($content), 0, 40);
            self::markKeySuccess($keyId, $message, true);
            return ['ok' => true, 'message' => $message];
        } catch (Throwable $error) {
            self::handleKeyError($keyId, $error, true);
            return ['ok' => false, 'message' => mb_substr($error->getMessage(), 0, 240)];
        }
    }

    public static function request(array $messages, array $config, int $maxTokens = 600): string
    {
        $candidates = [];
        if (isset($config['key_id'])) {
            foreach (self::availableKeyRows((string) $config['provider'], (int) $config['key_id']) as $row) {
                try {
                    $candidate = $config;
                    $candidate['api_key'] = SecretVault::decrypt((string) $row['api_key_encrypted']);
                    $candidate['key_id'] = (int) $row['id'];
                    $candidates[] = $candidate;
                } catch (Throwable $error) {
                    self::markKeyFailure((int) $row['id'], 'decrypt_error', 'Không thể giải mã key.', 3600);
                }
            }
        }
        if (!$candidates) {
            $candidates[] = $config;
        }
        $lastError = null;
        foreach ($candidates as $candidate) {
            $keyId = isset($candidate['key_id']) ? (int) $candidate['key_id'] : 0;
            if ($keyId > 0) {
                self::markKeyAttempt($keyId);
            }
            try {
                $content = self::requestOnce($messages, $candidate, $maxTokens);
                if ($keyId > 0) {
                    self::markKeySuccess($keyId, 'Request thành công.');
                }
                return $content;
            } catch (Throwable $error) {
                $lastError = $error;
                if ($keyId > 0) {
                    self::handleKeyError($keyId, $error);
                }
                if (!self::shouldRotate($error) || $keyId === 0) {
                    throw $error;
                }
            }
        }
        throw $lastError ?? new RuntimeException('Không còn API key khả dụng.');
    }

    public static function requestJson(string $systemPrompt, array $context, array $config, int $maxTokens = 600): array
    {
        $content = self::request([['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]], $config, $maxTokens);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*|\s*```$/ui', '', $content) ?? $content;
        }
        if (preg_match('/\{.*\}/su', $content, $match) !== 1) {
            throw new RuntimeException('Phản hồi AI không chứa JSON.');
        }
        $result = json_decode($match[0], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($result)) {
            throw new RuntimeException('JSON từ AI không hợp lệ.');
        }
        return $result;
    }

    private static function requestOnce(array $messages, array $config, int $maxTokens): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is not available.');
        }
        $body = json_encode(['model' => $config['model'], 'messages' => $messages, 'temperature' => 0.2, 'max_tokens' => $maxTokens], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $handle = curl_init($config['endpoint']);
        if ($handle === false) {
            throw new RuntimeException('Không thể khởi tạo kết nối AI.');
        }
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $config['api_key'], 'Content-Type: application/json'], CURLOPT_POSTFIELDS => $body, CURLOPT_CONNECTTIMEOUT_MS => 3000, CURLOPT_TIMEOUT_MS => 15000]);
        $responseBody = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        if (!is_string($responseBody) || $responseBody === '' || $status < 200 || $status >= 300) {
            $apiMessage = self::responseErrorMessage(is_string($responseBody) ? $responseBody : '');
            throw new AiProviderRequestException($curlError !== '' ? $curlError : ($apiMessage !== '' ? $apiMessage : 'AI API trả về HTTP ' . $status . '.'), $status);
        }
        $response = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI API trả về dữ liệu không đúng định dạng.');
        }
        return trim($content);
    }

    private static function availableKeyRows(string $provider, int $preferredId = 0): array
    {
        return rows('SELECT * FROM ai_provider_keys WHERE provider = ? AND enabled = 1 AND (cooldown_until IS NULL OR cooldown_until <= CURRENT_TIMESTAMP) ORDER BY CASE WHEN id = ? THEN 0 ELSE 1 END, use_count ASC, CASE WHEN last_used_at IS NULL THEN 0 ELSE 1 END, last_used_at ASC, id ASC', [$provider, $preferredId]);
    }

    private static function keyRow(int $keyId): array
    {
        $row = $keyId > 0 ? (rows('SELECT * FROM ai_provider_keys WHERE id = ?', [$keyId])[0] ?? null) : null;
        if (!$row) {
            throw new InvalidArgumentException('Không tìm thấy slot API key.');
        }
        return $row;
    }

    private static function markKeyAttempt(int $keyId): void
    {
        Database::connection()->prepare('UPDATE ai_provider_keys SET use_count = use_count + 1, last_used_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$keyId]);
    }

    private static function markKeySuccess(int $keyId, string $message, bool $tested = false): void
    {
        $testedSql = $tested ? ', last_tested_at = CURRENT_TIMESTAMP' : '';
        Database::connection()->prepare("UPDATE ai_provider_keys SET failure_count = 0, cooldown_until = NULL, last_status = 'success', last_message = ?, updated_at = CURRENT_TIMESTAMP{$testedSql} WHERE id = ?")->execute([mb_substr($message, 0, 240), $keyId]);
    }

    private static function markKeyFailure(int $keyId, string $status, string $message, int $cooldownSeconds, bool $tested = false): void
    {
        $testedSql = $tested ? ', last_tested_at = CURRENT_TIMESTAMP' : '';
        Database::connection()->prepare("UPDATE ai_provider_keys SET failure_count = failure_count + 1, cooldown_until = ?, last_status = ?, last_message = ?, updated_at = CURRENT_TIMESTAMP{$testedSql} WHERE id = ?")->execute([gmdate('Y-m-d H:i:s', time() + $cooldownSeconds), $status, mb_substr($message, 0, 240), $keyId]);
    }

    private static function handleKeyError(int $keyId, Throwable $error, bool $tested = false): void
    {
        $status = $error instanceof AiProviderRequestException ? $error->httpStatus : 0;
        if ($status === 401 || $status === 403) {
            self::markKeyFailure($keyId, 'auth_error', $error->getMessage(), 3600, $tested);
        } elseif ($status === 429) {
            self::markKeyFailure($keyId, 'rate_limited', $error->getMessage(), 120, $tested);
        } elseif ($status === 0 || $status >= 500) {
            self::markKeyFailure($keyId, 'temporary_error', $error->getMessage(), 45, $tested);
        } else {
            self::markKeyFailure($keyId, 'failed', $error->getMessage(), 15, $tested);
        }
    }

    private static function shouldRotate(Throwable $error): bool
    {
        if (!$error instanceof AiProviderRequestException) {
            return true;
        }
        return $error->httpStatus === 0 || in_array($error->httpStatus, [401, 403, 408, 409, 429], true) || $error->httpStatus >= 500;
    }

    private static function responseErrorMessage(string $body): string
    {
        $decoded = $body !== '' ? json_decode($body, true) : null;
        $message = is_array($decoded) ? ($decoded['error']['message'] ?? $decoded['message'] ?? '') : '';
        return is_string($message) ? mb_substr(trim($message), 0, 240) : '';
    }

    private static function legacyKey(string $provider, string $encrypted): string
    {
        if ($encrypted !== '') {
            try {
                return SecretVault::decrypt($encrypted);
            } catch (Throwable $error) {
                error_log('Could not decrypt legacy ' . $provider . ' API key: ' . $error->getMessage());
            }
        }
        return trim((string) getenv(strtoupper($provider) . '_API_KEY'));
    }

    private static function testMessages(): array
    {
        return [['role' => 'system', 'content' => 'Trả lời đúng một từ: OK'], ['role' => 'user', 'content' => 'Kiểm tra kết nối.']];
    }

    private static function assertProvider(string $provider): void
    {
        if (!isset(self::registry()[$provider])) {
            throw new InvalidArgumentException('Provider AI không hợp lệ.');
        }
    }

    private static function assertEndpoint(string $provider, string $endpoint): void
    {
        $preset = self::registry()[$provider] ?? null;
        $parts = parse_url(trim($endpoint));
        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        $suffix = (string) ($preset['host_suffix'] ?? '');
        $validHost = $host === $suffix || ($provider === 'qwen' && str_ends_with($host, '.' . $suffix));
        if (!$preset || ($parts['scheme'] ?? '') !== 'https' || !$validHost || !str_ends_with(rtrim((string) ($parts['path'] ?? ''), '/'), '/chat/completions')) {
            throw new InvalidArgumentException('Endpoint không thuộc domain chính thức của provider hoặc thiếu /chat/completions.');
        }
    }

    private static function storeTestResult(string $provider, bool $ok, string $message): void
    {
        Database::connection()->prepare('UPDATE ai_provider_configs SET last_test_status = ?, last_test_message = ?, last_tested_at = CURRENT_TIMESTAMP WHERE provider = ?')->execute([$ok ? 'success' : 'failed', $message, $provider]);
    }
}
