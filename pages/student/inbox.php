<?php
require_auth(['ambassador']);
$pageTitle = 'Hộp thư P2P';
$conversations = rows('SELECT c.*, p.name AS prospect_name, p.major, (SELECT content FROM messages WHERE conversation_id = c.id AND is_flagged = 0 ORDER BY id DESC LIMIT 1) AS last_message, (SELECT created_at FROM messages WHERE conversation_id = c.id AND is_flagged = 0 ORDER BY id DESC LIMIT 1) AS message_time FROM conversations c JOIN users p ON p.id = c.prospect_id WHERE c.ambassador_id = ? ORDER BY c.last_message_at DESC', [user()['id']]);
$selected = (int) ($_GET['conversation'] ?? ($conversations[0]['id'] ?? 0));
$ownedIds = array_map('intval', array_column($conversations, 'id'));
if (!in_array($selected, $ownedIds, true)) { $selected = $ownedIds[0] ?? 0; }
$myAppointments=rows("SELECT * FROM consultation_appointments WHERE ambassador_id=? AND status IN ('pending','confirmed') ORDER BY preferred_at",[user()['id']]);
?>
<?php if ($myAppointments): ?><details class="panel-card mb-3"><summary><?= count($myAppointments) ?> lịch tư vấn chờ xử lý / đã xác nhận</summary><div class="table-responsive"><table class="table"><thead><tr><th>Học sinh</th><th>Thời gian mong muốn</th><th>Nội dung</th><th>Trạng thái</th></tr></thead><tbody><?php foreach($myAppointments as $appointment): ?><tr><td><?= e($appointment['student_name']) ?></td><td><?= e(date('H:i d/m/Y',strtotime($appointment['preferred_at']))) ?></td><td><?= e($appointment['question']) ?></td><td><?= $appointment['status']==='confirmed'?'Đã xác nhận':'Chờ quản trị xác nhận' ?></td></tr><?php endforeach; ?></tbody></table></div></details><?php endif; ?>
<div class="inbox-shell" data-inbox-conversation="<?= $selected ?>">
    <aside class="inbox-list"><div class="inbox-list-head"><div><p class="eyebrow">TIN NHẮN</p><h3><?= count($conversations) ?> cuộc trò chuyện</h3></div><span class="presence online"></span></div><div class="inbox-search"><i class="bi bi-search"></i><input placeholder="Tìm theo tên..."></div><?php foreach ($conversations as $conversation): ?><a class="inbox-row <?= $selected === (int) $conversation['id'] ? 'active' : '' ?>" href="index.php?page=inbox&conversation=<?= (int) $conversation['id'] ?>"><span class="avatar avatar-sm"><?= e(initials($conversation['prospect_name'])) ?></span><div><strong><?= e($conversation['prospect_name']) ?></strong><small><?= e(mb_substr($conversation['last_message'] ?: 'Bắt đầu cuộc trò chuyện', 0, 48)) ?><?= mb_strlen($conversation['last_message'] ?? '') > 48 ? '…' : '' ?></small></div><time><?= $conversation['message_time'] ? date('H:i', strtotime($conversation['message_time'])) : '' ?></time></a><?php endforeach; ?></aside>
    <section class="inbox-room"><?php if ($selected): $info = rows('SELECT c.*, p.name AS prospect_name, p.major FROM conversations c JOIN users p ON p.id=c.prospect_id WHERE c.id=? AND c.ambassador_id=?', [$selected,user()['id']])[0] ?? null; ?><div class="inbox-room-head"><span class="avatar avatar-sm"><?= e(initials($info['prospect_name'])) ?></span><div><strong><?= e($info['prospect_name']) ?></strong><small>Quan tâm: <?= e($info['major'] ?: 'Chưa xác định') ?></small></div><span class="quality-chip"><i class="bi bi-activity"></i> Quality <?= (int) $info['quality_score'] ?>/100</span><span class="safety-badge"><i class="bi bi-shield-check"></i> Được bảo vệ</span></div><div class="message-list inbox-messages" id="messageList"></div><form class="chat-composer inbox-composer" id="messageForm"><input type="hidden" id="conversationId" value="<?= $selected ?>"><textarea id="messageInput" rows="1" placeholder="Trả lời như một người bạn..."></textarea><button type="submit" aria-label="Gửi tin nhắn"><i class="bi bi-send-fill"></i></button></form><?php else: ?><div class="empty-state"><i class="bi bi-chat-heart"></i><h2>Chưa có tin nhắn</h2><p>Khi học sinh chọn trò chuyện cùng bạn, cuộc hội thoại sẽ xuất hiện tại đây.</p></div><?php endif; ?></section>
    <aside class="inbox-profile"><?php if ($selected && isset($info)): ?><span class="avatar avatar-xl"><?= e(initials($info['prospect_name'])) ?></span><h3><?= e($info['prospect_name']) ?></h3><p>Học sinh THPT</p><div class="profile-fact"><span><i class="bi bi-book"></i></span><div><small>Ngành quan tâm</small><strong><?= e($info['major'] ?: 'Đang khám phá') ?></strong></div></div><div class="profile-fact"><span><i class="bi bi-arrow-left-right"></i></span><div><small>Trạng thái hỗ trợ</small><strong><?= e(match ($info['crm_status']) { 'active' => 'Đang hỗ trợ', 'resolved' => 'Đã giải đáp', default => 'Mới' }) ?></strong></div></div><?php if (!empty($info['is_escalated']) && $info['escalation_status'] !== 'answered'): ?><div class="profile-fact"><span class="text-warning"><i class="bi bi-shield-exclamation"></i></span><div><small>Chuyển tuyến cán bộ</small><strong class="text-warning"><?= e($info['escalation_status'] === 'answered' ? 'Đã có xác nhận' : 'Đang chờ Ban Tuyển sinh') ?></strong></div></div><?php else: ?><button class="btn btn-sm btn-outline-warning w-100 mt-2" type="button" data-bs-toggle="modal" data-bs-target="#escalateModal"><i class="bi bi-arrow-up-right-circle"></i> Chuyển Ban Tuyển sinh</button><?php endif; ?><div class="conversation-tips"><strong>Gợi ý trò chuyện</strong><p><i class="bi bi-check-circle-fill"></i> Chia sẻ trải nghiệm cá nhân</p><p><i class="bi bi-check-circle-fill"></i> Không hứa hẹn về tuyển sinh</p><p><i class="bi bi-check-circle-fill"></i> Dẫn nguồn khi nói về quy chế</p></div><?php endif; ?></aside>
</div>
<?php if ($selected && isset($info) && (empty($info['is_escalated']) || $info['escalation_status'] === 'answered')): ?>
<div class="modal fade" id="escalateModal" tabindex="-1" aria-labelledby="escalateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="actions.php?action=escalate_question">
                <?= csrf_field() ?>
                <input type="hidden" name="conversation_id" value="<?= $selected ?>">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-0 text-warning">PHÂN ĐỊNH TRÁCH NHIỆM (BẢNG 23 & SƠ ĐỒ 7)</p>
                        <h2 class="modal-title fs-5" id="escalateModalLabel">Chuyển câu hỏi đến Ban Tuyển sinh</h2>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Đại sứ trả lời trong phạm vi trải nghiệm học tập và đời sống. Các câu hỏi về <strong>học phí, học bổng, điều kiện xét tuyển, hồ sơ chính sách</strong> sẽ được chuyển đến cán bộ có thẩm quyền xác nhận chính thức.</p>
                    <label class="form-label fw-semibold" for="escalateReason">Nội dung / Lý do cần cán bộ xác nhận:</label>
                    <textarea class="form-control" id="escalateReason" name="reason" rows="3" placeholder="Ví dụ: Học sinh hỏi về điều kiện duy trì học bổng hoặc chính sách xét tuyển..." required></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Hủy</button>
                    <button class="btn btn-brand" type="submit"><i class="bi bi-send-check"></i> Xác nhận chuyển tuyến</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
