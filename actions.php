<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'logout') {
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
            $statement = $db->prepare('INSERT INTO campaigns (title, description, brief, platform, reward_points, status, deadline, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$title, $description, $brief, trim((string) $_POST['platform']), max(0, (int) $_POST['reward_points']), (string) $_POST['status'], $deadline, user()['id']]);
            flash('success', 'Đã tạo chiến dịch mới.');
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

        case 'submit_content':
            require_auth(['student', 'ambassador']);
            $campaignId = (int) ($_POST['campaign_id'] ?? 0);
            $url = trim((string) ($_POST['content_url'] ?? ''));
            if ($campaignId < 1 || !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Vui lòng nhập đường dẫn nội dung hợp lệ.');
            }
            $campaign = rows('SELECT platform FROM campaigns WHERE id = ? AND status = ?', [$campaignId, 'active'])[0] ?? null;
            if (!$campaign) {
                throw new RuntimeException('Chiến dịch không còn hoạt động.');
            }
            $platform = trim((string) ($_POST['platform'] ?? '')) ?: (string) $campaign['platform'];
            $statement = $db->prepare('INSERT INTO submissions (campaign_id, user_id, content_url, caption, platform) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$campaignId, user()['id'], $url, trim((string) ($_POST['caption'] ?? '')), $platform]);
            flash('success', 'Bài của bạn đã được gửi và đang chờ duyệt.');
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
            if ($status === 'approved' && $submission['status'] !== 'approved') {
                $multipliers = ['junior' => 1.0, 'senior' => 1.3];
                $multiplier = $multipliers[$submission['ambassador_tier']] ?? 1.0;
                $awardedPoints = (int) round(((int) $submission['reward_points'] + $bonusPoints) * $multiplier);
                $wallet = $db->prepare("INSERT INTO wallet_transactions (user_id, type, points, description, reference_type, reference_id) VALUES (?, 'credit', ?, 'Bài nộp UGC được duyệt', 'submission', ?)");
                $wallet->execute([$submission['user_id'], $awardedPoints, $submissionId]);
            }
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

        case 'flag_message':
            require_auth(['admin']);
            $id = (int) ($_POST['message_id'] ?? 0);
            $statement = $db->prepare('UPDATE messages SET is_flagged = CASE WHEN is_flagged = 1 THEN 0 ELSE 1 END WHERE id = ?');
            $statement->execute([$id]);
            flash('success', 'Đã cập nhật trạng thái kiểm duyệt.');
            redirect('index.php?page=admin-moderation');

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
