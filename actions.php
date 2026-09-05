<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'logout') {
    if (user()) { $db->prepare('UPDATE users SET is_online=0,last_seen_at=NULL WHERE id=?')->execute([user()['id']]); }
    $_SESSION = [];
    session_destroy();
    session_start();
    redirect('index.php?page=login');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verify_csrf();

try {
    switch ($action) {
        case 'login':
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $statement = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $statement->execute([$email]);
            $account = $statement->fetch();
            if (!$account || !password_verify($password, $account['password'])) {
                flash('danger', 'Email hoặc mật khẩu chưa đúng.');
                redirect('index.php?page=login');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            redirect('index.php?page=dashboard');

        case 'create_campaign':
            require_auth(['admin']);
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $brief = trim((string) ($_POST['brief'] ?? ''));
            $deadline = (string) ($_POST['deadline'] ?? '');
            if ($title === '' || $description === '' || $brief === '' || $deadline === '') {
                throw new InvalidArgumentException('Vui lòng điền đủ thông tin chiến dịch.');
            }
            $parsedDeadline = DateTimeImmutable::createFromFormat('!Y-m-d', $deadline);
            if (!$parsedDeadline || $parsedDeadline->format('Y-m-d') !== $deadline || $deadline < date('Y-m-d') || !in_array($_POST['status'] ?? '', ['active','draft'], true)) {
                throw new InvalidArgumentException('Ngày kết thúc hoặc trạng thái chiến dịch không hợp lệ.');
            }
            $statement = $db->prepare('INSERT INTO campaigns (title, description, brief, platform, reward_points, status, deadline, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$title, $description, $brief, trim((string) $_POST['platform']), max(0, (int) $_POST['reward_points']), (string) $_POST['status'], $deadline, user()['id']]);
            flash('success', 'Đã tạo chiến dịch mới.');
            redirect('index.php?page=admin-campaigns');

        case 'update_campaign_status':
            require_auth(['admin']);
            $campaignId=(int)($_POST['campaign_id']??0);
            $status=(string)($_POST['status']??'');
            if (!in_array($status,['active','draft','closed'],true)) { throw new InvalidArgumentException('Trạng thái không hợp lệ.'); }
            $campaign=rows('SELECT * FROM campaigns WHERE id=?',[$campaignId])[0]??null;
            if (!$campaign || ($status==='active' && $campaign['deadline']<date('Y-m-d'))) { throw new InvalidArgumentException('Không thể mở chiến dịch không tồn tại hoặc đã quá hạn.'); }
            $db->prepare('UPDATE campaigns SET status=? WHERE id=?')->execute([$status,$campaignId]);
            flash('success','Đã cập nhật trạng thái chiến dịch.');
            redirect('index.php?page=admin-campaigns');

        case 'set_login_theme':
            require_auth(['admin']);
            $theme = (string) ($_POST['login_theme'] ?? '');
            if (!array_key_exists($theme, login_theme_registry())) {
                throw new InvalidArgumentException('Giao diện đăng nhập không hợp lệ.');
            }
            $statement = $db->prepare(<<<'SQL'
                INSERT INTO ui_settings (key, value, updated_at)
                VALUES ('login_theme', ?, CURRENT_TIMESTAMP)
                ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP
            SQL);
            $statement->execute([$theme]);
            flash('success', 'Đã đổi giao diện đăng nhập.');
            redirect('index.php?page=appearance-studio');

        case 'save_super_admin_ai':
            require_super_admin();
            $registry = AiProviderManager::registry();
            $activeProvider = (string) ($_POST['widget_ai_provider'] ?? '');
            if (!isset($registry[$activeProvider])) {
                throw new InvalidArgumentException('Provider AI không hợp lệ.');
            }
            $colors = [];
            foreach (['primary', 'navy', 'soft', 'accent'] as $colorKey) {
                $value = mb_strtolower(trim((string) ($_POST['widget_theme_' . $colorKey] ?? '')));
                if (preg_match('/^#[0-9a-f]{6}$/', $value) !== 1) {
                    throw new InvalidArgumentException('Màu theme phải ở định dạng #RRGGBB.');
                }
                $colors[$colorKey] = $value;
            }
            $settingsToSave = [
                'widget_ai_enabled' => isset($_POST['widget_ai_enabled']) ? '1' : '0',
                'widget_ai_provider' => $activeProvider,
                'widget_ai_name' => mb_substr(trim((string) ($_POST['widget_ai_name'] ?? 'CMC AI')), 0, 60),
                'widget_ai_welcome' => mb_substr(trim((string) ($_POST['widget_ai_welcome'] ?? '')), 0, 500),
                'widget_ai_rules' => mb_substr(trim((string) ($_POST['widget_ai_rules'] ?? '')), 0, 3000),
                'widget_theme_primary' => $colors['primary'],
                'widget_theme_navy' => $colors['navy'],
                'widget_theme_soft' => $colors['soft'],
                'widget_theme_accent' => $colors['accent'],
            ];
            if ($settingsToSave['widget_ai_name'] === '' || $settingsToSave['widget_ai_welcome'] === '' || $settingsToSave['widget_ai_rules'] === '') {
                throw new InvalidArgumentException('Tên trợ lý, lời chào và rule không được để trống.');
            }
            $providerInput = is_array($_POST['providers'] ?? null) ? $_POST['providers'] : [];
            $db->beginTransaction();
            $settingStatement = $db->prepare("INSERT INTO ui_settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
            foreach ($settingsToSave as $key => $value) {
                $settingStatement->execute([$key, $value]);
            }
            foreach ($registry as $provider => $preset) {
                $input = is_array($providerInput[$provider] ?? null) ? $providerInput[$provider] : [];
                AiProviderManager::save(
                    $provider,
                    (string) ($input['endpoint'] ?? $preset['endpoint']),
                    (string) ($input['model'] ?? $preset['model']),
                    (string) ($input['api_key'] ?? ''),
                    isset($input['enabled']),
                    isset($input['clear_key']),
                    (int) user()['id']
                );
            }
            $db->commit();
            flash('success', 'Đã lưu cấu hình AI và theme widget.');
            redirect('index.php?page=super-admin');

        case 'test_ai_provider':
            require_super_admin();
            $provider = (string) ($_POST['provider'] ?? '');
            if (!isset(AiProviderManager::registry()[$provider])) {
                throw new InvalidArgumentException('Provider AI không hợp lệ.');
            }
            $test = AiProviderManager::test($provider);
            flash($test['ok'] ? 'success' : 'danger', $test['message']);
            redirect('index.php?page=super-admin#providers');

        case 'add_ai_provider_key':
            require_super_admin();
            $provider = (string) ($_POST['provider'] ?? 'gemini');
            AiProviderManager::addKey(
                $provider,
                (string) ($_POST['key_label'] ?? ''),
                (string) ($_POST['api_key'] ?? ''),
                (int) user()['id']
            );
            flash('success', 'Đã thêm API key vào vòng xoay ' . (AiProviderManager::registry()[$provider]['name'] ?? $provider) . '.');
            redirect('index.php?page=super-admin#providers');

        case 'toggle_ai_provider_key':
            require_super_admin();
            AiProviderManager::toggleKey((int) ($_POST['key_id'] ?? 0));
            flash('success', 'Đã cập nhật trạng thái API key.');
            redirect('index.php?page=super-admin#providers');

        case 'test_ai_provider_key':
            require_super_admin();
            $test = AiProviderManager::testKey((int) ($_POST['key_id'] ?? 0));
            flash($test['ok'] ? 'success' : 'danger', $test['message']);
            redirect('index.php?page=super-admin#providers');

        case 'delete_ai_provider_key':
            require_super_admin();
            AiProviderManager::removeKey((int) ($_POST['key_id'] ?? 0));
            flash('success', 'Đã xóa API key khỏi vòng xoay.');
            redirect('index.php?page=super-admin#providers');

        case 'save_ai_knowledge':
            require_super_admin();
            $id = (int) ($_POST['knowledge_id'] ?? 0);
            $category = mb_substr(trim((string) ($_POST['category'] ?? '')), 0, 80);
            $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 180);
            $content = mb_substr(trim((string) ($_POST['content'] ?? '')), 0, 5000);
            $keywords = mb_substr(trim((string) ($_POST['keywords'] ?? '')), 0, 500);
            if (mb_strlen($category) < 2 || mb_strlen($title) < 4 || mb_strlen($content) < 20) {
                throw new InvalidArgumentException('Hãy nhập đầy đủ danh mục, tiêu đề và nội dung kiến thức.');
            }
            if ($id > 0) {
                $statement = $db->prepare('UPDATE ai_knowledge_entries SET category = ?, title = ?, content = ?, keywords = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $statement->execute([$category, $title, $content, $keywords, user()['id'], $id]);
            } else {
                $statement = $db->prepare('INSERT INTO ai_knowledge_entries (category, title, content, keywords, updated_by) VALUES (?, ?, ?, ?, ?)');
                $statement->execute([$category, $title, $content, $keywords, user()['id']]);
            }
            flash('success', 'Đã lưu nội dung. Hãy xác nhận nguồn và thời hạn trước khi cho chatbot sử dụng.');
            redirect('index.php?page=ambassador-program&tab=knowledge');

        case 'toggle_ai_knowledge':
            require_super_admin();
            $id = (int) ($_POST['knowledge_id'] ?? 0);
            $db->prepare('UPDATE ai_knowledge_entries SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([user()['id'], $id]);
            flash('success', 'Đã cập nhật trạng thái dữ liệu gốc.');
            redirect('index.php?page=super-admin#knowledge');

        case 'submit_content':
            require_auth(['student', 'ambassador']);
            $campaignId = (int) ($_POST['campaign_id'] ?? 0);
            $url = trim((string) ($_POST['content_url'] ?? ''));
            if ($campaignId < 1 || !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Vui lòng nhập đường dẫn nội dung hợp lệ.');
            }
            $campaign = WorkflowIntegrity::requireOpenCampaign($db, $campaignId);
            if (!$campaign) {
                throw new RuntimeException('Chiến dịch không còn hoạt động.');
            }
            $platform = trim((string) ($_POST['platform'] ?? '')) ?: (string) $campaign['platform'];
            $statement = $db->prepare('INSERT INTO submissions (campaign_id, user_id, content_url, caption, platform) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$campaignId, user()['id'], $url, trim((string) ($_POST['caption'] ?? '')), $platform]);
            flash('success', 'Bài của bạn đã được gửi và đang chờ duyệt.');
            redirect('index.php?page=my-submissions');

        case 'submit_blog':
            require_auth(['ambassador']);
            $campaignId = (int) ($_POST['campaign_id'] ?? 0);
            $title = trim((string) ($_POST['blog_title'] ?? ''));
            $excerpt = trim((string) ($_POST['blog_excerpt'] ?? ''));
            $body = trim((string) ($_POST['blog_body'] ?? ''));
            $sourceUrl = trim((string) ($_POST['content_url'] ?? ''));
            if ($campaignId < 1 || mb_strlen($title) < 8 || mb_strlen($excerpt) < 20 || mb_strlen($body) < 120) {
                throw new InvalidArgumentException('Hãy chọn brief và viết tiêu đề, phần giới thiệu, nội dung blog đầy đủ hơn.');
            }
            if ($sourceUrl !== '' && !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Đường dẫn tham khảo chưa hợp lệ.');
            }
            $campaign = WorkflowIntegrity::requireOpenCampaign($db, $campaignId);
            if (!$campaign) {
                throw new RuntimeException('Brief này không còn hoạt động.');
            }
            $statement = $db->prepare("INSERT INTO submissions (campaign_id, user_id, content_url, caption, platform, content_type, blog_title, blog_excerpt, blog_body) VALUES (?, ?, '', ?, 'Blog', 'blog', ?, ?, ?)");
            $statement->execute([$campaignId, user()['id'], $excerpt, $title, $excerpt, $body]);
            $submissionId = (int) $db->lastInsertId();
            $contentUrl = $sourceUrl !== '' ? $sourceUrl : 'index.php?page=widget#content-' . $submissionId;
            $db->prepare('UPDATE submissions SET content_url = ? WHERE id = ?')->execute([$contentUrl, $submissionId]);
            flash('success', 'Bài blog đã được gửi và sẽ xuất hiện trong widget sau khi được duyệt.');
            redirect('index.php?page=my-submissions');

        case 'review_submission':
            require_auth(['admin']);
            $submissionId = (int) ($_POST['submission_id'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['approved', 'rejected'], true) ? (string) $_POST['status'] : 'pending';
            $submission = rows('SELECT s.*, c.reward_points, u.ambassador_tier FROM submissions s JOIN campaigns c ON c.id = s.campaign_id JOIN users u ON u.id = s.user_id WHERE s.id = ?', [$submissionId])[0] ?? null;
            if (!$submission) {
                throw new RuntimeException('Không tìm thấy bài nộp.');
            }
            $db->beginTransaction();
            $views = max(0, (int) ($_POST['views'] ?? 0));
            $likes = max(0, (int) ($_POST['likes'] ?? 0));
            $comments = max(0, (int) ($_POST['comments'] ?? 0));
            $shares = max(0, (int) ($_POST['shares'] ?? 0));
            $aiScore = max(0, min(100, (int) ($_POST['ai_score'] ?? 0)));
            $bonusPoints = ($views >= 10000 || $comments >= 100 || $aiScore >= 85) ? 40 : 0;
            $statement = $db->prepare('UPDATE submissions SET status = ?, feedback = ?, views = ?, likes = ?, comments = ?, shares = ?, ai_score = ?, bonus_points = ? WHERE id = ?');
            $statement->execute([$status, trim((string) ($_POST['feedback'] ?? '')), $views, $likes, $comments, $shares, $aiScore, $status === 'approved' ? $bonusPoints : 0, $submissionId]);
            $multiplier = $submission['ambassador_tier'] === 'senior' ? 1.3 : 1.0;
            $awardedPoints = (int) round(((int) $submission['reward_points'] + $bonusPoints) * $multiplier);
            WorkflowIntegrity::reconcileReward($db, $submission, $status, $awardedPoints);
            $db->commit();
            flash('success', 'Đã cập nhật kết quả duyệt bài.');
            redirect('index.php?page=admin-submissions');

        case 'update_content_metrics':
            require_auth(['admin']);
            $submissionId = (int) ($_POST['submission_id'] ?? 0);
            $statement = $db->prepare('UPDATE submissions SET views = ?, likes = ?, comments = ?, shares = ? WHERE id = ? AND status = ?');
            $statement->execute([
                max(0, (int) ($_POST['views'] ?? 0)),
                max(0, (int) ($_POST['likes'] ?? 0)),
                max(0, (int) ($_POST['comments'] ?? 0)),
                max(0, (int) ($_POST['shares'] ?? 0)),
                $submissionId,
                'approved',
            ]);
            if ($statement->rowCount() < 1) {
                throw new RuntimeException('Không tìm thấy nội dung đã duyệt.');
            }
            flash('success', 'Đã cập nhật chỉ số nội dung.');
            redirect('index.php?page=admin-performance');

        case 'update_ambassador':
            require_auth(['admin']);
            $id = (int) ($_POST['user_id'] ?? 0);
            $role = ($_POST['role'] ?? '') === 'ambassador' ? 'ambassador' : 'student';
            $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
            $statement = $db->prepare("UPDATE users SET role = ?, status = ? WHERE id = ? AND role IN ('student','ambassador')");
            $statement->execute([$role, $status, $id]);
            flash('success', 'Đã cập nhật hồ sơ đại sứ.');
            redirect('index.php?page=admin-ambassadors');

        case 'update_ambassador_profile':
            require_auth(['admin']);
            $id = (int) ($_POST['user_id'] ?? 0);
            $tier = in_array($_POST['ambassador_tier'] ?? '', ['junior', 'senior'], true) ? (string) $_POST['ambassador_tier'] : 'junior';
            $policy = in_array($_POST['policy_status'] ?? '', ['pending', 'approved', 'suspended'], true) ? (string) $_POST['policy_status'] : 'pending';
            $violation = in_array($_POST['violation_level'] ?? '', ['none', 'yellow', 'orange', 'red'], true) ? (string) $_POST['violation_level'] : 'none';
            $gpa = max(0, min(4, (float) ($_POST['gpa'] ?? 0)));
            $followers = max(0, (int) ($_POST['followers'] ?? 0));
            $statement = $db->prepare("UPDATE users SET ambassador_tier = ?, policy_status = ?, violation_level = ?, gpa = ?, followers = ? WHERE id = ? AND role IN ('student','ambassador')");
            $statement->execute([$tier, $policy, $violation, $gpa, $followers, $id]);
            flash('success', 'Đã cập nhật phân hạng và trạng thái chính sách.');
            redirect('index.php?page=admin-rewards');

        case 'update_support_status':
            require_auth(['admin']);
            $conversationId = (int) ($_POST['conversation_id'] ?? 0);
            $crmStatus = in_array($_POST['support_status'] ?? '', ['new', 'active', 'resolved'], true) ? (string) $_POST['support_status'] : 'new';
            $statement = $db->prepare('UPDATE conversations SET crm_status = ? WHERE id = ?');
            $statement->execute([$crmStatus, $conversationId]);
            flash('success', 'Đã cập nhật trạng thái hỗ trợ.');
            redirect('index.php?page=admin-moderation&conversation=' . $conversationId);

        case 'update_appointment_status':
            require_auth(['admin']);
            $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['pending', 'confirmed', 'completed', 'cancelled'], true) ? (string) $_POST['status'] : 'pending';
            $statement = $db->prepare('UPDATE consultation_appointments SET status = ? WHERE id = ?');
            $statement->execute([$status, $appointmentId]);
            flash('success', 'Đã cập nhật lịch tư vấn.');
            redirect('index.php?page=admin-widget');

        case 'flag_message':
            require_auth(['admin']);
            $id = (int) ($_POST['message_id'] ?? 0);
            $statement = $db->prepare('UPDATE messages SET is_flagged = CASE WHEN is_flagged = 1 THEN 0 ELSE 1 END WHERE id = ?');
            $statement->execute([$id]);
            $conversationId = (int) scalar('SELECT conversation_id FROM messages WHERE id=?', [$id]);
            if ($conversationId) { WorkflowIntegrity::quality($db, $conversationId); }
            flash('success', 'Đã cập nhật trạng thái kiểm duyệt.');
            redirect('index.php?page=admin-moderation&conversation=' . $conversationId);

        case 'escalate_question':
            require_auth(['ambassador']);
            $conversationId = (int) ($_POST['conversation_id'] ?? 0);
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $conversation = rows('SELECT * FROM conversations WHERE id = ? AND ambassador_id = ?', [$conversationId, user()['id']])[0] ?? null;
            if (!$conversation) {
                throw new InvalidArgumentException('Cuộc trò chuyện không tồn tại.');
            }
            if (($conversation['escalation_status']??'')==='pending') { throw new InvalidArgumentException('Hội thoại đã có yêu cầu chuyển tuyến đang chờ.'); }
            $db->beginTransaction();
            $db->prepare('INSERT INTO program_audit(actor_id,entity,entity_id,action,snapshot) VALUES(?,?,?,?,?)')->execute([user()['id'],'conversation',$conversationId,'escalation_requested',json_encode(['previous_reason'=>$conversation['escalation_reason']??null,'previous_answer'=>$conversation['official_answer']??null,'reason'=>$reason],JSON_UNESCAPED_UNICODE)]);
            $statement = $db->prepare("UPDATE conversations SET is_escalated = 1, escalation_reason = ?, escalation_status = 'pending' WHERE id = ?");
            $statement->execute([$reason !== '' ? $reason : 'Cần cán bộ tuyển sinh xác nhận chính sách/học phí/học bổng.', $conversationId]);
            $db->commit();
            flash('success', 'Đã chuyển tuyến câu hỏi đến Ban Tuyển sinh.');
            redirect('index.php?page=inbox&conversation=' . $conversationId);

        case 'answer_escalated_question':
            require_auth(['admin']);
            $conversationId = (int) ($_POST['conversation_id'] ?? 0);
            $officialAnswer = trim((string) ($_POST['official_answer'] ?? ''));
            if ($officialAnswer === '') {
                throw new InvalidArgumentException('Vui lòng nhập nội dung xác nhận chính thức.');
            }
            $conversation = rows('SELECT * FROM conversations WHERE id = ?', [$conversationId])[0] ?? null;
            if (!$conversation) {
                throw new InvalidArgumentException('Cuộc trò chuyện không tồn tại.');
            }
            if (empty($conversation['is_escalated']) || $conversation['escalation_status'] !== 'pending') {
                throw new InvalidArgumentException('Yêu cầu đã được trả lời hoặc chưa được chuyển tuyến.');
            }
            $db->beginTransaction();
            $statement = $db->prepare("UPDATE conversations SET official_answer = ?, answered_by = ?, answered_at = CURRENT_TIMESTAMP, escalation_status = 'answered', crm_status = 'active' WHERE id = ? AND escalation_status='pending'");
            $statement->execute([$officialAnswer, user()['id'], $conversationId]);
            if ($statement->rowCount() !== 1) { throw new InvalidArgumentException('Yêu cầu vừa được xử lý. Hãy tải lại hội thoại.'); }

            $msgStmt = $db->prepare("INSERT INTO messages (conversation_id, sender_id, content, is_flagged, moderation_provider) VALUES (?, ?, ?, 0, 'official')");
            $msgStmt->execute([$conversationId, user()['id'], "【Xác nhận từ Ban Tuyển sinh CMC】\n" . $officialAnswer]);
            WorkflowIntegrity::quality($db, $conversationId, true);
            $db->commit();
            flash('success', 'Đã gửi xác nhận chính thức từ Ban Tuyển sinh.');
            redirect('index.php?page=admin-moderation&conversation=' . $conversationId);

        default:
            throw new InvalidArgumentException('Thao tác không hợp lệ.');
    }
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    flash('danger', $error instanceof InvalidArgumentException ? $error->getMessage() : 'Có lỗi xảy ra. Vui lòng thử lại.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
}
