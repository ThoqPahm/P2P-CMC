<?php

declare(strict_types=1);

final class AiProviderManager
{
    /** @return array<string, array{name: string, endpoint: string, model: string, host_suffix: string, hint: string}> */
    public static function registry(): array
    {
        return [
            'gemini' => [
                'name' => 'Google Gemini',
                'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
                'model' => 'gemini-2.5-flash',
                'host_suffix' => 'generativelanguage.googleapis.com',
                'hint' => 'API key từ Google AI Studio.',
            ],
            'deepseek' => [
                'name' => 'DeepSeek',
                'endpoint' => 'https://api.deepseek.com/chat/completions',
                'model' => 'deepseek-chat',
                'host_suffix' => 'api.deepseek.com',
                'hint' => 'API key từ DeepSeek Platform.',
            ],
            'glm' => [
                'name' => 'Zhipu GLM',
                'endpoint' => 'https://open.bigmodel.cn/api/paas/v4/chat/completions',
                'model' => 'glm-5.2',
                'host_suffix' => 'open.bigmodel.cn',
                'hint' => 'API key từ Zhipu BigModel.',
            ],
            'qwen' => [
                'name' => 'Alibaba Qwen',
                'endpoint' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions',
                'model' => 'qwen-plus',
                'host_suffix' => 'aliyuncs.com',
                'hint' => 'Có thể thay bằng endpoint Workspace cùng region.',
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function allConfigs(): array
    {
        $rows = rows('SELECT provider, endpoint, model, api_key_encrypted, enabled, last_test_status, last_test_message, last_tested_at, updated_at FROM ai_provider_configs');
        $stored = [];
        foreach ($rows as $row) {
            $stored[(string) $row['provider']] = $row;
        }
        $configs = [];
        foreach (self::registry() as $key => $preset) {
            $row = $stored[$key] ?? [];
            $configs[$key] = [
                'provider' => $key,
                'name' => $preset['name'],
                'endpoint' => (string) ($row['endpoint'] ?? $preset['endpoint']),
                'model' => (string) ($row['model'] ?? $preset['model']),
                'enabled' => (bool) ($row['enabled'] ?? true),
                'has_key' => !empty($row['api_key_encrypted']),
                'hint' => $preset['hint'],
                'last_test_status' => (string) ($row['last_test_status'] ?? 'untested'),
                'last_test_message' => (string) ($row['last_test_message'] ?? ''),
                'last_tested_at' => $row['last_tested_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }
        return $configs;
    }

    /** @return array{provider: string, endpoint: string, api_key: string, model: string}|null */
    public static function activeConfig(): ?array
    {
        $provider = (string) (ui_settings()['widget_ai_provider'] ?? 'gemini');
        return self::config($provider);
    }

    /** @return array{provider: string, endpoint: string, api_key: string, model: string}|null */
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
        $apiKey = '';
        if (!empty($row['api_key_encrypted'])) {
            try {
                $apiKey = SecretVault::decrypt((string) $row['api_key_encrypted']);
            } catch (Throwable $error) {
                error_log('Could not decrypt ' . $provider . ' API key: ' . $error->getMessage());
            }
        }
        if ($apiKey === '') {
            $envPrefix = strtoupper($provider);
            $apiKey = trim((string) getenv($envPrefix . '_API_KEY'));
        }
        if ($endpoint === '' || $model === '' || $apiKey === '') {
            return null;
        }
        return ['provider' => $provider, 'endpoint' => $endpoint, 'api_key' => $apiKey, 'model' => $model];
    }

    public static function save(string $provider, string $endpoint, string $model, string $apiKey, bool $enabled, bool $clearKey, int $userId): void
    {
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
        $statement = Database::connection()->prepare(<<<'SQL'
            INSERT INTO ai_provider_configs (provider, endpoint, model, api_key_encrypted, enabled, updated_by, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(provider) DO UPDATE SET endpoint = excluded.endpoint, model = excluded.model,
                api_key_encrypted = excluded.api_key_encrypted, enabled = excluded.enabled,
                updated_by = excluded.updated_by, updated_at = CURRENT_TIMESTAMP
        SQL);
        $statement->execute([$provider, $endpoint, $model, $encrypted, $enabled ? 1 : 0, $userId]);
    }

    /** @return array{ok: bool, message: string} */
    public static function test(string $provider): array
    {
        $config = self::config($provider);
        if ($config === null) {
            return ['ok' => false, 'message' => 'Chưa có API key hoặc provider đang tắt.'];
        }
        try {
            $content = self::request([
                ['role' => 'system', 'content' => 'Trả lời đúng một từ: OK'],
                ['role' => 'user', 'content' => 'Kiểm tra kết nối.'],
            ], $config, 20);
            $message = 'Kết nối thành công · ' . mb_substr(trim($content), 0, 60);
            self::storeTestResult($provider, true, $message);
            return ['ok' => true, 'message' => $message];
        } catch (Throwable $error) {
            $message = mb_substr($error->getMessage(), 0, 240);
            self::storeTestResult($provider, false, $message);
            return ['ok' => false, 'message' => $message];
        }
    }

    /** @param array<int, array{role: string, content: string}> $messages */
    public static function request(array $messages, array $config, int $maxTokens = 600): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is not available.');
        }
        $body = json_encode([
            'model' => $config['model'],
            'messages' => $messages,
            'temperature' => 0.2,
            'max_tokens' => $maxTokens,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $handle = curl_init($config['endpoint']);
        if ($handle === false) {
            throw new RuntimeException('Không thể khởi tạo kết nối AI.');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $config['api_key'], 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT_MS => 3000,
            CURLOPT_TIMEOUT_MS => 15000,
        ]);
        $responseBody = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        if (!is_string($responseBody) || $responseBody === '' || $status < 200 || $status >= 300) {
            throw new RuntimeException($curlError !== '' ? $curlError : 'AI API trả về HTTP ' . $status . '.');
        }
        $response = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI API trả về dữ liệu không đúng định dạng.');
        }
        return trim($content);
    }

    /** @return array<string, mixed> */
    public static function requestJson(string $systemPrompt, array $context, array $config, int $maxTokens = 600): array
    {
        $content = self::request([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ], $config, $maxTokens);
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
        Database::connection()->prepare('UPDATE ai_provider_configs SET last_test_status = ?, last_test_message = ?, last_tested_at = CURRENT_TIMESTAMP WHERE provider = ?')
            ->execute([$ok ? 'success' : 'failed', $message, $provider]);
    }
}
