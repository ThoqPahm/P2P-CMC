<?php

declare(strict_types=1);

final class ContentModerator
{
    private const ENDPOINT = 'https://api.openai.com/v1/moderations';
    private const DEFAULT_MODEL = 'omni-moderation-latest';

    /**
     * @return array{flagged: bool, provider: string, model: string, categories: array<int, string>}
     */
    public static function check(string $content): array
    {
        $local = self::checkLocally($content);
        $apiKey = trim((string) getenv('OPENAI_API_KEY'));

        if ($local['flagged'] || $apiKey === '') {
            return $local;
        }

        try {
            return self::checkWithOpenAI($content, $apiKey);
        } catch (Throwable $error) {
            error_log('OpenAI moderation unavailable: ' . $error->getMessage());
            return $local;
        }
    }

    /**
     * @return array{flagged: bool, provider: string, model: string, categories: array<int, string>}
     */
    private static function checkWithOpenAI(string $content, string $apiKey): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is not available.');
        }

        $model = trim((string) getenv('OPENAI_MODERATION_MODEL')) ?: self::DEFAULT_MODEL;
        $requestBody = json_encode([
            'model' => $model,
            'input' => $content,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $handle = curl_init(self::ENDPOINT);
        if ($handle === false) {
            throw new RuntimeException('Could not initialize moderation request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_CONNECTTIMEOUT_MS => 2000,
            CURLOPT_TIMEOUT_MS => 6000,
        ]);

        $responseBody = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if (!is_string($responseBody) || $responseBody === '' || $status < 200 || $status >= 300) {
            throw new RuntimeException($curlError !== '' ? $curlError : 'Moderation API returned HTTP ' . $status . '.');
        }

        $response = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        $result = $response['results'][0] ?? null;
        if (!is_array($result) || !isset($result['flagged'], $result['categories']) || !is_array($result['categories'])) {
            throw new RuntimeException('Moderation API returned an unexpected response.');
        }

        $categories = [];
        foreach ($result['categories'] as $category => $flagged) {
            if ($flagged === true) {
                $categories[] = (string) $category;
            }
        }

        return [
            'flagged' => (bool) $result['flagged'],
            'provider' => 'openai',
            'model' => (string) ($response['model'] ?? $model),
            'categories' => $categories,
        ];
    }

    /**
     * A deliberately small safety net for development and API outages.
     * It is not intended to replace the moderation model.
     *
     * @return array{flagged: bool, provider: string, model: string, categories: array<int, string>}
     */
    private static function checkLocally(string $content): array
    {
        $patterns = [
            'harassment' => '/(?:^|[\s,.!?])(đm+|đmm+|địt|đụ|đéo|cặc|lồn|fuck\s+you|bitch)(?:$|[\s,.!?])/ui',
            'harassment/threatening' => '/\b(tao|tôi|tớ|mình)\s+sẽ\s+(giết|đánh|đập|xử)\s+(mày|bạn|nó|chúng mày)\b/ui',
            'sexual/minors' => '/\b(trẻ em|trẻ vị thành niên|dưới 18)\b.{0,24}\b(khiêu dâm|quan hệ tình dục|ảnh nóng)\b/ui',
        ];

        $categories = [];
        foreach ($patterns as $category => $pattern) {
            if (preg_match($pattern, $content) === 1) {
                $categories[] = $category;
            }
        }

        return [
            'flagged' => $categories !== [],
            'provider' => 'local',
            'model' => 'local-safety-net-v1',
            'categories' => $categories,
        ];
    }
}
