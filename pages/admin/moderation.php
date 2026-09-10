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
$questionInsights = [
    'period' => '30 ngày gần đây',
    'total' => 126,
    'repeat_groups' => 4,
    'unanswered' => 7,
    'repeated' => [
        ['question' => 'Người hướng nội có phù hợp với ngành Digital Marketing không?', 'count' => 18, 'trend' => '+38%', 'topic' => 'Marketing có cần hướng ngoại?'],
        ['question' => 'Chưa biết lập trình thì có học Công nghệ thông tin được không?', 'count' => 15, 'trend' => '+25%', 'topic' => 'Bắt đầu ngành CNTT từ con số 0'],
        ['question' => 'Học phí và học bổng năm 2026 được áp dụng như thế nào?', 'count' => 13, 'trend' => '+18%', 'topic' => 'Giải đáp học phí và học bổng 2026'],
        ['question' => 'Sinh viên năm nhất có cơ hội tham gia dự án hoặc thực tập chưa?', 'count' => 9, 'trend' => '+12%', 'topic' => 'Cơ hội trải nghiệm nghề nghiệp từ năm nhất'],
    ],
    'unresolved' => [
        ['question' => 'Điểm chuẩn năm 2026 chính thức là bao nhiêu?', 'reason' => 'Nhà trường chưa công bố dữ liệu chính thức', 'owner' => 'Ban Tuyển sinh'],
        ['question' => 'Sinh viên có được chuyển ngành sau học kỳ đầu tiên không?', 'reason' => 'Thiếu quy định chuyển ngành trong kho dữ liệu', 'owner' => 'Phòng Đào tạo'],
        ['question' => 'Chính sách ký túc xá áp dụng cho cơ sở nào?', 'reason' => 'Thông tin hiện có chưa đủ để kết luận', 'owner' => 'Phòng Công tác sinh viên'],
    ],
];
?>
<div class="moderation-page-actions">
    <p>Tập trung xử lý nội dung được đánh dấu và các câu hỏi cần nhà trường xác nhận.</p>
    <button class="btn btn-brand insight-launcher" type="button" data-bs-toggle="modal" data-bs-target="#questionInsightsModal">
        <i class="bi bi-lightbulb" aria-hidden="true"></i>
        Insight câu hỏi
        <span><?= (int) $questionInsights['unanswered'] ?></span>
    </button>
</div>
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

<div class="modal fade question-insights-modal" id="questionInsightsModal" tabindex="-1" aria-labelledby="questionInsightsTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header insight-modal-head">
                <div>
                    <div class="insight-heading-line">
                        <span class="insight-heading-icon"><i class="bi bi-lightbulb" aria-hidden="true"></i></span>
                        <div>
                            <p class="eyebrow mb-1">PHẢN HỒI TỪ HỌC SINH</p>
                            <h2 class="modal-title" id="questionInsightsTitle">Insight câu hỏi</h2>
                        </div>
                    </div>
                    <p class="insight-modal-intro">Nhận diện nhu cầu lặp lại để bổ sung dữ liệu tư vấn và phát triển chủ đề truyền thông phù hợp.</p>
                </div>
                <div class="insight-head-meta">
                    <label class="insight-period-control" for="insightPeriod">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                        <span class="visually-hidden">Khoảng thời gian tổng hợp</span>
                        <select id="insightPeriod" aria-label="Khoảng thời gian tổng hợp">
                            <option value="7">7 ngày gần đây</option>
                            <option value="30" selected>30 ngày gần đây</option>
                            <option value="90">90 ngày gần đây</option>
                        </select>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </label>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
            </div>
            <div class="modal-body insight-modal-body">
                <div class="insight-loading" hidden aria-hidden="true">
                    <div class="insight-loading-status"><span class="insight-loading-mark"><i></i><i></i><i></i></span><strong>Đang tổng hợp câu hỏi học sinh…</strong></div>
                    <div class="insight-skeleton-summary"><i></i><i></i><i></i></div>
                    <div class="insight-skeleton-columns"><div><i></i><i></i><i></i><i></i></div><div><i></i><i></i><i></i></div></div>
                </div>
                <div class="insight-results" aria-live="polite">
                <div class="insight-summary" aria-label="Tổng quan câu hỏi">
                    <article><span>Tổng câu hỏi</span><strong data-insight-total><?= (int) $questionInsights['total'] ?></strong><small>đã tổng hợp</small></article>
                    <article><span>Nhóm lặp lại</span><strong><?= (int) $questionInsights['repeat_groups'] ?></strong><small>chủ đề nổi bật</small></article>
                    <article class="needs-action"><span>Chưa giải đáp</span><strong data-insight-unanswered><?= (int) $questionInsights['unanswered'] ?></strong><small>cần bổ sung dữ liệu</small></article>
                </div>

                <div class="insight-columns">
                    <section class="insight-section">
                        <div class="insight-section-head">
                            <div><span class="section-kicker">NHU CẦU NỔI BẬT</span><h3>Câu hỏi lặp lại nhiều</h3></div>
                            <span class="section-count"><?= count($questionInsights['repeated']) ?> nhóm</span>
                        </div>
                        <div class="repeated-question-list">
                            <?php foreach ($questionInsights['repeated'] as $index => $insight): ?>
                                <article class="question-insight-row" data-question-index="<?= $index ?>">
                                    <span class="question-rank"><?= $index + 1 ?></span>
                                    <div class="question-insight-copy">
                                        <h4><?= e($insight['question']) ?></h4>
                                        <p><i class="bi bi-megaphone" aria-hidden="true"></i> Chủ đề đề xuất: <strong><?= e($insight['topic']) ?></strong></p>
                                    </div>
                                    <div class="question-volume"><strong data-repeat-count><?= (int) $insight['count'] ?></strong><span>lượt hỏi</span><em data-repeat-trend><?= e($insight['trend']) ?></em></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="insight-section unresolved-section">
                        <div class="insight-section-head">
                            <div><span class="section-kicker">CẦN PHỐI HỢP</span><h3>Câu hỏi chưa thể giải đáp</h3></div>
                            <span class="section-count is-warning"><b data-insight-unanswered><?= (int) $questionInsights['unanswered'] ?></b> câu</span>
                        </div>
                        <div class="unresolved-question-list">
                            <?php foreach ($questionInsights['unresolved'] as $item): ?>
                                <article class="unresolved-question">
                                    <span class="unresolved-icon"><i class="bi bi-exclamation-circle" aria-hidden="true"></i></span>
                                    <div><h4><?= e($item['question']) ?></h4><p><?= e($item['reason']) ?></p><span><i class="bi bi-arrow-up-right" aria-hidden="true"></i> Chuyển đến <?= e($item['owner']) ?></span></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <p class="unresolved-more"><i class="bi bi-info-circle" aria-hidden="true"></i> Còn 4 câu hỏi đơn lẻ đang chờ phân loại.</p>
                    </section>
                </div>
                </div>
            </div>
            <div class="modal-footer insight-modal-footer">
                <p><i class="bi bi-shield-check" aria-hidden="true"></i> Insight chỉ dùng dữ liệu đã tổng hợp, không hiển thị danh tính hoặc toàn bộ hội thoại.</p>
                <div><button class="btn btn-light border" type="button" data-bs-dismiss="modal">Đóng</button><a class="btn btn-brand" href="index.php?page=admin-campaigns"><i class="bi bi-megaphone" aria-hidden="true"></i> Mở quản lý chiến dịch</a></div>
            </div>
        </div>
    </div>
</div>
