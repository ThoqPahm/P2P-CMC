<?php
require_auth(['admin']);
$pageTitle = 'Kiểm duyệt hội thoại';
$conversations = rows('SELECT c.*, p.name AS prospect_name, a.name AS ambassador_name, COUNT(m.id) AS message_count, SUM(m.is_flagged) AS flagged_count FROM conversations c JOIN users p ON p.id = c.prospect_id JOIN users a ON a.id = c.ambassador_id LEFT JOIN messages m ON m.conversation_id = c.id GROUP BY c.id ORDER BY flagged_count DESC, c.last_message_at DESC');
$selected = (int) ($_GET['conversation'] ?? ($conversations[0]['id'] ?? 0));
$messages = $selected ? rows('SELECT m.*, u.name AS sender_name, u.role AS sender_role FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.conversation_id = ? ORDER BY m.id', [$selected]) : [];
$selectedConversation = null;
foreach ($conversations as $conversation) {
    if ((int) $conversation['id'] === $selected) {
        $selectedConversation = $conversation;
        break;
    }
}
$moderationCategoryLabels = [
    'harassment' => 'Quấy rối',
    'harassment/threatening' => 'Đe dọa',
    'hate' => 'Thù ghét',
    'hate/threatening' => 'Thù ghét & đe dọa',
    'illicit' => 'Hành vi sai trái',
    'illicit/violent' => 'Sai trái & bạo lực',
    'self-harm' => 'Tự hại',
    'self-harm/instructions' => 'Hướng dẫn tự hại',
    'self-harm/intent' => 'Ý định tự hại',
    'sexual' => 'Tình dục',
    'sexual/minors' => 'Tình dục trẻ vị thành niên',
    'violence' => 'Bạo lực',
    'violence/graphic' => 'Bạo lực trực diện',
    'spam' => 'Spam / lừa đảo',
    'personal-data' => 'Dữ liệu cá nhân',
];
?>
<div class="moderation-shell">
    <aside class="conversation-list">
        <div class="conversation-list-head"><p class="eyebrow">HỘI THOẠI</p><h3><?= count($conversations) ?> cuộc trò chuyện</h3></div>
        <?php foreach ($conversations as $conversation): ?>
            <a class="conversation-row <?= $selected === (int) $conversation['id'] ? 'active' : '' ?>" href="index.php?page=admin-moderation&conversation=<?= (int) $conversation['id'] ?>">
                <span class="avatar avatar-sm"><?= e(initials($conversation['prospect_name'])) ?></span>
                <div><strong><?= e($conversation['prospect_name']) ?></strong><small>với <?= e($conversation['ambassador_name']) ?></small><em><?= (int) $conversation['message_count'] ?> tin nhắn</em></div>
                <?php if ($conversation['flagged_count']): ?><b><i class="bi bi-flag-fill"></i> <?= (int) $conversation['flagged_count'] ?></b><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </aside>

    <section class="moderation-chat panel-card">
        <div class="panel-head"><div><p class="eyebrow">CHẾ ĐỘ KIỂM DUYỆT</p><h3>Nội dung cuộc trò chuyện #<?= $selected ?></h3></div><span class="panel-chip"><i></i> <?= count($messages) ?> tin nhắn</span></div>
        <?php if ($selectedConversation): ?>
            <div class="conversation-quality">
                <div><span>Quality score</span><strong><?= (int) $selectedConversation['quality_score'] ?>/100</strong></div>
                <div class="quality-meter"><i style="width: <?= (int) $selectedConversation['quality_score'] ?>%"></i></div>
                <form method="post" action="actions.php?action=update_support_status">
                    <?= csrf_field() ?>
                    <input type="hidden" name="conversation_id" value="<?= $selected ?>">
                    <label class="visually-hidden" for="supportStatus">Trạng thái hỗ trợ</label>
                    <select class="form-select form-select-sm" id="supportStatus" name="support_status">
                        <option value="new" <?= $selectedConversation['crm_status'] === 'new' ? 'selected' : '' ?>>Mới</option>
                        <option value="active" <?= $selectedConversation['crm_status'] === 'active' ? 'selected' : '' ?>>Đang hỗ trợ</option>
                        <option value="resolved" <?= $selectedConversation['crm_status'] === 'resolved' ? 'selected' : '' ?>>Đã giải đáp</option>
                    </select>
                    <button class="btn btn-sm btn-brand">Cập nhật</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="moderation-messages">
            <?php foreach ($messages as $message): ?>
                <?php
                $categories = json_decode((string) ($message['moderation_categories'] ?? '[]'), true);
                $categories = is_array($categories) ? $categories : [];
                $isAi = ($message['moderation_provider'] ?? '') === 'ai-compatible';
                ?>
                <div class="moderation-message <?= $message['sender_role'] === 'ambassador' ? 'ambassador' : '' ?> <?= $message['is_flagged'] ? 'flagged' : '' ?>">
                    <div class="message-meta"><strong><?= e($message['sender_name']) ?></strong><span><?= date('H:i d/m', strtotime($message['created_at'])) ?></span></div>
                    <p><?= e($message['content']) ?></p>
                    <?php if ($message['is_flagged']): ?>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <?php foreach ($categories as $category): ?><span class="badge text-bg-danger"><?= e($moderationCategoryLabels[$category] ?? $category) ?></span><?php endforeach; ?>
                            <span class="badge text-bg-secondary"><?= $isAi ? 'AI · ' . e((string) ($message['moderation_model'] ?? '')) : 'Bộ lọc nội bộ' ?></span>
                            <?php if ($message['moderation_confidence'] !== null): ?><span class="badge text-bg-warning"><?= (int) round((float) $message['moderation_confidence'] * 100) ?>% tin cậy</span><?php endif; ?>
                        </div>
                        <?php if (!empty($message['moderation_reason'])): ?><p class="small text-danger mb-2"><strong>Lý do:</strong> <?= e($message['moderation_reason']) ?></p><?php endif; ?>
                    <?php endif; ?>
                    <form method="post" action="actions.php?action=flag_message">
                        <?= csrf_field() ?>
                        <input type="hidden" name="message_id" value="<?= (int) $message['id'] ?>">
                        <button class="btn btn-sm <?= $message['is_flagged'] ? 'btn-danger' : 'btn-light' ?>"><i class="bi bi-flag"></i> <?= $message['is_flagged'] ? 'Cho phép hiển thị' : 'Ẩn & đánh dấu' ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="safety-note"><i class="bi bi-shield-lock-fill"></i><p><strong>Quyền riêng tư & an toàn</strong><small>Tin bị hệ thống đánh dấu sẽ được ẩn khỏi cuộc trò chuyện cho tới khi quản trị viên cho phép.</small></p></div>
    </section>
</div>
