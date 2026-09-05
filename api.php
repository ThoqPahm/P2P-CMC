<?php

declare(strict_types=1);

// API responses must remain valid JSON even when the local PHP runtime emits warnings.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ob_start();
register_shutdown_function(static function (): void {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!$error || !in_array($error['type'], $fatalTypes, true)) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo '{"ok":false,"message":"Máy chủ chưa hoàn tất phản hồi. Vui lòng thử gửi lại sau ít phút."}';
});

require_once __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    try {
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (JsonException) {
        http_response_code(500);
        echo '{"ok":false,"message":"Phản hồi máy chủ không đúng định dạng. Vui lòng thử lại."}';
    }
    exit;
}

function verify_widget_access(): string
{
    $token = (string) ($_POST['widget_token'] ?? $_GET['widget_token'] ?? ($_SERVER['HTTP_X_WIDGET_TOKEN'] ?? ''));
    if ($token === '' || !(int) scalar('SELECT COUNT(*) FROM widget_access_tokens WHERE token = ? AND expires_at > CURRENT_TIMESTAMP', [$token])) {
        json_response(['ok' => false, 'message' => 'Phiên widget đã hết hạn. Vui lòng tải lại cửa sổ tư vấn.'], 419);
    }
    return $token;
}

/**
 * @return array{flagged: bool, provider: string, model: string, categories: array<int, string>, confidence: float, reason: string}
 */
function store_chat_message(PDO $db, int $conversationId, int $senderId, string $content): array
{
    $cleanContent = mb_substr(trim($content), 0, 1000);
    $moderation = ContentModerator::check($cleanContent);
    $statement = $db->prepare(
        'INSERT INTO messages (conversation_id, sender_id, content, is_flagged, moderation_provider, moderation_model, moderation_categories, moderation_confidence, moderation_reason, moderated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
    );
    $statement->execute([
        $conversationId,
        $senderId,
        $cleanContent,
        $moderation['flagged'] ? 1 : 0,
        $moderation['provider'],
        $moderation['model'],
        json_encode($moderation['categories'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $moderation['confidence'],
        $moderation['reason'],
    ]);

    return $moderation;
}

function refresh_conversation_quality(PDO $db, int $conversationId): int
{
    return WorkflowIntegrity::quality($db, $conversationId, true);
}

function reject_flagged_message(array $moderation): void
{
    if (!$moderation['flagged']) {
        return;
    }

    json_response([
        'ok' => false,
        'code' => 'content_blocked',
        'message' => 'Tin nhắn có nội dung không phù hợp với tiêu chuẩn cộng đồng. Hãy chỉnh lại rồi gửi nhé.',
    ], 422);
}

function enforce_widget_ai_rate_limit(): void
{
    $now = time();
    $requests = array_values(array_filter(
        is_array($_SESSION['widget_ai_requests'] ?? null) ? $_SESSION['widget_ai_requests'] : [],
        static fn(mixed $timestamp): bool => is_int($timestamp) && $timestamp > $now - 60
    ));
    if (count($requests) >= 15) {
        json_response(['ok' => false, 'message' => 'Bạn đang dùng AI quá nhanh. Hãy thử lại sau một phút.'], 429);
    }
    $requests[] = $now;
    $_SESSION['widget_ai_requests'] = $requests;
}

/** @return array<string, mixed> */
function widget_ambassador(int $ambassadorId): array
{
    $ambassador = rows("SELECT id, name, major, hometown, interests, bio, study_year, is_online FROM eligible_ambassadors WHERE id = ?", [$ambassadorId])[0] ?? null;
    if (!$ambassador) {
        throw new InvalidArgumentException('Không tìm thấy đại sứ phù hợp.');
    }
    return $ambassador;
}

try {
    $widgetActions = ['widget_start_chat', 'widget_send_message', 'widget_schedule', 'widget_ai_chat', 'widget_ai_suggestions', 'widget_ai_rewrite', 'widget_rate_conversation'];
    if (!in_array($action, ['widget_messages', 'widget_appointment_status', 'messages'], true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok'=>false, 'message'=>'Chỉ hỗ trợ POST.'], 405);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (is_array($payload)) {
            $_POST = array_merge($_POST, $payload);
        }
        if (in_array($action, $widgetActions, true)) {
            verify_widget_access();
        } else {
            verify_csrf();
        }
    }

    switch ($action) {
        case 'widget_ai_chat':
            enforce_widget_ai_rate_limit();
            $history = is_array($_POST['history'] ?? null) ? $_POST['history'] : [];
            $result = WidgetChatAssistant::reply((string) ($_POST['message'] ?? ''), $history);
            json_response(['ok' => true] + $result);

        case 'widget_ai_suggestions':
            enforce_widget_ai_rate_limit();
            $ambassador = widget_ambassador((int) ($_POST['ambassador_id'] ?? 0));
            $result = WidgetAiAssistant::suggestQuestions($ambassador);
            json_response(['ok' => true] + $result);

        case 'widget_ai_rewrite':
            enforce_widget_ai_rate_limit();
            $ambassador = widget_ambassador((int) ($_POST['ambassador_id'] ?? 0));
            $result = WidgetAiAssistant::rewriteQuestion((string) ($_POST['draft'] ?? ''), $ambassador);
            json_response(['ok' => true] + $result);

        case 'widget_start_chat':
            $ambassadorId = (int) ($_POST['ambassador_id'] ?? 0);
            $ambassador = rows("SELECT id, is_online FROM eligible_ambassadors WHERE id = ?", [$ambassadorId])[0] ?? null;
            if (!$ambassador) {
                throw new InvalidArgumentException('Đại sứ này hiện chưa sẵn sàng nhận tin nhắn.');
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Vui lòng nhập tên và email hợp lệ.');
            }
            // Unverified contact email must never merge identities. A guest gets
            // an opaque internal identity; the real email is contact data only.
            $statement = $db->prepare("INSERT INTO users (role,name,email,contact_email,password,major,is_online) VALUES ('prospect',?,?,?,?,?,0)");
            $statement->execute([$name,'guest-'.bin2hex(random_bytes(16)).'@widget.invalid',$email,password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),trim((string)($_POST['major']??''))]);
            $prospectId=(int)$db->lastInsertId();
            // An email is a contact address, not proof of ownership. Starting a
            // guest chat never grants access to or rotates any existing thread.
            // Existing threads are resumed through their browser-held token.
            $conversationId = 0;
            $conversationToken = bin2hex(random_bytes(24));
            if (!$conversationId) {
                $statement = $db->prepare('INSERT INTO conversations (prospect_id, ambassador_id, public_token) VALUES (?, ?, ?)');
                $statement->execute([$prospectId, $ambassadorId, $conversationToken]);
                $conversationId = (int) $db->lastInsertId();
            }
            $firstMessage = trim((string) ($_POST['message'] ?? ''));
            if ($firstMessage !== '') {
                $moderation = store_chat_message($db, $conversationId, $prospectId, $firstMessage);
                refresh_conversation_quality($db, $conversationId);
                reject_flagged_message($moderation);
            }
            json_response([
                'ok' => true,
                'conversation_id' => $conversationId,
                'conversation_token' => $conversationToken,
                'current_user_id' => $prospectId,
                'ambassador_online' => (bool) $ambassador['is_online'],
            ]);

        case 'widget_messages':
            verify_widget_access();
            $conversationId = (int) ($_GET['conversation_id'] ?? 0);
            $conversationToken = (string) ($_GET['conversation_token'] ?? '');
            $conversation = rows('SELECT prospect_id FROM conversations WHERE id = ? AND public_token = ? AND status = ?', [$conversationId, $conversationToken, 'open'])[0] ?? null;
            if (!$conversation) {
                json_response(['ok' => false, 'message' => 'Cuộc trò chuyện không còn khả dụng.'], 403);
            }
            $messages = rows('SELECT m.id, m.content, m.created_at, m.sender_id, u.name AS sender_name, u.role AS sender_role FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.conversation_id = ? AND m.is_flagged = 0 ORDER BY m.id', [$conversationId]);
            json_response(['ok' => true, 'messages' => $messages, 'current_user_id' => (int) $conversation['prospect_id']]);

        case 'widget_send_message':
            $conversationId = (int) ($_POST['conversation_id'] ?? 0);
            $conversationToken = (string) ($_POST['conversation_token'] ?? '');
            $content = trim((string) ($_POST['content'] ?? ''));
            $conversation = rows('SELECT prospect_id FROM conversations WHERE id = ? AND public_token = ? AND status = ?', [$conversationId, $conversationToken, 'open'])[0] ?? null;
            if (!$conversation || $content === '') {
                throw new InvalidArgumentException('Tin nhắn không hợp lệ.');
            }
            $db->beginTransaction();
            $moderation = store_chat_message($db, $conversationId, (int) $conversation['prospect_id'], $content);
            $qualityScore = refresh_conversation_quality($db, $conversationId);
            $db->commit();
            reject_flagged_message($moderation);
            json_response(['ok' => true, 'quality_score' => $qualityScore]);

        case 'widget_rate_conversation':
            $conversationId = (int) ($_POST['conversation_id'] ?? 0);
            $conversationToken = (string) ($_POST['conversation_token'] ?? '');
            $clarity = filter_var($_POST['clarity_rating'] ?? null, FILTER_VALIDATE_INT);
            $helpfulness = filter_var($_POST['helpfulness_rating'] ?? null, FILTER_VALIDATE_INT);
            if ($clarity === false || $helpfulness === false || $clarity < 1 || $clarity > 5 || $helpfulness < 1 || $helpfulness > 5) {
                throw new InvalidArgumentException('Hãy chọn đánh giá từ 1 đến 5.');
            }
            $feedback = mb_substr(trim((string) ($_POST['feedback_note'] ?? '')), 0, 500);

            $conversation = rows('SELECT id, prospect_id, quality_score FROM conversations WHERE id = ? AND public_token = ?', [$conversationId, $conversationToken])[0] ?? null;
            if (!$conversation) {
                json_response(['ok' => false, 'message' => 'Cuộc trò chuyện không tồn tại.'], 404);
            }
            $avgStars = (int) round(($clarity + $helpfulness) / 2);
            $statement = $db->prepare('UPDATE conversations SET clarity_rating = ?, helpfulness_rating = ?, feedback_note = ?, rating = ? WHERE id = ?');
            $statement->execute([$clarity, $helpfulness, $feedback, $avgStars, $conversationId]);
            json_response(['ok' => true, 'message' => 'Cảm ơn bạn đã gửi đánh giá! Ý kiến của bạn giúp nâng cao chất lượng tư vấn.']);

        case 'widget_appointment_status':
            verify_widget_access();
            $appointment = rows('SELECT a.id,a.status,a.preferred_at,u.name AS ambassador_name FROM consultation_appointments a JOIN users u ON u.id=a.ambassador_id WHERE a.id=? AND a.public_token=?', [(int)($_GET['appointment_id']??0),(string)($_GET['appointment_token']??'')])[0]??null;
            if (!$appointment) { json_response(['ok'=>false,'message'=>'Không tìm thấy lịch hẹn.'],403); }
            json_response(['ok'=>true,'appointment'=>$appointment]);

        case 'widget_schedule':
            $ambassadorId = (int) ($_POST['ambassador_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $preferredAt = trim((string) ($_POST['preferred_at'] ?? ''));
            $ambassadorExists = (int) scalar("SELECT COUNT(*) FROM eligible_ambassadors WHERE id = ?", [$ambassadorId]);
            if (!$ambassadorExists || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strtotime($preferredAt) <= time()) {
                throw new InvalidArgumentException('Vui lòng kiểm tra lại thông tin và chọn thời gian trong tương lai.');
            }
            $linkedConversation = rows("SELECT id FROM conversations WHERE id=? AND public_token=? AND ambassador_id=? AND status='open'",[(int)($_POST['conversation_id']??0),(string)($_POST['conversation_token']??''),$ambassadorId])[0]??null;
            $appointmentToken=bin2hex(random_bytes(24));
            $statement = $db->prepare('INSERT INTO consultation_appointments (ambassador_id,student_name,email,phone,preferred_at,question,public_token,conversation_id) VALUES (?,?,?,?,?,?,?,?)');
            $statement->execute([$ambassadorId,$name,$email,trim((string)($_POST['phone']??'')),date('Y-m-d H:i:s',strtotime($preferredAt)),mb_substr(trim((string)($_POST['question']??'')),0,1000),$appointmentToken,$linkedConversation['id']??null]);
            json_response(['ok'=>true,'appointment_id'=>(int)$db->lastInsertId(),'appointment_token'=>$appointmentToken,'message'=>'Yêu cầu đang chờ xác nhận. Xem cập nhật trong Inbox của widget.']);

        case 'start_chat':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
            }
            $ambassadorId = (int) ($_POST['ambassador_id'] ?? 0);
            $ambassador = rows("SELECT id FROM eligible_ambassadors WHERE id = ?", [$ambassadorId])[0] ?? null;
            if (!$ambassador) {
                throw new InvalidArgumentException('Đại sứ này hiện chưa sẵn sàng.');
            }
            $current = user();
            if (!$current || $current['role'] !== 'prospect') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Vui lòng nhập tên và email hợp lệ.');
                }
                $statement = $db->prepare('SELECT * FROM users WHERE email = ?');
                $statement->execute([$email]);
                $prospect = $statement->fetch();
                if (!$prospect) {
                    $statement = $db->prepare("INSERT INTO users (role, name, email, password, major, is_online) VALUES ('prospect', ?, ?, ?, ?, 1)");
                    $statement->execute([$name, $email, password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT), trim((string) ($_POST['major'] ?? ''))]);
                    $prospectId = (int) $db->lastInsertId();
                } elseif ($prospect['role'] === 'prospect') {
                    throw new InvalidArgumentException('Email đã có tài khoản. Hãy đăng nhập để xem hội thoại cũ, hoặc dùng widget để bắt đầu một cuộc trò chuyện riêng.');
                } else {
                    throw new InvalidArgumentException('Email này thuộc tài khoản nội bộ. Vui lòng đăng nhập.');
                }
                session_regenerate_id(true);
                $_SESSION['user_id'] = $prospectId;
                $current = ['id' => $prospectId, 'role' => 'prospect'];
            }
            $conversationId = (int) (scalar("SELECT id FROM conversations WHERE prospect_id = ? AND ambassador_id = ? AND status = 'open' AND public_token IS NULL ORDER BY id DESC LIMIT 1", [$current['id'], $ambassadorId]) ?: 0);
            if (!$conversationId) {
                $statement = $db->prepare('INSERT INTO conversations (prospect_id, ambassador_id) VALUES (?, ?)');
                $statement->execute([$current['id'], $ambassadorId]);
                $conversationId = (int) $db->lastInsertId();
            }
            $firstMessage = trim((string) ($_POST['message'] ?? ''));
            if ($firstMessage !== '') {
                $moderation = store_chat_message($db, $conversationId, (int) $current['id'], $firstMessage);
                refresh_conversation_quality($db, $conversationId);
                reject_flagged_message($moderation);
            }
            json_response(['ok' => true, 'conversation_id' => $conversationId]);

        case 'messages':
            $conversationId = (int) ($_GET['conversation_id'] ?? 0);
            $current = user();
            if (!$current) {
                json_response(['ok' => false, 'message' => 'Chưa đăng nhập'], 401);
            }
            $allowed = (int) scalar('SELECT COUNT(*) FROM conversations WHERE id = ? AND ((prospect_id = ? AND public_token IS NULL) OR ambassador_id = ?)', [$conversationId, $current['id'], $current['id']]);
            if (!$allowed) {
                json_response(['ok' => false, 'message' => 'Không có quyền'], 403);
            }
            $visibilityClause = ' AND m.is_flagged = 0';
            $messages = rows('SELECT m.id, m.content, m.created_at, m.sender_id, u.name AS sender_name, u.role AS sender_role FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.conversation_id = ?' . $visibilityClause . ' ORDER BY m.id', [$conversationId]);
            json_response(['ok' => true, 'messages' => $messages, 'current_user_id' => (int) $current['id']]);

        case 'send_message':
            $current = user();
            if (!$current) {
                json_response(['ok' => false, 'message' => 'Vui lòng đăng nhập lại.'], 401);
            }
            $conversationId = (int) ($_POST['conversation_id'] ?? 0);
            $content = trim((string) ($_POST['content'] ?? ''));
            $allowed = (int) scalar("SELECT COUNT(*) FROM conversations WHERE id = ? AND status = 'open' AND ((prospect_id = ? AND public_token IS NULL) OR ambassador_id = ?)", [$conversationId, $current['id'], $current['id']]);
            if (!$allowed || $content === '') {
                throw new InvalidArgumentException('Tin nhắn không hợp lệ.');
            }
            $db->beginTransaction();
            $moderation = store_chat_message($db, $conversationId, (int) $current['id'], $content);
            $qualityScore = refresh_conversation_quality($db, $conversationId);
            $db->commit();
            reject_flagged_message($moderation);
            json_response(['ok' => true, 'quality_score' => $qualityScore]);

        case 'copilot_generate':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
            }
            $current = user();
            if (!$current || !in_array($current['role'], ['student', 'ambassador'], true)) {
                json_response(['ok' => false, 'message' => 'Bạn cần đăng nhập bằng tài khoản sinh viên.'], 401);
            }

            $campaignId = (int) ($_POST['campaign_id'] ?? 0);
            $objective = trim((string) ($_POST['objective'] ?? ''));
            $platform = trim((string) ($_POST['platform'] ?? 'TikTok'));
            $tone = trim((string) ($_POST['tone'] ?? 'Chân thật'));
            $allowedPlatforms = ['TikTok', 'Reels', 'YouTube Shorts'];
            $allowedTones = ['Chân thật', 'Năng động', 'Gần gũi', 'Thông tin'];
            if ($campaignId < 1 || mb_strlen($objective) < 12) {
                throw new InvalidArgumentException('Hãy chọn chiến dịch và mô tả ý tưởng rõ hơn một chút.');
            }
            if (!in_array($platform, $allowedPlatforms, true) || !in_array($tone, $allowedTones, true)) {
                throw new InvalidArgumentException('Nền tảng hoặc giọng điệu không hợp lệ.');
            }
            $campaign = WorkflowIntegrity::requireOpenCampaign($db, $campaignId);
            if (!$campaign) {
                throw new InvalidArgumentException('Brief này không còn hoạt động.');
            }

            $cleanObjective = mb_substr($objective, 0, 600);
            $lowerText = mb_strtolower($cleanObjective . ' ' . $campaign['brief']);
            $riskPatterns = ['cam kết đậu', 'đảm bảo việc làm', '100%', 'tốt nhất việt nam', 'học phí rẻ nhất'];
            $warnings = [];
            foreach ($riskPatterns as $phrase) {
                if (str_contains($lowerText, $phrase)) {
                    $warnings[] = 'Tránh tuyên bố tuyệt đối: “' . $phrase . '”. Hãy chuyển thành trải nghiệm có dẫn chứng.';
                }
            }
            $brandScore = max(55, 96 - (count($warnings) * 14));
            if (!$warnings) {
                $warnings[] = 'Không phát hiện cam kết tuyệt đối. Vẫn cần kiểm tra lại số liệu học phí và tuyển sinh trước khi đăng.';
            }

            $directions = [
                [
                    'title' => 'Khoảnh khắc phá vỡ định kiến',
                    'format' => 'Story-led | 35 đến 45 giây',
                    'hook' => '“Mình từng nghĩ ' . mb_strtolower($campaign['title']) . ' sẽ rất khác, cho đến khoảnh khắc này.”',
                    'beats' => [
                        '0 đến 4s: Mở bằng một cảnh thật tạo tương phản với điều người xem thường nghĩ.',
                        '5 đến 22s: Kể trải nghiệm cụ thể: ' . $cleanObjective,
                        '23 đến 35s: Cho thấy một chi tiết trong brief bằng hình ảnh, không chỉ lời kể.',
                    ],
                    'cta' => 'Bạn đang tò mò điều gì nhất về trải nghiệm này? Để lại câu hỏi, mình sẽ trả lời bằng trải nghiệm thật.',
                ],
                [
                    'title' => 'Ba lát cắt trong một ngày',
                    'format' => 'Listicle có câu chuyện | 30 đến 40 giây',
                    'hook' => '“Ba khoảnh khắc nhỏ khiến mình hiểu rõ hơn về cuộc sống ở CMC.”',
                    'beats' => [
                        '0 đến 3s: Montage nhanh ba cảnh, mỗi cảnh gắn một từ khóa.',
                        '4 đến 25s: Mỗi cảnh giải thích một ý, ưu tiên người thật và không gian thật.',
                        '26 đến 34s: Chốt điều bạn đã học được từ trải nghiệm: ' . $cleanObjective,
                    ],
                    'cta' => 'Lưu video nếu bạn đang tìm hiểu CMC và gửi câu hỏi cho đội ngũ đại sứ sinh viên.',
                ],
                [
                    'title' => 'Một câu hỏi, một câu trả lời thật',
                    'format' => 'Q&A trực diện | 25 đến 35 giây',
                    'hook' => '“Nếu chỉ có 30 giây để trả lời câu hỏi này, đây là điều mình sẽ nói.”',
                    'beats' => [
                        '0 đến 5s: Hiển thị câu hỏi của học sinh lớp 12 trên màn hình.',
                        '6 đến 24s: Trả lời bằng ví dụ cá nhân, kèm một cảnh minh họa tại trường.',
                        '25 đến 32s: Nêu rõ đây là góc nhìn cá nhân và gợi ý nơi xem thông tin chính thức.',
                    ],
                    'cta' => 'Bạn muốn đại sứ ngành nào trả lời tiếp? Bình luận tên ngành bên dưới.',
                ],
            ];
            $schedule = match ($platform) {
                'YouTube Shorts' => 'Thử đăng 19:30 đến 21:00, ưu tiên tiêu đề có câu hỏi rõ ràng.',
                'Reels' => 'Thử đăng 11:30 đến 13:00 hoặc 20:00, dùng ảnh bìa có 3 đến 5 từ.',
                default => 'Thử đăng 19:00 đến 21:30, giữ nhịp cắt gọn trong 3 giây đầu.',
            };
            $result = [
                'campaign' => $campaign['title'],
                'brief' => $campaign['brief'],
                'tone' => $tone,
                'directions' => $directions,
                'hashtags' => ['#CMCUniversity', '#CMCAmbassador', '#CMCLife', '#HocThatChiaSeThat'],
                'schedule' => $schedule,
                'brand_score' => $brandScore,
                'warnings' => $warnings,
            ];
            $statement = $db->prepare('INSERT INTO ai_requests (user_id, campaign_id, objective, platform, tone, response_json, brand_score) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$current['id'], $campaignId, $cleanObjective, $platform, $tone, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $brandScore]);
            json_response(['ok' => true, 'result' => $result]);

        default:
            json_response(['ok' => false, 'message' => 'API không tồn tại.'], 404);
    }
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_response(['ok' => false, 'message' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'Có lỗi xảy ra.'], 422);
}
