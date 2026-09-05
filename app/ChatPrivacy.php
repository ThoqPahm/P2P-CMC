<?php
declare(strict_types=1);

final class ChatPrivacy
{
    public static function allowed(array $account): bool
    {
        if (($account['role']??'') !== 'admin' || ($account['status']??'') !== 'active') { return false; }
        $emails = preg_split('/\s*,\s*/', strtolower(trim((string)getenv('CHAT_MODERATOR_EMAILS')))) ?: [];
        return is_super_admin($account) || in_array(strtolower($account['email']), $emails, true);
    }

    public static function requireAccess(): void
    {
        require_auth(['admin']);
        if (!self::allowed(user())) { http_response_code(403); exit('Bạn chưa được phân công kiểm duyệt tin nhắn.'); }
    }

    public static function pending(PDO $db, int $id): bool
    {
        $s=$db->prepare("SELECT 1 FROM conversations c WHERE c.id=? AND (c.escalation_status='pending' OR EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id=c.id AND m.is_flagged=1))");
        $s->execute([$id]); return (bool)$s->fetchColumn();
    }

    public static function context(PDO $db, int $id): array
    {
        // At most five flagged messages, with two neighbors either side each.
        // An escalation alone grants no access to the original messages.
        $s=$db->prepare('SELECT id FROM messages WHERE conversation_id=? AND is_flagged=1 ORDER BY id DESC LIMIT 5');
        $s->execute([$id]); $ids=[];
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $flag) {
            foreach (['id<=? ORDER BY id DESC LIMIT 3','id>? ORDER BY id LIMIT 2'] as $range) {
                $q=$db->prepare('SELECT id FROM messages WHERE conversation_id=? AND '.$range);
                $q->execute([$id,$flag]); $ids=array_merge($ids,$q->fetchAll(PDO::FETCH_COLUMN));
            }
        }
        $ids=array_values(array_unique(array_map('intval',$ids)));
        if (!$ids) { return []; }
        $q=$db->prepare('SELECT m.*,u.name AS sender_name,u.role AS sender_role FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.conversation_id=? AND m.id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY m.id');
        $q->execute([$id,...$ids]); return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function audit(PDO $db, int $actor, int $id, string $action, array $ids=[]): void
    {
        $db->prepare('INSERT INTO program_audit(actor_id,entity,entity_id,action,snapshot) VALUES(?,?,?,?,?)')->execute([$actor,'chat_privacy',$id,$action,json_encode(['message_ids'=>$ids,'scope'=>'flagged_context_or_forwarded_question'],JSON_THROW_ON_ERROR)]);
    }
}
