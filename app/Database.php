<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dataDirectory = dirname(__DIR__) . '/data';
        if (!is_dir($dataDirectory)) {
            mkdir($dataDirectory, 0775, true);
        }

        self::$connection = new PDO('sqlite:' . $dataDirectory . '/p2p_cmc.sqlite');
        self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$connection->exec('PRAGMA foreign_keys = ON');
        self::migrate(self::$connection);

        return self::$connection;
    }

    private static function migrate(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role TEXT NOT NULL CHECK(role IN ('admin','student','ambassador','prospect')),
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                student_code TEXT,
                major TEXT,
                hometown TEXT,
                interests TEXT,
                bio TEXT,
                avatar TEXT,
                study_year INTEGER,
                is_online INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'active',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS campaigns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NOT NULL,
                brief TEXT NOT NULL,
                platform TEXT NOT NULL DEFAULT 'TikTok / Reels',
                reward_points INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'active',
                deadline TEXT NOT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(created_by) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                content_url TEXT NOT NULL,
                caption TEXT,
                status TEXT NOT NULL DEFAULT 'pending',
                feedback TEXT,
                submitted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(campaign_id) REFERENCES campaigns(id),
                FOREIGN KEY(user_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS wallet_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL CHECK(type IN ('credit','debit')),
                points INTEGER NOT NULL,
                description TEXT NOT NULL,
                reference_type TEXT,
                reference_id INTEGER,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS conversations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                prospect_id INTEGER NOT NULL,
                ambassador_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'open',
                rating INTEGER,
                last_message_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(prospect_id) REFERENCES users(id),
                FOREIGN KEY(ambassador_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL,
                sender_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                is_flagged INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(conversation_id) REFERENCES conversations(id),
                FOREIGN KEY(sender_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS consultation_appointments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ambassador_id INTEGER NOT NULL,
                student_name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT,
                preferred_at TEXT NOT NULL,
                question TEXT,
                status TEXT NOT NULL DEFAULT 'pending',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(ambassador_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS widget_access_tokens (
                token TEXT PRIMARY KEY,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS ai_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                campaign_id INTEGER,
                objective TEXT NOT NULL,
                platform TEXT NOT NULL,
                tone TEXT NOT NULL,
                response_json TEXT NOT NULL,
                brand_score INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id),
                FOREIGN KEY(campaign_id) REFERENCES campaigns(id)
            );

            CREATE TABLE IF NOT EXISTS ui_settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS ai_provider_configs (
                provider TEXT PRIMARY KEY CHECK(provider IN ('gemini','deepseek','glm','qwen')),
                endpoint TEXT NOT NULL,
                model TEXT NOT NULL,
                api_key_encrypted TEXT NOT NULL DEFAULT '',
                enabled INTEGER NOT NULL DEFAULT 1,
                last_test_status TEXT NOT NULL DEFAULT 'untested',
                last_test_message TEXT,
                last_tested_at TEXT,
                updated_by INTEGER,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(updated_by) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS ai_provider_keys (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                provider TEXT NOT NULL CHECK(provider IN ('gemini','deepseek','glm','qwen')),
                label TEXT NOT NULL,
                api_key_encrypted TEXT NOT NULL,
                key_suffix TEXT NOT NULL DEFAULT '',
                enabled INTEGER NOT NULL DEFAULT 1,
                use_count INTEGER NOT NULL DEFAULT 0,
                failure_count INTEGER NOT NULL DEFAULT 0,
                cooldown_until TEXT,
                last_status TEXT NOT NULL DEFAULT 'untested',
                last_message TEXT,
                last_used_at TEXT,
                last_tested_at TEXT,
                created_by INTEGER,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(created_by) REFERENCES users(id)
            );

            CREATE INDEX IF NOT EXISTS idx_ai_provider_keys_rotation
                ON ai_provider_keys(provider, enabled, use_count, last_used_at);

            CREATE TABLE IF NOT EXISTS ai_knowledge_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category TEXT NOT NULL,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                keywords TEXT NOT NULL DEFAULT '',
                is_active INTEGER NOT NULL DEFAULT 1,
                updated_by INTEGER,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(updated_by) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS widget_ai_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                question TEXT NOT NULL,
                answer TEXT NOT NULL,
                provider TEXT NOT NULL,
                model TEXT NOT NULL,
                knowledge_ids TEXT NOT NULL DEFAULT '[]',
                ambassador_ids TEXT NOT NULL DEFAULT '[]',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        SQL);

        self::addColumn($db, 'users', 'ambassador_tier', "TEXT NOT NULL DEFAULT 'junior'");
        self::addColumn($db, 'users', 'gpa', 'REAL NOT NULL DEFAULT 0');
        self::addColumn($db, 'users', 'followers', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($db, 'users', 'policy_status', "TEXT NOT NULL DEFAULT 'pending'");
        self::addColumn($db, 'users', 'violation_level', "TEXT NOT NULL DEFAULT 'none'");
        self::addColumn($db, 'submissions', 'ai_score', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($db, 'submissions', 'views', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($db, 'submissions', 'likes', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($db, 'submissions', 'comments', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($db, 'submissions', 'shares', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($db, 'submissions', 'platform', "TEXT NOT NULL DEFAULT ''");
        self::addColumn($db, 'submissions', 'bonus_points', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($db, 'submissions', 'content_type', "TEXT NOT NULL DEFAULT 'social'");
        self::addColumn($db, 'submissions', 'blog_title', 'TEXT');
        self::addColumn($db, 'submissions', 'blog_excerpt', 'TEXT');
        self::addColumn($db, 'submissions', 'blog_body', 'TEXT');
        self::addColumn($db, 'conversations', 'quality_score', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($db, 'conversations', 'crm_status', "TEXT NOT NULL DEFAULT 'new'");
        self::addColumn($db, 'conversations', 'public_token', 'TEXT');
        self::addColumn($db, 'messages', 'is_ai', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($db, 'messages', 'moderation_provider', "TEXT NOT NULL DEFAULT 'manual'");
        self::addColumn($db, 'messages', 'moderation_model', 'TEXT');
        self::addColumn($db, 'messages', 'moderation_categories', 'TEXT');
        self::addColumn($db, 'messages', 'moderation_confidence', 'REAL');
        self::addColumn($db, 'messages', 'moderation_reason', 'TEXT');
        self::addColumn($db, 'messages', 'moderated_at', 'TEXT');

        $count = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            self::seed($db);
        }

        $db->exec(<<<'SQL'
            INSERT INTO submissions (
                campaign_id, user_id, content_url, caption, status, feedback, platform,
                content_type, blog_title, blog_excerpt, blog_body
            )
            SELECT
                2, 4, '',
                'Một góc nhìn thật về nhịp học, đồ án và cách chủ động hỏi khi chưa hiểu bài.',
                'approved', 'Bài viết rõ ràng, gần gũi và phù hợp để chia sẻ trong widget.', 'Blog',
                'blog',
                'Một ngày học Công nghệ thông tin tại CMC diễn ra như thế nào?',
                'Từ giờ học, thời gian làm bài đến cách trao đổi với bạn bè, đây là nhịp học thường ngày dưới góc nhìn của một sinh viên CNTT.',
                'Một ngày học của mình thường bắt đầu bằng việc xem lại mục tiêu của buổi học và ghi nhanh những phần còn chưa chắc. Thay vì cố ghi chép mọi thứ, mình tập trung vào ví dụ và cách giảng viên giải quyết từng bài toán.\n\nSau giờ học, mình thường dành một khoảng thời gian để thử lại phần code hoặc hoàn thiện đầu việc của nhóm. Có những hôm tiến độ rất nhanh, cũng có hôm cả nhóm phải dừng lại để tìm nguyên nhân của một lỗi nhỏ. Điều hữu ích nhất là hỏi sớm và mô tả rõ mình đã thử những gì.\n\nNếu bạn đang cân nhắc ngành CNTT, lời khuyên của mình là đừng quá lo vì chưa biết nhiều từ đầu. Sự tò mò, thói quen tự học và khả năng phối hợp với người khác sẽ giúp bạn tiến bộ từng ngày.'
            WHERE EXISTS (SELECT 1 FROM campaigns WHERE id = 2)
              AND EXISTS (SELECT 1 FROM users WHERE id = 4 AND role = 'ambassador')
              AND NOT EXISTS (SELECT 1 FROM submissions WHERE content_type = 'blog' AND blog_title = 'Một ngày học Công nghệ thông tin tại CMC diễn ra như thế nào?')
        SQL);

        $db->exec("UPDATE users SET ambassador_tier = 'senior', gpa = 3.45, followers = 2400, policy_status = 'approved' WHERE email = 'ambassador@cmc.edu.vn'");
        $db->exec("UPDATE users SET ambassador_tier = 'senior', gpa = 3.62, followers = 5100, policy_status = 'approved' WHERE email = 'nam@cmc.edu.vn'");
        $db->exec("UPDATE users SET ambassador_tier = 'junior', gpa = 3.31, followers = 1300, policy_status = 'approved' WHERE email = 'linh@cmc.edu.vn'");
        $db->exec("UPDATE users SET ambassador_tier = 'senior' WHERE ambassador_tier NOT IN ('junior', 'senior')");
        $db->exec("UPDATE users SET gpa = 3.28, followers = 860, policy_status = 'pending' WHERE email = 'student@cmc.edu.vn'");
        $db->exec("UPDATE conversations SET quality_score = 82 WHERE quality_score = 0 AND id = 1");
        $db->exec("UPDATE conversations SET crm_status = 'active' WHERE crm_status NOT IN ('new', 'active', 'resolved')");
        $db->exec("UPDATE submissions SET platform = COALESCE((SELECT platform FROM campaigns WHERE campaigns.id = submissions.campaign_id), 'TikTok / Reels') WHERE platform = ''");
        $db->exec("UPDATE submissions SET views = 18400, likes = 1290, comments = 86, shares = 94 WHERE content_url = 'https://www.youtube.com/shorts/demo' AND views = 0");
        $db->exec("UPDATE wallet_transactions SET description = 'Thưởng hiệu quả nội dung UGC', reference_type = 'submission' WHERE reference_type IS NOT NULL AND reference_type <> 'submission'");

        $db->exec(<<<'SQL'
            INSERT OR IGNORE INTO ai_provider_configs (provider, endpoint, model) VALUES
                ('gemini', 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', 'gemini-2.5-flash'),
                ('deepseek', 'https://api.deepseek.com/chat/completions', 'deepseek-chat'),
                ('glm', 'https://open.bigmodel.cn/api/paas/v4/chat/completions', 'glm-5.2'),
                ('qwen', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions', 'qwen-plus')
        SQL);

        $db->exec(<<<'SQL'
            INSERT INTO ai_provider_keys (provider, label, api_key_encrypted, key_suffix, created_by)
            SELECT provider, 'Key mặc định', api_key_encrypted, 'legacy', updated_by
            FROM ai_provider_configs AS config
            WHERE config.provider = 'gemini'
              AND config.api_key_encrypted <> ''
              AND NOT EXISTS (SELECT 1 FROM ai_provider_keys AS pool WHERE pool.provider = config.provider)
        SQL);
        $db->exec("UPDATE ai_provider_configs SET api_key_encrypted = '' WHERE provider = 'gemini' AND EXISTS (SELECT 1 FROM ai_provider_keys WHERE provider = 'gemini')");

        $knowledgeCount = (int) $db->query('SELECT COUNT(*) FROM ai_knowledge_entries')->fetchColumn();
        if ($knowledgeCount === 0) {
            $knowledge = $db->prepare('INSERT INTO ai_knowledge_entries (category, title, content, keywords, is_active) VALUES (?, ?, ?, ?, 1)');
            $knowledge->execute([
                'Hỗ trợ học sinh',
                'Kết nối với đại sứ sinh viên',
                'Học sinh có thể tìm đại sứ theo ngành học, quê quán và năm học; xem hồ sơ rồi nhắn tin trực tiếp để hỏi về trải nghiệm học tập và đời sống sinh viên.',
                'đại sứ, tư vấn, nhắn tin, ngành học, quê quán',
            ]);
            $knowledge->execute([
                'Hỗ trợ học sinh',
                'Khi đại sứ đang offline',
                'Học sinh vẫn có thể gửi tin nhắn khi đại sứ offline và để lại email nhận phản hồi, hoặc chọn đặt lịch tư vấn vào thời gian phù hợp.',
                'offline, email, phản hồi, đặt lịch, tư vấn',
            ]);
        }
    }

    private static function addColumn(PDO $db, string $table, string $column, string $definition): void
    {
        $columns = $db->query('PRAGMA table_info(' . $table . ')')->fetchAll();
        foreach ($columns as $item) {
            if ($item['name'] === $column) {
                return;
            }
        }
        $db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    private static function seed(PDO $db): void
    {
        $db->beginTransaction();
        try {
            $password = password_hash('123456', PASSWORD_DEFAULT);
            $user = $db->prepare(<<<'SQL'
                INSERT INTO users (role, name, email, password, student_code, major, hometown, interests, bio, avatar, study_year, is_online)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            SQL);

            $users = [
                ['admin', 'Phòng Tuyển sinh CMC', 'admin@cmc.edu.vn', $password, null, null, null, null, null, null, null, 1],
                ['student', 'Nguyễn Hà An', 'student@cmc.edu.vn', $password, 'CMC220104', 'Công nghệ thông tin', 'Hà Nội', 'Công nghệ, nhiếp ảnh', 'Mình thích kể chuyện về cuộc sống sinh viên qua những video ngắn.', null, 2, 1],
                ['ambassador', 'Trần Minh Anh', 'ambassador@cmc.edu.vn', $password, 'CMC210218', 'Marketing', 'Hải Phòng', 'Truyền thông, câu lạc bộ, du lịch', 'Đại sứ năm 3, sẵn sàng chia sẻ thật về học tập và hoạt động tại CMC.', null, 3, 1],
                ['ambassador', 'Lê Đức Nam', 'nam@cmc.edu.vn', $password, 'CMC200087', 'Công nghệ thông tin', 'Nam Định', 'AI, lập trình, bóng đá', 'Mình có thể giúp bạn hiểu rõ lộ trình học, đồ án và cơ hội thực tập ngành CNTT.', null, 4, 0],
                ['ambassador', 'Phạm Khánh Linh', 'linh@cmc.edu.vn', $password, 'CMC220331', 'Thiết kế đồ họa', 'Đà Nẵng', 'Minh họa, phim ảnh, âm nhạc', 'Yêu thiết kế và luôn sẵn lòng chia sẻ hành trình từ tân sinh viên đến portfolio đầu tiên.', null, 2, 1],
                ['prospect', 'Mai Thu', 'maithu@example.com', $password, null, 'Marketing', 'Hà Nội', null, null, null, null, 1],
            ];
            foreach ($users as $row) {
                $user->execute($row);
            }

            $campaign = $db->prepare('INSERT INTO campaigns (title, description, brief, platform, reward_points, status, deadline, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
            $campaigns = [
                ['Review không gian học tập mới', 'Chia sẻ một góc học tập bạn yêu thích tại CMC qua video ngắn chân thực.', 'Video dọc 30-60 giây. Có cảnh toàn, một chi tiết bạn thích và cảm nhận cá nhân. Gắn hashtag #CMCLife #HocThatChiaSeThat.', 'TikTok / Reels', 50, 'active', date('Y-m-d', strtotime('+21 days'))],
                ['Một ngày đi học ngành CNTT', 'Đưa học sinh THPT theo chân bạn trong một ngày học bình thường.', 'Quay 4-6 khoảnh khắc từ lúc đến trường, giờ học, ăn trưa đến hoạt động CLB. Không cần diễn, ưu tiên trải nghiệm thật.', 'TikTok / YouTube Shorts', 80, 'active', date('Y-m-d', strtotime('+35 days'))],
                ['Điều mình ước biết trước khi vào đại học', 'Một lời khuyên hữu ích cho các bạn lớp 12 đang chọn trường.', 'Kể một câu chuyện cá nhân, nêu bài học và kết bằng lời nhắn tích cực. Video tối đa 60 giây.', 'TikTok / Reels', 60, 'draft', date('Y-m-d', strtotime('+45 days'))],
            ];
            foreach ($campaigns as $row) {
                $campaign->execute($row);
            }

            $db->exec("INSERT INTO submissions (campaign_id, user_id, content_url, caption, status, platform, views, likes, comments, shares) VALUES (1, 2, 'https://www.tiktok.com/@demo/video/001', 'Góc học bài có nắng đẹp nhất CMC', 'pending', 'TikTok', 0, 0, 0, 0)");
            $db->exec("INSERT INTO submissions (campaign_id, user_id, content_url, caption, status, feedback, platform, views, likes, comments, shares) VALUES (2, 3, 'https://www.youtube.com/shorts/demo', 'Một ngày chạy deadline cùng sinh viên Marketing', 'approved', 'Nội dung tự nhiên, đúng brief.', 'YouTube Shorts', 18400, 1290, 86, 94)");

            $db->exec("INSERT INTO wallet_transactions (user_id, type, points, description, reference_type, reference_id) VALUES (2, 'credit', 50, 'Hoàn thành nhiệm vụ tháng 7', 'submission', 1), (3, 'credit', 120, 'Bài nộp UGC có hiệu quả tốt', 'submission', 2)");
            $db->exec("INSERT INTO conversations (prospect_id, ambassador_id, status, rating, last_message_at) VALUES (6, 3, 'open', NULL, CURRENT_TIMESTAMP)");
            $db->exec("INSERT INTO messages (conversation_id, sender_id, content, is_flagged) VALUES (1, 6, 'Chị ơi ngành Marketing có học nhiều toán không ạ?', 0), (1, 3, 'Chào em! Ngành có một số môn số liệu nền tảng, nhưng phần lớn tập trung vào tư duy khách hàng, nội dung và chiến lược. Chị có thể kể kỹ hơn về từng năm học nhé.', 0)");

            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
