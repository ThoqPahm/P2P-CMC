<?php
declare(strict_types=1);

/** Chapter 4 workflows. Kept separate from the thesis screenshot surfaces. */
final class AmbassadorProgram
{
    public const MODULES = [
        'sources' => ['Nguồn tin & giới hạn tư vấn', 'Chính sách, học phí và tuyển sinh phải dựa trên nguồn được nhà trường xác nhận. Trải nghiệm cá nhân cần được nói rõ là góc nhìn của mình, không phải cam kết của trường.', 'Học sinh hỏi một chính sách chưa có nguồn xác nhận. Bạn làm gì?', ['Chuyển cán bộ phụ trách kèm câu hỏi và ngữ cảnh', 'Suy đoán từ trải nghiệm cá nhân', 'Cam kết để học sinh yên tâm']],
        'privacy' => ['Quyền riêng tư', 'Chỉ thu thập thông tin cần cho việc tư vấn. Không yêu cầu mật khẩu, giấy tờ định danh hoặc công khai nội dung hội thoại. Khi cần chuyển tuyến, chỉ chia sẻ ngữ cảnh liên quan với người phụ trách.', 'Thông tin nào không nên yêu cầu trong chat tư vấn?', ['Mật khẩu tài khoản của học sinh', 'Ngành đang quan tâm', 'Chủ đề muốn được tư vấn']],
        'conversation' => ['Lắng nghe & phản hồi', 'Đọc ngữ cảnh trước khi trả lời. Khi chưa rõ nhu cầu, hỏi lại một câu ngắn. Với câu hỏi về trải nghiệm, chia sẻ cụ thể và nêu giới hạn. Nếu chưa thể phản hồi ngay, hẹn thời điểm phù hợp, không tự hứa thay người khác.', 'Học sinh nói “mình chưa biết chọn ngành nào”. Bước tiếp theo?', ['Hỏi thêm về sở thích và điều bạn ấy đang cân nhắc', 'Chọn ngay ngành của mình cho bạn ấy', 'Gửi nguyên văn toàn bộ danh sách học phí']],
    ];
    public const LABELS = ['pending'=>'Chờ duyệt', 'approved'=>'Được tiếp nhận', 'rejected'=>'Chưa phù hợp', 'active'=>'Đang tham gia', 'paused'=>'Tạm nghỉ', 'role_change'=>'Đề nghị đổi vai trò', 'assigned'=>'Đã giao', 'submitted'=>'Chờ nhận xét', 'completed'=>'Đã ghi nhận', 'revision'=>'Cần bổ sung', 'draft'=>'Chưa xác nhận', 'official'=>'Thông tin chính thức', 'experience'=>'Trải nghiệm cá nhân'];

    public static function migrate(PDO $db): void
    {
        $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS ambassador_applications (
            user_id INTEGER PRIMARY KEY REFERENCES users(id), motivation TEXT NOT NULL,
            topics TEXT NOT NULL, skills TEXT NOT NULL, availability TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending', participation TEXT NOT NULL DEFAULT 'active',
            review_note TEXT NOT NULL DEFAULT '', reviewed_by INTEGER REFERENCES users(id),
            consent_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS ambassador_training (
            user_id INTEGER NOT NULL REFERENCES users(id), module TEXT NOT NULL,
            completed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id,module)
        );
        CREATE TABLE IF NOT EXISTS ambassador_tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id),
            mentor_id INTEGER NOT NULL REFERENCES users(id), title TEXT NOT NULL, kind TEXT NOT NULL,
            brief TEXT NOT NULL, due_date TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'assigned',
            result TEXT NOT NULL DEFAULT '', feedback TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS ambassador_reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id),
            author_id INTEGER NOT NULL REFERENCES users(id), decision TEXT NOT NULL,
            note TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS knowledge_governance (
            entry_id INTEGER PRIMARY KEY REFERENCES ai_knowledge_entries(id),
            kind TEXT NOT NULL, source_url TEXT NOT NULL, scope TEXT NOT NULL,
            valid_until TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'draft',
            content_hash TEXT NOT NULL, confirmed_by INTEGER REFERENCES users(id),
            confirmed_at TEXT, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS program_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT, actor_id INTEGER NOT NULL REFERENCES users(id),
            entity TEXT NOT NULL, entity_id INTEGER NOT NULL, action TEXT NOT NULL,
            snapshot TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS program_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT, reporter_id INTEGER NOT NULL REFERENCES users(id),
            category TEXT NOT NULL, detail TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'open',
            response TEXT NOT NULL DEFAULT '', resolved_by INTEGER REFERENCES users(id),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        SQL);
    }

    private static function text(array $data, string $key, int $max = 2000): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $max) {
            throw new InvalidArgumentException('Vui lòng điền đầy đủ các trường; nội dung vượt giới hạn hoặc không hợp lệ.');
        }
        return trim($value);
    }

    private static function choice(array $data, string $key, array $choices): string
    {
        $value = self::text($data, $key, 40);
        if (!in_array($value, $choices, true)) {
            throw new InvalidArgumentException('Lựa chọn không hợp lệ.');
        }
        return $value;
    }

    private static function date(array $data, string $key): string
    {
        $date = self::text($data, $key, 10);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < date('Y-m-d')) {
            throw new InvalidArgumentException('Ngày phải hợp lệ và không được ở quá khứ.');
        }
        return $date;
    }

    private static function get(PDO $db, string $sql, array $params): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            throw new InvalidArgumentException('Không tìm thấy bản ghi hoặc bạn không có quyền xử lý.');
        }
        return $record;
    }

    public static function hash(array $entry): string
    {
        return hash('sha256', json_encode(array_map(static fn(string $key): string => (string)($entry[$key]??''), ['category','title','content','keywords']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public static function knowledge(PDO $db, bool $approvedOnly = false): array
    {
        $entries = $db->query('SELECT k.*, g.kind, g.source_url, g.scope, g.valid_until, g.state, g.content_hash, g.confirmed_at, u.name AS confirmer FROM ai_knowledge_entries k LEFT JOIN knowledge_governance g ON g.entry_id=k.id LEFT JOIN users u ON u.id=g.confirmed_by ORDER BY k.updated_at DESC, k.id DESC')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($entries as &$entry) {
            $entry['usable'] = $entry['state'] === 'approved' && $entry['valid_until'] >= date('Y-m-d') && hash_equals((string) $entry['content_hash'], self::hash($entry));
            $entry['source_reference'] = $entry['source_url'] ?? '';
            $entry['verified_by_role'] = $entry['confirmer'] ?? '';
            $entry['verified_at'] = $entry['confirmed_at'] ?? '';
        }
        unset($entry);
        return $approvedOnly ? array_values(array_filter($entries, static fn(array $e): bool => $e['usable'] && $e['kind'] === 'official' && (int) $e['is_active'] === 1)) : $entries;
    }

    /** Actor is always reloaded from the database, never trusted from POST. */
    public static function handle(PDO $db, int $actorId, string $action, array $input): void
    {
        $actor = self::get($db, "SELECT * FROM users WHERE id=? AND status='active'", [$actorId]);
        $adminActions = ['review_application','assign_task','review_task','review_member','save_source','resolve_report'];
        if (in_array($action, $adminActions, true) ? $actor['role'] !== 'admin' : !in_array($actor['role'], ['student','ambassador'], true)) {
            throw new InvalidArgumentException('Bạn không có quyền thực hiện thao tác này.');
        }
        $id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $db->beginTransaction();
        try {
            switch ($action) {
                case 'report_issue':
                    $db->prepare('INSERT INTO program_reports(reporter_id,category,detail) VALUES(?,?,?)')->execute([$actorId,self::choice($input,'category',['information','privacy','communication']),self::text($input,'detail',2000)]);
                    $id = (int)$db->lastInsertId();
                    break;
                case 'resolve_report':
                    self::get($db,'SELECT * FROM program_reports WHERE id=?',[$id]);
                    $status=self::choice($input,'status',['open','resolved']);
                    $db->prepare('UPDATE program_reports SET status=?,response=?,resolved_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,self::text($input,'note'),$actorId,$id]);
                    break;
                case 'apply':
                    if (($input['consent'] ?? '') !== '1') {
                        throw new InvalidArgumentException('Cần đồng ý mục đích sử dụng hồ sơ trước khi gửi.');
                    }
                    $sql = "INSERT INTO ambassador_applications(user_id,motivation,topics,skills,availability) VALUES(?,?,?,?,?) ON CONFLICT(user_id) DO UPDATE SET motivation=excluded.motivation,topics=excluded.topics,skills=excluded.skills,availability=excluded.availability,status=CASE WHEN ambassador_applications.status='approved' THEN 'approved' ELSE 'pending' END,updated_at=CURRENT_TIMESTAMP";
                    $db->prepare($sql)->execute([$actorId, self::text($input,'motivation'), self::text($input,'topics',500), self::text($input,'skills',500), self::text($input,'availability',500)]);
                    $id = $actorId;
                    break;
                case 'review_application':
                    self::get($db, 'SELECT * FROM ambassador_applications WHERE user_id=?', [$id]);
                    $db->prepare('UPDATE ambassador_applications SET status=?,review_note=?,reviewed_by=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=?')->execute([self::choice($input,'status',['approved','rejected']), self::text($input,'note'), $actorId, $id]);
                    break;
                case 'complete_training':
                    $module = self::choice($input,'module',array_keys(self::MODULES));
                    if (($input['answer'] ?? '') !== '0') {
                        throw new InvalidArgumentException('Chưa đúng. Hãy đọc lại hướng dẫn và thử lại.');
                    }
                    $db->prepare('INSERT OR IGNORE INTO ambassador_training(user_id,module) VALUES(?,?)')->execute([$actorId,$module]);
                    $id = $actorId;
                    break;
                case 'assign_task':
                    self::get($db, "SELECT u.id FROM users u JOIN ambassador_applications a ON a.user_id=u.id WHERE u.id=? AND u.role IN ('student','ambassador') AND u.status='active' AND a.status='approved' AND a.participation='active'", [$id]);
                    $training = self::get($db, 'SELECT COUNT(*) AS n FROM ambassador_training WHERE user_id=?', [$id]);
                    if ((int) $training['n'] < count(self::MODULES)) {
                        throw new InvalidArgumentException('Thành viên cần hoàn thành ba nội dung định hướng trước khi nhận việc.');
                    }
                    $db->prepare('INSERT INTO ambassador_tasks(user_id,mentor_id,title,kind,brief,due_date) VALUES(?,?,?,?,?,?)')->execute([$id,$actorId,self::text($input,'title',160),self::choice($input,'kind',['content','consultation','event']),self::text($input,'brief',4000),self::date($input,'due_date')]);
                    $id = (int) $db->lastInsertId();
                    break;
                case 'submit_task':
                    self::get($db, "SELECT * FROM ambassador_tasks WHERE id=? AND user_id=? AND status IN ('assigned','revision')", [$id,$actorId]);
                    $db->prepare("UPDATE ambassador_tasks SET result=?,status='submitted',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([self::text($input,'result',4000),$id]);
                    break;
                case 'review_task':
                    self::get($db, "SELECT * FROM ambassador_tasks WHERE id=? AND status='submitted'", [$id]);
                    $db->prepare('UPDATE ambassador_tasks SET status=?,feedback=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([self::choice($input,'status',['completed','revision']),self::text($input,'note'),$id]);
                    break;
                case 'member_feedback':
                case 'review_member':
                    $id = $action === 'member_feedback' ? $actorId : $id;
                    self::get($db, 'SELECT * FROM ambassador_applications WHERE user_id=?', [$id]);
                    $decision = self::choice($input,'decision',['active','paused','role_change']);
                    $db->prepare('INSERT INTO ambassador_reviews(user_id,author_id,decision,note) VALUES(?,?,?,?)')->execute([$id,$actorId,$decision,self::text($input,'note')]);
                    // Member feedback is a request; only a coordinator changes participation.
                    if ($action === 'review_member') {
                        $db->prepare('UPDATE ambassador_applications SET participation=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=?')->execute([$decision,$id]);
                    }
                    break;
                case 'save_source':
                    $entry = self::get($db,'SELECT * FROM ai_knowledge_entries WHERE id=?',[$id]);
                    $url = self::text($input,'source_url',1000);
                    if (!filter_var($url,FILTER_VALIDATE_URL) || !in_array(parse_url($url,PHP_URL_SCHEME),['https','http'],true)) {
                        throw new InvalidArgumentException('Nguồn cần là một đường dẫn http hoặc https hợp lệ.');
                    }
                    $state = self::choice($input,'state',['draft','approved']);
                    $db->prepare('INSERT INTO knowledge_governance(entry_id,kind,source_url,scope,valid_until,state,content_hash,confirmed_by,confirmed_at) VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(entry_id) DO UPDATE SET kind=excluded.kind,source_url=excluded.source_url,scope=excluded.scope,valid_until=excluded.valid_until,state=excluded.state,content_hash=excluded.content_hash,confirmed_by=excluded.confirmed_by,confirmed_at=excluded.confirmed_at,updated_at=CURRENT_TIMESTAMP')->execute([$id,self::choice($input,'kind',['official','experience']),$url,self::text($input,'scope',1000),self::date($input,'valid_until'),$state,self::hash($entry),$state==='approved'?$actorId:null,$state==='approved'?gmdate('Y-m-d H:i:s'):null]);
                    break;
                default:
                    throw new InvalidArgumentException('Thao tác không hợp lệ.');
            }
            // Deliberately exclude CSRF tokens and credentials from audit records.
            $snapshot = array_intersect_key($input,array_flip(['motivation','topics','skills','availability','status','note','module','title','kind','brief','due_date','result','decision','source_url','scope','valid_until','state','category','detail']));
            if ($action === 'save_source') {
                $snapshot['entry'] = array_intersect_key($entry,array_flip(['category','title','content','keywords']));
            }
            $db->prepare('INSERT INTO program_audit(actor_id,entity,entity_id,action,snapshot) VALUES(?,?,?,?,?)')->execute([$actorId,$action==='save_source'?'knowledge':'program',$id,$action,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    public static function metrics(PDO $db): array
    {
        $one = static function(string $sql) use ($db): mixed { return $db->query($sql)->fetchColumn(); };
        $first = $one("SELECT AVG(seconds) FROM (SELECT (julianday((SELECT MIN(m.created_at) FROM messages m WHERE m.conversation_id=c.id AND m.sender_id=c.ambassador_id AND m.is_ai=0 AND m.is_flagged=0 AND m.created_at >= (SELECT MIN(p.created_at) FROM messages p WHERE p.conversation_id=c.id AND p.sender_id=c.prospect_id))) - julianday((SELECT MIN(p.created_at) FROM messages p WHERE p.conversation_id=c.id AND p.sender_id=c.prospect_id)))*86400 AS seconds FROM conversations c) WHERE seconds >= 0");
        return [
            'conversations'=>(int)$one('SELECT COUNT(*) FROM conversations'),
            'appointments'=>(int)$one('SELECT COUNT(*) FROM consultation_appointments'),
            'majors'=>(int)$one("SELECT COUNT(DISTINCT major) FROM users WHERE role='ambassador' AND status='active' AND major IS NOT NULL AND major<>''"),
            'first_reply_minutes'=>$first===null?null:round((float)$first/60,1),
            'rating'=>$one('SELECT AVG(rating) FROM conversations WHERE rating BETWEEN 1 AND 5'),
            'rated'=>(int)$one('SELECT COUNT(*) FROM conversations WHERE rating BETWEEN 1 AND 5'),
            'approved_sources'=>count(self::knowledge($db,true)),
            'total_sources'=>(int)$one('SELECT COUNT(*) FROM ai_knowledge_entries'),
            'trained'=>(int)$one('SELECT COUNT(*) FROM (SELECT user_id FROM ambassador_training GROUP BY user_id HAVING COUNT(*)=3)'),
            'tasks'=>(int)$one('SELECT COUNT(*) FROM ambassador_tasks'),
            'completed_tasks'=>(int)$one("SELECT COUNT(*) FROM ambassador_tasks WHERE status='completed'"),
            'flagged'=>(int)$one('SELECT COUNT(*) FROM messages WHERE is_flagged=1'),
            'messages'=>(int)$one('SELECT COUNT(*) FROM messages'),
            'open_reports'=>(int)$one("SELECT COUNT(*) FROM program_reports WHERE status='open'"),
            'privacy_reports'=>(int)$one("SELECT COUNT(*) FROM program_reports WHERE category='privacy'"),
        ];
    }
}
