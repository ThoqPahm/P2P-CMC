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

    /** Expand SQLite CHECK constraints without losing encrypted keys or slot state. */
    public static function migrateApinex(PDO $db): void
    {
        foreach (['ai_provider_configs', 'ai_provider_keys'] as $table) {
            $sql = (string)$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'")->fetchColumn();
            if ($sql === '' || (str_contains($sql, "'apinex'") && str_contains($sql, "'xkiro'"))) continue;
            $db->beginTransaction();
            try {
                $indexes = $db->query("SELECT sql FROM sqlite_master WHERE type IN ('index','trigger') AND tbl_name='$table' AND sql IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                $expanded = preg_replace_callback("~CHECK\\s*\\(provider\\s+IN\\s*\\(([^)]*)\\)\\)~i", static function(array $match): string {
                    $providers = $match[1];
                    foreach (["'apinex'", "'xkiro'"] as $provider) {
                        if (!str_contains($providers, $provider)) $providers .= ',' . $provider;
                    }
                    return 'CHECK(provider IN (' . $providers . '))';
                }, $sql) ?? $sql;
                $expanded = str_replace($table, $table . '_expanded', $expanded);
                $db->exec($expanded);
                $db->exec("INSERT INTO {$table}_expanded SELECT * FROM $table");
                $db->exec("DROP TABLE $table");
                $db->exec("ALTER TABLE {$table}_expanded RENAME TO $table");
                foreach ($indexes as $index) $db->exec($index);
                $db->commit();
            } catch (Throwable $error) {
                $db->rollBack();
                throw $error;
            }
        }
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

            CREATE TABLE IF NOT EXISTS rehearsal_scripts (
                user_id INTEGER NOT NULL,
                step_key TEXT NOT NULL,
                content TEXT NOT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(user_id, step_key),
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS public_rehearsal_scripts (
                step_key TEXT PRIMARY KEY,
                content TEXT NOT NULL,
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

        $db->exec(<<<'SQL'
            INSERT OR IGNORE INTO public_rehearsal_scripts(step_key, content, updated_at)
            SELECT source.step_key, source.content, source.updated_at
            FROM rehearsal_scripts AS source
            WHERE source.updated_at = (
                SELECT MAX(candidate.updated_at)
                FROM rehearsal_scripts AS candidate
                WHERE candidate.step_key = source.step_key
            )
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
        self::addColumn($db, 'submissions', 'cover_image', 'TEXT');
        self::addColumn($db, 'submissions', 'source_label', 'TEXT');
        self::addColumn($db, 'conversations', 'quality_score', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($db, 'conversations', 'crm_status', "TEXT NOT NULL DEFAULT 'new'");
        self::addColumn($db, 'conversations', 'public_token', 'TEXT');
        self::addColumn($db, 'users', 'last_seen_at', 'TEXT');
        self::addColumn($db, 'users', 'contact_email', 'TEXT');
        self::addColumn($db, 'consultation_appointments', 'public_token', 'TEXT');
        self::addColumn($db, 'consultation_appointments', 'conversation_id', 'INTEGER REFERENCES conversations(id)');
        self::addColumn($db, 'messages', 'is_ai', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($db, 'messages', 'moderation_provider', "TEXT NOT NULL DEFAULT 'manual'");
        self::addColumn($db, 'messages', 'moderation_model', 'TEXT');
        self::addColumn($db, 'messages', 'moderation_categories', 'TEXT');
        self::addColumn($db, 'messages', 'moderation_confidence', 'REAL');
        self::addColumn($db, 'messages', 'moderation_reason', 'TEXT');
        self::addColumn($db, 'messages', 'moderated_at', 'TEXT');

        // Chapter 4: Quản lý nguồn tin chính thức & xác minh (Bảng 15, Bảng 21, Bảng 23)
        self::addColumn($db, 'ai_knowledge_entries', 'source_reference', "TEXT NOT NULL DEFAULT ''");
        self::addColumn($db, 'ai_knowledge_entries', 'verified_by_role', "TEXT NOT NULL DEFAULT ''");
        self::addColumn($db, 'ai_knowledge_entries', 'verified_at', "TEXT NOT NULL DEFAULT ''");

        // Chapter 4: Khảo sát sau tương tác (Bảng 19, Bảng 26) & Chuyển tuyến câu hỏi (Sơ đồ 7, Bảng 23)
        self::addColumn($db, 'conversations', 'clarity_rating', 'INTEGER');
        self::addColumn($db, 'conversations', 'helpfulness_rating', 'INTEGER');
        self::addColumn($db, 'conversations', 'feedback_note', 'TEXT');
        self::addColumn($db, 'conversations', 'is_escalated', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($db, 'conversations', 'escalation_reason', 'TEXT');
        self::addColumn($db, 'conversations', 'escalation_status', "TEXT NOT NULL DEFAULT 'none'");
        self::addColumn($db, 'conversations', 'official_answer', 'TEXT');
        self::addColumn($db, 'conversations', 'answered_by', 'INTEGER');
        self::addColumn($db, 'conversations', 'answered_at', 'TEXT');

        $count = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            self::seed($db);
        }

        // Replace only known legacy demo codes so administrator-edited records remain untouched.
        $studentCodes = [
            ['BIT241104', 'student@cmc.edu.vn', 'CMC220104'],
            ['BBA231218', 'ambassador@cmc.edu.vn', 'CMC210218'],
            ['BIT221087', 'nam@cmc.edu.vn', 'CMC200087'],
            ['BGD241331', 'linh@cmc.edu.vn', 'CMC220331'],
        ];
        $updateStudentCode = $db->prepare('UPDATE users SET student_code=? WHERE email=? AND student_code=?');
        foreach ($studentCodes as $studentCode) $updateStudentCode->execute($studentCode);

        // Demo initialization runs only for a new database, not on every request.
        if ($count === 0) {
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
        }

        // Keep the public demo's editorial feed useful on fresh installs and existing demo databases.
        // These records are matched by their canonical title/URL so administrator-created content is untouched.
        $db->prepare(<<<'SQL'
            UPDATE submissions
            SET content_url = ?, caption = ?, platform = 'TikTok', cover_image = ?, source_label = 'Xem trên TikTok',
                views = 28600, likes = 2140, comments = 73, shares = 118
            WHERE content_url = 'https://www.youtube.com/shorts/demo'
        SQL)->execute([
            'https://www.tiktok.com/@ua.cmc/video/7675607526141873429',
            'Một khoảnh khắc rất CMCU: hội bạn thân, đồng phục xanh và năng lượng sinh viên sau giờ học.',
            'assets/img/content/tiktok-cmcu-7675607526141873429.jpg',
        ]);

        $db->prepare(<<<'SQL'
            UPDATE submissions
            SET blog_excerpt = ?, blog_body = ?, content_url = ?, cover_image = ?, source_label = 'Thông tin tham khảo từ CMCU',
                views = CASE WHEN views = 0 THEN 3840 ELSE views END,
                likes = CASE WHEN likes = 0 THEN 268 ELSE likes END
            WHERE content_type = 'blog' AND blog_title = 'Một ngày học Công nghệ thông tin tại CMC diễn ra như thế nào?'
        SQL)->execute([
            'Theo chân một sinh viên CNTT từ tiết học buổi sáng, giờ làm đồ án nhóm đến khoảng thời gian tự học và chuẩn bị cho định hướng nghề nghiệp.',
            "Một ngày học của mình thường bắt đầu trước giờ vào lớp khoảng 20 phút. Mình mở lại mục tiêu của buổi học, xem phần nào còn chưa chắc và ghi ra vài câu hỏi ngắn. Cách này giúp mình không bị cuốn vào việc chép mọi thứ, mà tập trung quan sát cách giảng viên phân tích bài toán và đi từ yêu cầu đến giải pháp.\n\nTrong giờ thực hành, điều mình thấy khác nhất so với thời phổ thông là không phải lúc nào cũng có một đáp án mẫu duy nhất. Có bài cả nhóm phải thử vài hướng, đọc tài liệu, kiểm tra lỗi rồi quay lại sửa cách làm ban đầu. Khi bí, mình thường mô tả rõ đã thử gì, kết quả ra sao và đang mắc ở bước nào; câu hỏi càng cụ thể thì giảng viên và bạn bè càng dễ hỗ trợ.\n\nBuổi chiều thường dành cho bài tập hoặc đồ án nhóm. Nhóm mình chia đầu việc theo thế mạnh, nhưng vẫn dành một khoảng để cùng review code và giải thích phần mình làm. Nhờ vậy, mỗi người không chỉ hoàn thành nhiệm vụ riêng mà còn hiểu sản phẩm hoạt động như một hệ thống. Những buổi tranh luận về cách đặt tên, luồng dữ liệu hay trải nghiệm người dùng đôi khi kéo dài, nhưng đó lại là lúc mình học được nhiều nhất.\n\nNgoài giờ học, mình cố gắng duy trì một dự án cá nhân nhỏ thay vì chạy theo quá nhiều công nghệ cùng lúc. Có tuần mình chỉ sửa một tính năng hoặc viết lại phần tài liệu, nhưng việc nhìn thấy sản phẩm tốt lên từng chút giúp mình hiểu rõ hơn mình hợp với phát triển phần mềm, dữ liệu hay AI. Các câu chuyện nghề nghiệp từ sinh viên và cựu sinh viên CMCU cũng giúp mình hình dung cụ thể hơn con đường từ giảng đường đến môi trường doanh nghiệp.\n\nNếu bạn đang cân nhắc ngành CNTT, bạn không cần phải biết lập trình thật giỏi trước khi nhập học. Sự tò mò, thói quen tự học và khả năng phối hợp với người khác quan trọng hơn rất nhiều. Hãy bắt đầu bằng một bài toán bạn thấy thú vị, làm đến nơi đến chốn và đừng ngại hỏi khi chưa hiểu.",
            'https://cmcu.edu.vn/nganh-cong-nghe-thong-tin/',
            'assets/img/content/cmc-ai-automation-alumni-2026.jpg',
        ]);

        $editorialItems = [
            [
                'social', 'https://www.tiktok.com/@ua.cmc/video/7674889049940823316',
                'Một màn “bay” đúng chất Gen Z tại CMCU — vui một chút giữa lịch học và deadline.',
                'TikTok',
                'Đời sống sinh viên CMCU: vui hết mình sau giờ học',
                'Không chỉ có giờ học và đồ án, những khoảnh khắc ngẫu hứng cùng bạn bè cũng làm nên ký ức đại học.',
                '', 'assets/img/content/tiktok-cmcu-7674889049940823316.jpg', 'Xem trên TikTok',
                'linh@cmc.edu.vn', 19700, 1560, 51, 84,
            ],
            [
                'blog', 'https://cmcu.edu.vn/an-tuong-voi-ngay-hoi-thuc-tap-va-viec-lam-cmc-career-fair-2026-truong-dai-hoc-cmc-ket-noi-he-sinh-thai-doanh-nghiep-dong-hanh-kien-tao-nguon-nhan-luc-chat-luong-cao-cho-ky-nguyen-ai/',
                'Một ngày mình bước ra khỏi lớp học để trò chuyện trực tiếp với doanh nghiệp và nhìn rõ hơn các kỹ năng cần chuẩn bị.',
                'Bài viết',
                'Đi Career Fair khi còn là sinh viên: mình đã chuẩn bị gì?',
                'Một góc nhìn thực tế về cách chuẩn bị CV, mở đầu cuộc trò chuyện và biến một ngày hội việc làm thành cơ hội học hỏi.',
                "Trước CMC Career Fair 2026, mình từng nghĩ ngày hội việc làm chủ yếu dành cho sinh viên sắp tốt nghiệp. Nhưng khi xem danh sách doanh nghiệp và các vị trí đang tìm kiếm, mình nhận ra đây còn là dịp rất tốt để sinh viên năm hai, năm ba kiểm tra xem những gì mình đang học có gần với nhu cầu thực tế hay chưa.\n\nMình chuẩn bị một bản CV một trang, không cố liệt kê mọi hoạt động mà chọn ba trải nghiệm có thể kể thành câu chuyện: một dự án nhóm, một chiến dịch nội dung và một lần phải xử lý tiến độ gấp. Mình cũng viết sẵn ba câu hỏi muốn hỏi nhà tuyển dụng, ví dụ sinh viên mới thường thiếu kỹ năng nào và một portfolio tốt nên cho thấy điều gì.\n\nKhi đến sự kiện, phần khó nhất không phải là đưa CV mà là bắt đầu cuộc trò chuyện. Câu mở đầu hiệu quả nhất với mình rất đơn giản: giới thiệu ngành học, năm học và điều đang muốn tìm hiểu. Sau đó mình lắng nghe, ghi chú lại từ khóa thay vì cố gây ấn tượng bằng những điều chưa thật sự hiểu.\n\nĐiều mình mang về không chỉ là thông tin thực tập. Mình thấy rõ hơn rằng kiến thức chuyên môn cần đi cùng khả năng trình bày vấn đề, làm việc nhóm và chủ động xin phản hồi. Có doanh nghiệp quan tâm tới sản phẩm mình đã làm hơn điểm số; có nơi lại hỏi rất kỹ về cách mình đo lường kết quả của một chiến dịch.\n\nNếu bạn chưa đến năm cuối, vẫn nên thử tham gia một ngày hội nghề nghiệp. Hãy coi đó là buổi quan sát có mục tiêu: chọn vài gian hàng phù hợp, chuẩn bị câu hỏi thật và dành thời gian tổng kết sau sự kiện. Bạn sẽ biết mình nên học thêm gì trong học kỳ tiếp theo, thay vì chờ đến lúc cần thực tập mới bắt đầu.",
                'assets/img/content/cmc-career-fair-2026.jpg', 'Đọc tin chính thức từ CMCU',
                'ambassador@cmc.edu.vn', 6210, 487, 28, 61,
            ],
            [
                'blog', 'https://cmcu.edu.vn/tu-giang-duong-cmcu-den-moi-truong-quoc-te-sinh-vien-truong-dai-hoc-cmc-san-sang-cho-hanh-trinh-trao-doi-hoc-tap-tai-trung-quoc/',
                'Từ việc chuẩn bị ngoại ngữ, hồ sơ đến tâm lý sống xa nhà: những điều mình ghi lại từ hành trình trao đổi học tập của sinh viên CMCU.',
                'Bài viết',
                'Trước một kỳ trao đổi quốc tế, sinh viên nên chuẩn bị từ đâu?',
                'Một checklist gần gũi về ngoại ngữ, hồ sơ, tài chính và cách tận dụng trải nghiệm học tập ở môi trường mới.',
                "Khi nghe về chương trình trao đổi học tập, nhiều bạn nghĩ trước tiên đến điểm đến và những bức ảnh đẹp. Nhưng qua những buổi chia sẻ với các bạn chuẩn bị sang Trung Quốc, mình thấy phần quan trọng nhất lại bắt đầu từ rất sớm: hiểu mục tiêu của bản thân và chuẩn bị từng việc nhỏ một cách có kế hoạch.\n\nNgoại ngữ là nền tảng, nhưng không chỉ để vượt qua yêu cầu hồ sơ. Bạn cần đủ tự tin để hỏi đường, trao đổi trong lớp, làm việc nhóm và xử lý những tình huống hàng ngày. Thay vì đợi đến gần ngày đi mới học cấp tốc, hãy tạo thói quen nghe và nói đều đặn, đồng thời ghi lại các cụm từ liên quan đến ngành học của mình.\n\nVới hồ sơ, mình khuyên nên làm một bảng theo dõi gồm giấy tờ cần có, người phụ trách, thời hạn và trạng thái hiện tại. Những việc như hộ chiếu, bảng điểm, xác nhận sinh viên hay bảo hiểm đều có thể mất nhiều thời gian hơn dự kiến. Lưu một bản số hóa có tên file rõ ràng cũng giúp bạn đỡ lúng túng khi cần bổ sung.\n\nTài chính và sinh hoạt nên được tính thực tế. Ngoài khoản cố định, hãy dự trù chi phí đi lại, sim, đồ dùng ban đầu và một quỹ nhỏ cho tình huống phát sinh. Trước khi đi, bạn cũng nên tìm hiểu văn hóa lớp học, phương tiện công cộng và cách liên hệ hỗ trợ khi cần.\n\nCuối cùng, đừng đặt áp lực rằng kỳ trao đổi phải hoàn hảo. Mục tiêu của trải nghiệm là bước ra khỏi vùng quen thuộc, học cách thích nghi và hiểu thêm về chính mình. Nếu còn băn khoăn, bạn có thể hỏi một đại sứ đã tham gia hoạt động quốc tế về lịch chuẩn bị, những điều bất ngờ và cách cân bằng việc học với khám phá.",
                'assets/img/content/cmc-student-exchange-2026.jpg', 'Đọc tin chính thức từ CMCU',
                'linh@cmc.edu.vn', 4780, 356, 19, 43,
            ],
        ];
        $insertEditorial = $db->prepare(<<<'SQL'
            INSERT INTO submissions (
                campaign_id, user_id, content_url, caption, status, feedback, platform,
                views, likes, comments, shares, content_type, blog_title, blog_excerpt, blog_body,
                cover_image, source_label
            )
            SELECT c.id, u.id, ?, ?, 'approved', 'Nội dung đã được duyệt để hiển thị trong widget.', ?,
                   ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            FROM campaigns c, users u
            WHERE c.id = (SELECT MIN(id) FROM campaigns WHERE status = 'active')
              AND u.email = ? AND u.role = 'ambassador'
              AND NOT EXISTS (
                  SELECT 1 FROM submissions s
                  WHERE (? <> '' AND s.content_url = ?) OR (? <> '' AND s.blog_title = ?)
              )
        SQL);
        foreach ($editorialItems as $item) {
            [$type, $url, $caption, $platform, $title, $excerpt, $body, $cover, $sourceLabel, $email, $views, $likes, $comments, $shares] = $item;
            $insertEditorial->execute([
                $url, $caption, $platform, $views, $likes, $comments, $shares, $type,
                $type === 'blog' ? $title : null, $excerpt, $body, $cover, $sourceLabel, $email,
                $url, $url, $type === 'blog' ? $title : '', $type === 'blog' ? $title : '',
            ]);
        }

        self::migrateApinex($db);
        $db->exec(<<<'SQL'
            INSERT OR IGNORE INTO ai_provider_configs (provider, endpoint, model) VALUES
                ('apinex', 'https://api.apinex.bond/v1/chat/completions', ''),
                ('xkiro', 'https://api.xkiro.com/v1/chat/completions', 'openai/gpt-5.6-sol'),
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
        $db->prepare("UPDATE ui_settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'widget_ai_welcome' AND value = ?")->execute([
            'Chào bạn, mình là CMCU AI. Bạn đang quan tâm điều gì ở CMCU? Cứ nói tự nhiên nhé, chưa biết bắt đầu từ đâu cũng không sao.',
            'Chào bạn! Mình có thể giải đáp thông tin chung từ dữ liệu của trường hoặc giúp bạn tìm đại sứ phù hợp.',
        ]);
        $db->exec("UPDATE ui_settings SET value = 'CMCU AI', updated_at = CURRENT_TIMESTAMP WHERE key = 'widget_ai_name' AND value = 'CMC AI'");
        $db->prepare("UPDATE ui_settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'widget_ai_welcome' AND value = ?")->execute([
            'Chào bạn, mình là CMCU AI. Bạn đang quan tâm điều gì ở CMCU? Cứ nói tự nhiên nhé, chưa biết bắt đầu từ đâu cũng không sao.',
            'Chào bạn, mình là CMC AI. Bạn đang quan tâm điều gì ở CMC? Cứ nói tự nhiên nhé, chưa biết bắt đầu từ đâu cũng không sao.',
        ]);

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

        if ($count === 0) { self::seedOfficialAiKnowledge($db); }
    }

    private static function seedOfficialAiKnowledge(PDO $db): void
    {
        $entries = require dirname(__DIR__) . '/data/official_ai_knowledge.php';
        $exists = $db->prepare('SELECT 1 FROM ai_knowledge_entries WHERE title = ? LIMIT 1');
        $insert = $db->prepare('INSERT INTO ai_knowledge_entries (category, title, content, keywords, is_active) VALUES (?, ?, ?, ?, 1)');
        foreach ($entries as $entry) {
            $exists->execute([$entry['title']]);
            if (!$exists->fetchColumn()) {
                $insert->execute([$entry['category'], $entry['title'], $entry['content'], $entry['keywords']]);
            }
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
                ['student', 'Nguyễn Hà An', 'student@cmc.edu.vn', $password, 'BIT241104', 'Công nghệ thông tin', 'Hà Nội', 'Công nghệ, nhiếp ảnh', 'Mình thích kể chuyện về cuộc sống sinh viên qua những video ngắn.', null, 2, 1],
                ['ambassador', 'Trần Minh Anh', 'ambassador@cmc.edu.vn', $password, 'BBA231218', 'Marketing', 'Hải Phòng', 'Truyền thông, câu lạc bộ, du lịch', 'Đại sứ năm 3, sẵn sàng chia sẻ thật về học tập và hoạt động tại CMC.', null, 3, 1],
                ['ambassador', 'Lê Đức Nam', 'nam@cmc.edu.vn', $password, 'BIT221087', 'Công nghệ thông tin', 'Nam Định', 'AI, lập trình, bóng đá', 'Mình có thể giúp bạn hiểu rõ lộ trình học, đồ án và cơ hội thực tập ngành CNTT.', null, 4, 0],
                ['ambassador', 'Phạm Khánh Linh', 'linh@cmc.edu.vn', $password, 'BGD241331', 'Thiết kế đồ họa', 'Đà Nẵng', 'Minh họa, phim ảnh, âm nhạc', 'Yêu thiết kế và luôn sẵn lòng chia sẻ hành trình từ tân sinh viên đến portfolio đầu tiên.', null, 2, 1],
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

            $db->exec("INSERT INTO wallet_transactions (user_id, type, points, description, reference_type, reference_id) VALUES (3, 'credit', 120, 'Bài nộp UGC có hiệu quả tốt', 'submission', 2)");
            $db->exec("INSERT INTO conversations (prospect_id, ambassador_id, status, rating, last_message_at) VALUES (6, 3, 'open', NULL, CURRENT_TIMESTAMP)");
            $db->exec("INSERT INTO messages (conversation_id, sender_id, content, is_flagged) VALUES (1, 6, 'Chị ơi ngành Marketing có học nhiều toán không ạ?', 0), (1, 3, 'Chào em! Ngành có một số môn số liệu nền tảng, nhưng phần lớn tập trung vào tư duy khách hàng, nội dung và chiến lược. Chị có thể kể kỹ hơn về từng năm học nhé.', 0)");

            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
