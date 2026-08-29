<?php

declare(strict_types=1);

final class WidgetAiAssistant
{
    /** @return array{suggestions: array<int, string>, provider: string, model: string} */
    public static function suggestQuestions(array $ambassador): array
    {
        $fallback = self::localSuggestions($ambassador);
        $config = self::config();
        if ($config === null) {
            return ['suggestions' => $fallback, 'provider' => 'local', 'model' => 'profile-rules-v1'];
        }

        $profile = [
            'name' => (string) ($ambassador['name'] ?? ''),
            'major' => (string) ($ambassador['major'] ?? ''),
            'study_year' => (int) ($ambassador['study_year'] ?? 0),
            'hometown' => (string) ($ambassador['hometown'] ?? ''),
            'interests' => self::interests($ambassador),
            'bio' => (string) ($ambassador['bio'] ?? ''),
        ];
        $prompt = <<<'PROMPT'
Bạn hỗ trợ học sinh THPT chuẩn bị câu hỏi cho đại sứ sinh viên CMC University.
Tạo đúng 4 câu hỏi ngắn, tự nhiên bằng tiếng Việt, dựa duy nhất trên hồ sơ được cung cấp.
Ưu tiên trải nghiệm học tập, đời sống sinh viên, hoạt động và góc nhìn cá nhân.
Không tự trả lời câu hỏi, không bịa thông tin tuyển sinh, học phí, học bổng, việc làm hoặc cam kết kết quả.
Không yêu cầu dữ liệu cá nhân nhạy cảm. Mỗi câu tối đa 120 ký tự.
Chỉ trả về JSON: {"suggestions":["...","...","...","..."]}
PROMPT;

        try {
            $result = AiProviderManager::requestJson($prompt, ['profile' => $profile], $config, 360);
            $suggestions = array_values(array_filter(array_map(
                static fn(mixed $item): string => mb_substr(trim((string) $item), 0, 120),
                is_array($result['suggestions'] ?? null) ? $result['suggestions'] : []
            )));
            if (count($suggestions) < 3) {
                throw new RuntimeException('AI suggestions are incomplete.');
            }
            return [
                'suggestions' => array_slice(array_values(array_unique($suggestions)), 0, 4),
                'provider' => 'ai-compatible',
                'model' => $config['model'],
            ];
        } catch (Throwable $error) {
            error_log('Widget AI suggestions unavailable: ' . $error->getMessage());
            return ['suggestions' => $fallback, 'provider' => 'local', 'model' => 'profile-rules-v1'];
        }
    }

    /** @return array{question: string, provider: string, model: string} */
    public static function rewriteQuestion(string $draft, array $ambassador): array
    {
        $cleanDraft = mb_substr(trim(preg_replace('/\s+/u', ' ', $draft) ?? $draft), 0, 500);
        if ($cleanDraft === '') {
            throw new InvalidArgumentException('Hãy nhập câu hỏi trước khi dùng AI.');
        }

        $moderation = ContentModerator::check($cleanDraft);
        if ($moderation['flagged']) {
            throw new InvalidArgumentException('Nội dung nháp chưa phù hợp để AI hỗ trợ. Hãy chỉnh lại câu hỏi.');
        }

        $fallback = self::localRewrite($cleanDraft);
        $config = self::config();
        if ($config === null) {
            return ['question' => $fallback, 'provider' => 'local', 'model' => 'question-cleanup-v1'];
        }

        $prompt = <<<'PROMPT'
Bạn chỉnh câu hỏi của học sinh THPT trước khi gửi cho đại sứ sinh viên CMC University.
Giữ nguyên ý định và dữ kiện của người viết. Chỉ làm câu hỏi rõ, lịch sự, tự nhiên và ngắn gọn hơn.
Không trả lời câu hỏi, không thêm thông tin tuyển sinh, không thêm lời hứa hay dữ liệu cá nhân.
Giữ cách xưng hô trung tính "mình/bạn". Câu kết quả tối đa 240 ký tự.
Chỉ trả về JSON: {"question":"..."}
PROMPT;
        $context = [
            'ambassador' => [
                'name' => (string) ($ambassador['name'] ?? ''),
                'major' => (string) ($ambassador['major'] ?? ''),
                'study_year' => (int) ($ambassador['study_year'] ?? 0),
            ],
            'draft' => $cleanDraft,
        ];

        try {
            $result = AiProviderManager::requestJson($prompt, $context, $config, 220);
            $question = mb_substr(trim((string) ($result['question'] ?? '')), 0, 240);
            if ($question === '') {
                throw new RuntimeException('AI rewrite is empty.');
            }
            return ['question' => $question, 'provider' => 'ai-compatible', 'model' => $config['model']];
        } catch (Throwable $error) {
            error_log('Widget AI rewrite unavailable: ' . $error->getMessage());
            return ['question' => $fallback, 'provider' => 'local', 'model' => 'question-cleanup-v1'];
        }
    }

    /** @return array{provider: string, endpoint: string, api_key: string, model: string}|null */
    private static function config(): ?array
    {
        $managed = AiProviderManager::activeConfig();
        if ($managed !== null) {
            return $managed;
        }
        $endpoint = trim((string) (getenv('AI_WIDGET_API_URL') ?: getenv('AI_MODERATION_API_URL')));
        $apiKey = trim((string) (getenv('AI_WIDGET_API_KEY') ?: getenv('AI_MODERATION_API_KEY')));
        $model = trim((string) (getenv('AI_WIDGET_MODEL') ?: getenv('AI_MODERATION_MODEL')));
        return $endpoint !== '' && $apiKey !== '' && $model !== ''
            ? ['provider' => 'environment', 'endpoint' => $endpoint, 'api_key' => $apiKey, 'model' => $model]
            : null;
    }

    /** @return array<int, string> */
    private static function localSuggestions(array $ambassador): array
    {
        $major = trim((string) ($ambassador['major'] ?? 'ngành bạn đang học'));
        $year = max(1, (int) ($ambassador['study_year'] ?? 1));
        $interests = self::interests($ambassador);
        $interest = $interests[0] ?? 'hoạt động sinh viên';
        return [
            "Bạn có thể chia sẻ trải nghiệm học ngành {$major} tại CMC không?",
            "Sinh viên năm {$year} thường học và tham gia những hoạt động gì?",
            "Ở CMC có những trải nghiệm nào liên quan đến {$interest}?",
            'Có điều gì bạn ước mình biết trước khi bắt đầu học tại CMC?',
        ];
    }

    /** @return array<int, string> */
    private static function interests(array $ambassador): array
    {
        $raw = $ambassador['interests'] ?? [];
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        return array_values(array_filter(array_map(static fn(mixed $item): string => trim((string) $item), is_array($raw) ? $raw : [])));
    }

    private static function localRewrite(string $draft): string
    {
        $first = mb_strtoupper(mb_substr($draft, 0, 1));
        $question = $first . mb_substr($draft, 1);
        if (preg_match('/[?.!]$/u', $question) !== 1) {
            $question .= '?';
        }
        return mb_substr($question, 0, 240);
    }
}
