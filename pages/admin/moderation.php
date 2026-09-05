<?php
ChatPrivacy::requireAccess();
$pageTitle = 'Kiểm duyệt hội thoại';
$conversations = rows("SELECT c.*, p.name AS prospect_name, a.name AS ambassador_name, COUNT(m.id) AS message_count, SUM(m.is_flagged) AS flagged_count FROM conversations c JOIN users p ON p.id = c.prospect_id JOIN users a ON a.id = c.ambassador_id LEFT JOIN messages m ON m.conversation_id = c.id GROUP BY c.id HAVING SUM(m.is_flagged)>0 OR c.escalation_status='pending' ORDER BY flagged_count DESC, c.last_message_at DESC");
$selected = (int) ($_GET['conversation'] ?? ($conversations[0]['id'] ?? 0));
$messages = [];
$selectedConversation = null;
foreach ($conversations as $conversation) {
    if ((int) $conversation['id'] === $selected) {
        $selectedConversation = $conversation;
        break;
    }
}
if ($selectedConversation) {
    $messages = ChatPrivacy::context($db, $selected);
    ChatPrivacy::audit($db, (int)user()['id'], $selected, 'review_opened', array_column($messages, 'id'));
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
            <a class="conversation-row <?= $selected === (int) $conversation['id'] ? 'active' : '' ?>" <?= $selected === (int) $conversation['id'] ? 'aria-current="true"' : '' ?> href="index.php?page=admin-moderation&conversation=<?= (int) $conversation['id'] ?>">
                <span class="avatar avatar-sm"><?= e(initials($conversation['prospect_name'])) ?></span>
                <div><strong><?= e($conversation['prospect_name']) ?></strong><small>với <?= e($conversation['ambassador_name']) ?></small><em><?= (int) $conversation['message_count'] ?> tin nhắn</em></div>
                <?php if ($conversation['flagged_count']): ?><b aria-label="<?= (int) $conversation['flagged_count'] ?> tin bị đánh dấu"><i class="bi bi-flag-fill" aria-hidden="true"></i> <?= (int) $conversation['flagged_count'] ?></b><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if (!$conversations): ?><p class="moderation-empty-list">Không có tin nhắn bị đánh dấu hoặc câu hỏi chuyển tiếp cần xử lý.</p><?php endif; ?>
    </aside>

    <section class="moderation-chat panel-card">
        <div class="panel-head"><div><p class="eyebrow">CHẾ ĐỘ KIỂM DUYỆT</p><h3><?= $selectedConversation ? 'Vụ việc #' . $selected : 'Nội dung cần xử lý' ?></h3></div><span class="panel-chip"><?= count($messages) ?> tin nhắn</span></div>
        <?php if ($selectedConversation): ?>
            <div class="conversation-quality">
                <div><span>Điểm chất lượng</span><strong><?= (int) $selectedConversation['quality_score'] ?>/100</strong></div>
                <div class="quality-meter" aria-hidden="true"><i style="width: <?= max(0, min(100, (int) $selectedConversation['quality_score'])) ?>%"></i></div>
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

        <?php if ($selectedConversation && !empty($selectedConversation['is_escalated'])): ?>
            <div class="escalation-banner p-3 mb-3 rounded-2 bg-light border border-warning">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge text-bg-warning"><i class="bi bi-shield-exclamation"></i> Cần phản hồi từ nhà trường</span>
                    <small class="text-muted">Trạng thái: <strong><?= e($selectedConversation['escalation_status'] === 'answered' ? 'Đã phản hồi' : 'Chờ xác nhận') ?></strong></small>
                </div>
                <p class="mb-2"><strong>Nội dung đại sứ chuyển tiếp:</strong> <?= e($selectedConversation['escalation_reason']) ?></p>
                <?php if ($selectedConversation['escalation_status'] !== 'answered'): ?>
                    <form method="post" action="actions.php?action=answer_escalated_question" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="conversation_id" value="<?= $selected ?>">
                        <input class="form-control form-control-sm" name="official_answer" placeholder="Nhập xác nhận chính thức từ Ban Tuyển sinh..." required>
                        <button class="btn btn-sm btn-brand text-nowrap" type="submit"><i class="bi bi-check-circle"></i> Gửi xác nhận</button>
                    </form>
                <?php else: ?>
                    <p class="mb-0 small text-success"><i class="bi bi-check2-circle"></i> <strong>Đã xác nhận:</strong> <?= e($selectedConversation['official_answer']) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="moderation-messages">
            <?php if (!$messages): ?>
                <div class="moderation-empty"><strong><?= $selectedConversation ? 'Chưa có tin nhắn' : 'Chưa có hội thoại được chọn' ?></strong><p><?= $selectedConversation ? 'Chỉ hiển thị câu hỏi được chuyển tiếp ở trên; không mở lịch sử tin nhắn.' : 'Chọn một vụ việc để xem nội dung cần xử lý.' ?></p></div>
            <?php endif; ?>
            <?php foreach ($messages as $message): ?>
                <?php
                $categories = json_decode((string) ($message['moderation_categories'] ?? '[]'), true);
                $categories = is_array($categories) ? $categories : [];
                $isAi = ($message['moderation_provider'] ?? '') === 'ai-compatible';
                ?>
                <div class="moderation-message <?= $message['sender_role'] === 'ambassador' ? 'ambassador' : '' ?> <?= $message['is_flagged'] ? 'flagged' : '' ?>">
                    <div class="message-meta"><strong><?= e($message['sender_name']) ?></strong><span><?= date('H:i d/m', strtotime($message['created_at'])) ?></span></div>
                    <?php if ($message['is_flagged']): ?><span class="moderation-flag-label"><i class="bi bi-flag-fill" aria-hidden="true"></i> Đã ẩn · Cần kiểm duyệt</span><?php endif; ?>
                    <p><?= e($message['content']) ?></p>
                    <?php if ($message['is_flagged']): ?>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <?php foreach ($categories as $category): ?><span class="badge text-bg-danger"><?= e($moderationCategoryLabels[$category] ?? $category) ?></span><?php endforeach; ?>
                            <span class="badge text-bg-secondary"><?= $isAi ? 'AI · ' . e((string) ($message['moderation_model'] ?? '')) : 'Bộ lọc nội bộ' ?></span>
                            <?php if ($message['moderation_confidence'] !== null): ?><span class="badge text-bg-warning"><?= (int) round((float) $message['moderation_confidence'] * 100) ?>% tin cậy</span><?php endif; ?>
                        </div>
                        <?php if (!empty($message['moderation_reason'])): ?><p class="small text-danger mb-2"><strong>Lý do:</strong> <?= e($message['moderation_reason']) ?></p><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($message['is_flagged']): ?><form method="post" action="actions.php?action=flag_message">
                        <?= csrf_field() ?>
                        <input type="hidden" name="message_id" value="<?= (int) $message['id'] ?>">
                        <button class="btn btn-sm <?= $message['is_flagged'] ? 'btn-danger' : 'btn-light' ?>"><i class="bi bi-flag"></i> <?= $message['is_flagged'] ? 'Cho phép hiển thị' : 'Ẩn & đánh dấu' ?></button>
                    </form><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="safety-note"><i class="bi bi-shield-lock-fill"></i><p><strong>Quyền riêng tư & an toàn</strong><small>Chỉ hiển thị tối đa 5 tin bị đánh dấu, mỗi tin kèm 2 tin trước và 2 tin sau. Truy cập được ghi nhật ký; không có quyền mở toàn bộ lịch sử.</small></p></div>
    </section>
</div>
