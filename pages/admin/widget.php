<?php
require_auth(['admin']);
$pageTitle = 'Widget website';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8765';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
$baseUrl = $scheme . '://' . $host . ($basePath === '' ? '' : $basePath);
$widgetUrl = $baseUrl . '/index.php?page=widget';
$scriptUrl = $baseUrl . '/assets/js/eambassador-widget.js';
$embedCode = '<script src="' . $scriptUrl . '" data-widget-url="' . $widgetUrl . '" data-position="right" async></script>';
$appointments = rows(<<<'SQL'
    SELECT ca.*, u.name AS ambassador_name, u.major AS ambassador_major
    FROM consultation_appointments ca
    JOIN users u ON u.id = ca.ambassador_id
    ORDER BY CASE ca.status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 ELSE 2 END, ca.preferred_at ASC
    LIMIT 12
SQL);
$pendingAppointments = (int) scalar("SELECT COUNT(*) FROM consultation_appointments WHERE status = 'pending'");
$onlineAmbassadors = (int) scalar("SELECT COUNT(*) FROM users WHERE role = 'ambassador' AND status = 'active' AND is_online = 1");
?>
<div class="widget-admin-head">
    <div><h2>Đưa eAmbassador lên website chính thức</h2><p>Một điểm chạm tập trung để học sinh tìm đúng đại sứ, trò chuyện ngay hoặc đặt lịch khi người phù hợp đang offline.</p></div>
    <button class="btn btn-brand" type="button" data-bs-toggle="modal" data-bs-target="#widgetDemo"><i class="bi bi-play-circle-fill"></i> Xem demo học sinh</button>
</div>

<div class="widget-admin-layout">
    <section class="widget-build-panel">
        <header><div><h3>Mã nhúng đang sẵn sàng</h3><p>Dán một dòng trước thẻ đóng <code>&lt;/body&gt;</code> của website.</p></div><span class="widget-ready"><i></i> Hoạt động</span></header>
        <div class="embed-code-block"><code><?= e($embedCode) ?></code><button type="button" data-copy="<?= e($embedCode) ?>"><i class="bi bi-copy"></i> Sao chép mã</button></div>
        <div class="embed-options">
            <div><span class="option-icon"><i class="bi bi-window-stack"></i></span><p><strong>Hiển thị</strong><small>Nút nổi góc phải, tự mở thành cửa sổ tư vấn.</small></p></div>
            <div><span class="option-icon"><i class="bi bi-phone"></i></span><p><strong>Responsive</strong><small>Desktop dạng panel; mobile tự chuyển toàn màn hình.</small></p></div>
            <div><span class="option-icon"><i class="bi bi-shield-check"></i></span><p><strong>Độc lập giao diện</strong><small>Chạy trong iframe, không xung đột CSS website chính.</small></p></div>
        </div>
        <details class="embed-guide"><summary>Hướng dẫn tích hợp nhanh <i class="bi bi-chevron-down"></i></summary><ol><li>Mở phần quản trị mã nguồn website chính thức.</li><li>Dán đoạn script vào layout chung, ngay trước <code>&lt;/body&gt;</code>.</li><li>Xuất bản và kiểm tra nút “Hỏi đại sứ CMC” ở góc phải.</li></ol><p>Đổi <code>data-position="right"</code> thành <code>left</code> nếu cần đặt nút ở góc trái.</p></details>
    </section>

    <aside class="widget-admin-summary">
        <div class="widget-live-preview"><div class="preview-site"><span></span><span></span><span></span><div><b>WEBSITE CMC UNIVERSITY</b><small>Nội dung website chính thức</small></div></div><button type="button" data-bs-toggle="modal" data-bs-target="#widgetDemo"><span><i class="bi bi-chat-dots-fill"></i></span> Hỏi đại sứ CMC</button></div>
        <dl><div><dt>Đại sứ online</dt><dd><?= $onlineAmbassadors ?></dd></div><div><dt>Lịch chờ xác nhận</dt><dd><?= $pendingAppointments ?></dd></div><div><dt>Bộ lọc khả dụng</dt><dd>4</dd></div></dl>
        <a href="<?= e($widgetUrl) ?>" target="_blank" rel="noopener">Mở widget trong tab mới <i class="bi bi-arrow-up-right"></i></a>
    </aside>
</div>

<section class="panel-card widget-appointment-panel">
    <div class="panel-head"><div><h3>Lịch tư vấn từ widget</h3><p>Yêu cầu của học sinh khi đại sứ được chọn đang offline.</p></div><span class="panel-chip"><?= $pendingAppointments ?> lịch đang chờ</span></div>
    <div class="table-responsive"><table class="table clean-table align-middle"><thead><tr><th>Học sinh</th><th>Đại sứ</th><th>Thời gian mong muốn</th><th>Nội dung</th><th>Trạng thái</th><th></th></tr></thead><tbody>
        <?php foreach ($appointments as $appointment): ?><tr><td><strong><?= e($appointment['student_name']) ?></strong><small class="d-block text-muted"><?= e($appointment['email']) ?><?= $appointment['phone'] ? ' · ' . e($appointment['phone']) : '' ?></small></td><td><strong><?= e($appointment['ambassador_name']) ?></strong><small class="d-block text-muted"><?= e($appointment['ambassador_major']) ?></small></td><td><?= date('H:i d/m/Y', strtotime($appointment['preferred_at'])) ?></td><td><span class="appointment-question"><?= e($appointment['question'] ?: 'Chưa ghi nội dung') ?></span></td><td><span class="status-label status-<?= $appointment['status'] === 'completed' ? 'success' : ($appointment['status'] === 'cancelled' ? 'danger' : 'warning') ?>"><?= e(match ($appointment['status']) { 'confirmed' => 'Đã xác nhận', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy', default => 'Chờ xác nhận' }) ?></span></td><td><form method="post" action="actions.php?action=update_appointment_status" class="appointment-action"><?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>"><select class="form-select form-select-sm" name="status"><option value="pending" <?= $appointment['status'] === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option><option value="confirmed" <?= $appointment['status'] === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option><option value="completed" <?= $appointment['status'] === 'completed' ? 'selected' : '' ?>>Hoàn thành</option><option value="cancelled" <?= $appointment['status'] === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option></select><button class="btn btn-sm btn-light border" type="submit">Lưu</button></form></td></tr><?php endforeach; ?>
        <?php if (!$appointments): ?><tr><td colspan="6"><div class="widget-appointments-empty"><i class="bi bi-calendar2-check"></i><div><strong>Chưa có lịch tư vấn</strong><p>Các yêu cầu đặt lịch từ widget sẽ xuất hiện tại đây.</p></div></div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>

<div class="modal fade" id="widgetDemo" tabindex="-1" aria-labelledby="widgetDemoTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content widget-demo-modal"><div class="modal-header"><div><h2 class="modal-title" id="widgetDemoTitle">Góc nhìn của học sinh</h2><p>Demo đầy đủ bộ lọc, hồ sơ đại sứ, chat và đặt lịch tư vấn.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><div class="demo-browser-bar"><span></span><span></span><span></span><p><i class="bi bi-lock-fill"></i> tuyensinh.cmc-u.edu.vn</p></div><iframe src="<?= e($widgetUrl) ?>" title="Demo widget eAmbassador"></iframe></div></div></div></div>
