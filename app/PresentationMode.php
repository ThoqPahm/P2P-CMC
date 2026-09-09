<?php

declare(strict_types=1);

/**
 * Isolated Chapter 4 presentation support.
 *
 * The presentation runs with its own PHP session and a disposable SQLite
 * database. It never changes the signed-in account or the production database.
 */
final class PresentationMode
{
    private const LIFETIME_SECONDS = 14400;

    public static function issue(int $adminId): string
    {
        self::cleanupExpiredDatabases();
        $payload = self::encode(json_encode([
            'admin_id' => $adminId,
            'expires' => time() + self::LIFETIME_SECONDS,
            'nonce' => bin2hex(random_bytes(12)),
        ], JSON_THROW_ON_ERROR));
        return $payload . '.' . self::encode(hash_hmac('sha256', $payload, self::key(), true));
    }

    public static function verify(string $token): ?array
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');
        $expected = self::encode(hash_hmac('sha256', $payload, self::key(), true));
        if ($payload === '' || $signature === '' || !hash_equals($expected, $signature)) {
            return null;
        }
        $decoded = self::decode($payload);
        $data = is_string($decoded) ? json_decode($decoded, true) : null;
        if (!is_array($data) || (int)($data['expires'] ?? 0) < time() || !preg_match('/^[a-f0-9]{24}$/', (string)($data['nonce'] ?? ''))) {
            return null;
        }
        return $data;
    }

    public static function bindDatabase(string $nonce): PDO
    {
        $directory = dirname(__DIR__) . '/tmp/presentation';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Không thể tạo vùng dữ liệu trình diễn.');
        }
        $path = $directory . '/chapter-4-' . $nonce . '.sqlite';
        $database = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $database->exec('PRAGMA foreign_keys = ON');

        $property = new ReflectionProperty(Database::class, 'connection');
        $property->setValue(null, $database);
        $migrate = new ReflectionMethod(Database::class, 'migrate');
        $migrate->invoke(null, $database);
        return $database;
    }

    public static function seed(PDO $database): void
    {
        $database->beginTransaction();
        try {
            $database->exec("UPDATE users SET is_online=0");
            $database->exec("UPDATE users SET is_online=1, last_seen_at=CURRENT_TIMESTAMP, major='Digital Marketing', interests='Sáng tạo nội dung, nghiên cứu khách hàng, câu lạc bộ', bio='Đại sứ năm 3, sẵn sàng chia sẻ thật về việc học và cách thích nghi với ngành Digital Marketing.' WHERE email='ambassador@cmc.edu.vn'");
            $database->exec("UPDATE users SET major='Digital Marketing' WHERE email='maithu@example.com'");

            $campaign = $database->prepare("UPDATE campaigns SET title=?, description=?, brief=?, platform='TikTok / YouTube Shorts', reward_points=80, status='active', deadline=date('now','+35 days') WHERE id=2");
            $campaign->execute([
                'Một ngày đi học ngành Digital Marketing',
                'Giúp học sinh THPT hình dung nhịp học thực tế qua góc nhìn của một sinh viên.',
                'Video dọc 45-60 giây, gồm giờ học, bài tập nhóm và một điều bạn từng thấy khó khi mới vào ngành. Ưu tiên trải nghiệm thật; thông tin học phí, học bổng hoặc xét tuyển phải dẫn nguồn chính thức.',
            ]);

            $database->exec("UPDATE submissions SET caption='Một ngày học Digital Marketing dưới góc nhìn sinh viên', status='approved', feedback='Nội dung tự nhiên, đúng brief và phân biệt rõ trải nghiệm cá nhân.', platform='TikTok / YouTube Shorts', views=18400, likes=1290, comments=86, shares=94 WHERE id=2");

            $exists = $database->prepare('SELECT id FROM submissions WHERE blog_title=? LIMIT 1');
            $title = 'Một ngày học Digital Marketing tại CMC diễn ra như thế nào?';
            $exists->execute([$title]);
            if (!$exists->fetchColumn()) {
                $insert = $database->prepare("INSERT INTO submissions (campaign_id,user_id,content_url,caption,status,feedback,platform,views,likes,comments,shares,content_type,blog_title,blog_excerpt,blog_body) VALUES (2,3,'',?,'approved',?,'Bài viết',2350,186,0,0,'blog',?,?,?)");
                $insert->execute([
                    'Góc nhìn thật về giờ học, bài tập nhóm và cách một sinh viên hướng nội thích nghi.',
                    'Nội dung gần gũi, đúng phạm vi trải nghiệm cá nhân.',
                    $title,
                    'Từ giờ học đến bài tập nhóm, đây là nhịp học thường ngày dưới góc nhìn của một sinh viên Digital Marketing.',
                    "Một ngày học của mình thường bắt đầu bằng giờ học về khách hàng, nội dung hoặc dữ liệu. Mình không phải người nói nhiều từ đầu, nên những buổi thảo luận nhóm từng khá áp lực.\n\nSau một thời gian, mình học cách chuẩn bị ý trước, nhận phần việc phù hợp và trình bày từng bước. Ngành vẫn cần làm việc với người khác, nhưng hướng nội không có nghĩa là không phù hợp.\n\nĐây là trải nghiệm cá nhân của mình. Với học phí, học bổng hoặc điều kiện xét tuyển, bạn nên xem nguồn chính thức hoặc nhờ cán bộ phụ trách xác nhận.",
                ]);
            }

            $conversation = $database->prepare("UPDATE conversations SET prospect_id=(SELECT id FROM users WHERE email='maithu@example.com'), ambassador_id=(SELECT id FROM users WHERE email='ambassador@cmc.edu.vn'), status='open', quality_score=88, crm_status='active', is_escalated=1, escalation_reason=?, escalation_status='pending', official_answer=NULL, answered_by=NULL, answered_at=NULL WHERE id=1");
            $conversation->execute(['Học sinh hỏi: Với kết quả học tập hiện tại, em có chắc chắn nhận được học bổng không?']);

            $database->exec('DELETE FROM messages WHERE conversation_id=1');
            $message = $database->prepare("INSERT INTO messages (conversation_id,sender_id,content,is_flagged,moderation_provider,moderation_categories,moderation_confidence,moderation_reason) VALUES (1,?,?,0,'local','[]',0,'')");
            $prospectId = (int)$database->query("SELECT id FROM users WHERE email='maithu@example.com'")->fetchColumn();
            $ambassadorId = (int)$database->query("SELECT id FROM users WHERE email='ambassador@cmc.edu.vn'")->fetchColumn();
            $message->execute([$prospectId, 'Em là người hướng nội, liệu học Digital Marketing có phù hợp không ạ?']);
            $message->execute([$ambassadorId, 'Có em nhé. Ngành có phần phân tích số liệu và nhiều bài tập nhóm, nhưng người hướng nội vẫn có lợi thế ở khả năng quan sát, lắng nghe và chuẩn bị nội dung kỹ. Chị cũng từng ít nói và đã quen dần qua từng bài tập.']);
            $message->execute([$prospectId, 'Với kết quả học tập hiện tại, em có chắc chắn nhận được học bổng không ạ?']);
            $database->exec("UPDATE conversations SET last_message_at=CURRENT_TIMESTAMP WHERE id=1");
            $database->commit();
        } catch (Throwable $error) {
            $database->rollBack();
            throw $error;
        }
    }

    public static function setStage(PDO $database, string $stage): void
    {
        if ($stage === 'ambassador-handoff') {
            $database->exec("UPDATE conversations SET is_escalated=0, escalation_reason=NULL, escalation_status='none', official_answer=NULL WHERE id=1");
        } elseif ($stage === 'admin-review') {
            $statement = $database->prepare("UPDATE conversations SET is_escalated=1, escalation_reason=?, escalation_status='pending', official_answer=NULL WHERE id=1");
            $statement->execute(['Học sinh hỏi: Với kết quả học tập hiện tại, em có chắc chắn nhận được học bổng không?']);
        }
    }

    public static function userId(PDO $database, string $role): int
    {
        $email = match ($role) {
            'admin' => 'admin@cmc.edu.vn',
            'ambassador' => 'ambassador@cmc.edu.vn',
            'student' => 'student@cmc.edu.vn',
            'prospect' => 'maithu@example.com',
            default => '',
        };
        if ($email === '') return 0;
        $statement = $database->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");
        $statement->execute([$email]);
        return (int)$statement->fetchColumn();
    }

    private static function key(): string
    {
        $configured = trim((string)getenv('PRESENTATION_SECRET'));
        if ($configured !== '') return hash('sha256', $configured, true);
        $directory = dirname(__DIR__) . '/tmp/presentation';
        if (!is_dir($directory)) mkdir($directory, 0770, true);
        $path = $directory . '/.signing-key';
        if (!is_file($path)) {
            file_put_contents($path, bin2hex(random_bytes(32)), LOCK_EX);
            @chmod($path, 0600);
        }
        return hash('sha256', trim((string)file_get_contents($path)), true);
    }

    private static function cleanupExpiredDatabases(): void
    {
        $directory = dirname(__DIR__) . '/tmp/presentation';
        if (!is_dir($directory)) return;
        $cutoff = time() - 86400;
        foreach (new DirectoryIterator($directory) as $file) {
            if (!$file->isFile() || $file->getMTime() >= $cutoff) continue;
            if (!preg_match('/^chapter-4-[a-f0-9]{24}\.sqlite(?:-wal|-shm)?$/', $file->getFilename())) continue;
            @unlink($file->getPathname());
        }
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
