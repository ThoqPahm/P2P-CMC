<?php
declare(strict_types=1);

final class WorkflowIntegrity
{
    public static function migrate(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS submission_reward_state (
            submission_id INTEGER PRIMARY KEY REFERENCES submissions(id),
            awarded_points INTEGER NOT NULL DEFAULT 0,
            legacy_review_required INTEGER NOT NULL DEFAULT 0,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        // Snapshot old credits without changing balances. Historical inconsistencies
        // require an explicit reconciliation decision, never an automatic debit.
        $db->exec("INSERT OR IGNORE INTO submission_reward_state(submission_id,awarded_points,legacy_review_required)
            SELECT s.id, COALESCE(SUM(CASE WHEN w.type='credit' THEN w.points ELSE -w.points END),0),
                CASE WHEN COUNT(w.id)>0 THEN 1 ELSE 0 END
            FROM submissions s JOIN wallet_transactions w ON w.reference_type='submission' AND w.reference_id=s.id AND w.user_id=s.user_id
            GROUP BY s.id");
        $db->exec("CREATE VIEW IF NOT EXISTS eligible_ambassadors AS
            SELECT u.* FROM users u WHERE u.role='ambassador' AND u.status='active'
            AND u.policy_status='approved' AND u.violation_level<>'red'
            AND NOT EXISTS (SELECT 1 FROM ambassador_applications a WHERE a.user_id=u.id
                AND (a.status<>'approved' OR a.participation<>'active'
                    OR (SELECT COUNT(*) FROM ambassador_training t WHERE t.user_id=u.id AND t.module IN ('sources','privacy','conversation'))<3))");
    }

    public static function reconcileReward(PDO $db, array $submission, string $status, int $target): void
    {
        if (!$db->inTransaction()) { throw new LogicException('Reward update requires a transaction.'); }
        $stmt=$db->prepare('SELECT * FROM submission_reward_state WHERE submission_id=?');
        $stmt->execute([$submission['id']]);
        $state=$stmt->fetch(PDO::FETCH_ASSOC);
        if ($state && (int)$state['legacy_review_required']===1) {
            throw new InvalidArgumentException('Bài này có giao dịch điểm cũ cần đối soát. Chưa thay đổi trạng thái hoặc điểm thưởng.');
        }
        $target=$status==='approved'?max(0,$target):0;
        $delta=$target-(int)($state['awarded_points']??0);
        if ($delta!==0) {
            $db->prepare('INSERT INTO wallet_transactions(user_id,type,points,description,reference_type,reference_id) VALUES(?,?,?,?,?,?)')
                ->execute([$submission['user_id'],$delta>0?'credit':'debit',abs($delta),$delta>0?'Ghi nhận điểm bài nộp':'Hoàn điểm do thay đổi kết quả duyệt','submission',$submission['id']]);
        }
        $db->prepare('INSERT INTO submission_reward_state(submission_id,awarded_points) VALUES(?,?) ON CONFLICT(submission_id) DO UPDATE SET awarded_points=excluded.awarded_points,updated_at=CURRENT_TIMESTAMP')->execute([$submission['id'],$target]);
    }

    public static function quality(PDO $db, int $id, bool $newMessage=false): int
    {
        $stmt=$db->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(is_flagged),0) AS flagged FROM messages WHERE conversation_id=?');
        $stmt->execute([$id]); $counts=$stmt->fetch(PDO::FETCH_ASSOC);
        // Safety/engagement indicator only. User ratings are kept separately.
        $score=max(0,min(100,58+(int)$counts['total']*6-(int)$counts['flagged']*24));
        $db->prepare('UPDATE conversations SET quality_score=?'.($newMessage?', last_message_at=CURRENT_TIMESTAMP':'').' WHERE id=?')->execute([$score,$id]);
        return $score;
    }

    public static function requireOpenCampaign(PDO $db, int $id): array
    {
        $stmt=$db->prepare("SELECT * FROM campaigns WHERE id=? AND status='active'");
        $stmt->execute([$id]); $campaign=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign || $campaign['deadline']<date('Y-m-d')) {
            throw new InvalidArgumentException('Chiến dịch đã hết hạn hoặc không còn nhận bài.');
        }
        return $campaign;
    }
}
