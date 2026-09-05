<?php
require_auth(['admin']);
$pageTitle = 'Widget website';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8765';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
$baseUrl = $scheme . '://' . $host . ($basePath === '' ? '' : $basePath);
$widgetUrl = $baseUrl . '/index.php?page=widget';
$scriptUrl = $baseUrl . '/assets/js/eambassador-widget.js?v=2';
$embedCode = '<script src="' . $scriptUrl . '" data-widget-url="' . $widgetUrl . '" data-position="right" async></script>';
$appointments = rows(<<<'SQL'
    SELECT ca.*, u.name AS ambassador_name, u.major AS ambassador_major
    FROM consultation_appointments ca
    JOIN users u ON u.id = ca.ambassador_id
    ORDER BY CASE ca.status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 ELSE 2 END, ca.preferred_at ASC
    LIMIT 12
SQL);
$pendingAppointments = (int) scalar("SELECT COUNT(*) FROM consultation_appointments WHERE status = 'pending'");
$onlineAmbassadors = (int) scalar("SELECT COUNT(*) FROM eligible_ambassadors WHERE 1=1 AND is_online = 1");
?>
<div class="widget-admin-head">
    <div><h2>Tích hợp widget & quản lý lịch tư vấn</h2><p>Lấy mã nhúng cho website, xem trải nghiệm học sinh và xử lý yêu cầu đặt lịch tại một nơi.</p></div>
    <button class="btn btn-brand" type="button" data-bs-toggle="modal" data-bs-target="#widgetDemo"><i class="bi bi-play-circle-fill"></i> Xem demo học sinh</button>
</div>

<div class="widget-admin-layout">
    <section class="widget-build-panel">
        <header><div><h3>Mã nhúng website</h3><p>Dán đoạn mã vào layout chung, trước thẻ <code>&lt;/body&gt;</code>.</p></div><span class="widget-ready">Sẵn sàng sao chép</span></header>
        <div class="embed-code-block"><div class="embed-code-toolbar"><span>HTML / JavaScript</span><button type="button" data-copy="<?= e($embedCode) ?>"><i class="bi bi-copy"></i> Sao chép mã</button></div><code><?= e($embedCode) ?></code></div>
        <?php if (in_array(strtolower(explode(':', $host)[0]), ['localhost', '127.0.0.1'], true)): ?><p class="embed-local-note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Đây là mã nhúng từ máy local. Khi triển khai, hãy mở trang này trên tên miền thật để lấy đúng URL.</span></p><?php endif; ?>
        <div class="embed-options">
            <div><span class="option-icon"><i class="bi bi-window-stack"></i></span><p><strong>Hiển thị</strong><small>Nút nổi góc phải, tự mở thành cửa sổ tư vấn.</small></p></div>
            <div><span class="option-icon"><i class="bi bi-phone"></i></span><p><strong>Responsive</strong><small>Desktop dạng panel; mobile tự chuyển toàn màn hình.</small></p></div>
            <div><span class="option-icon"><i class="bi bi-shield-check"></i></span><p><strong>Độc lập giao diện</strong><small>Chạy trong iframe, không xung đột CSS website chính.</small></p></div>
        </div>
        <details class="embed-guide"><summary>Hướng dẫn tích hợp nhanh <i class="bi bi-chevron-down"></i></summary><ol><li>Mở phần quản trị mã nguồn website chính thức.</li><li>Dán đoạn script vào layout chung, ngay trước <code>&lt;/body&gt;</code>.</li><li>Xuất bản và kiểm tra nút “Hỏi đại sứ CMC” ở góc phải.</li></ol><p>Đổi <code>data-position="right"</code> thành <code>left</code> nếu cần đặt nút ở góc trái.</p></details>
    </section>

    <aside class="widget-admin-summary">
        <h3>Tình hình hỗ trợ</h3>
        <p class="widget-summary-note">Số liệu tại thời điểm tải trang.</p>
        <dl><div><dt>Đại sứ online</dt><dd><?= $onlineAmbassadors ?></dd></div><div><dt>Lịch chờ xác nhận</dt><dd><?= $pendingAppointments ?></dd></div></dl>
        <a class="widget-queue-link" href="#widgetAppointments">Xử lý lịch tư vấn <i class="bi bi-chevron-down"></i></a>
        <div class="widget-summary-divider"></div>
        <h3>Kiểm tra trước khi nhúng</h3>
        <p class="widget-summary-note">Mở trải nghiệm hiện tại để kiểm tra hiển thị và các thao tác phía học sinh.</p>
        <a href="<?= e($widgetUrl) ?>" target="_blank" rel="noopener">Mở widget trong tab mới <i class="bi bi-arrow-up-right"></i></a>
    </aside>
</div>

<section class="panel-card widget-appointment-panel" id="widgetAppointments">
    <div class="panel-head"><div><h3>Lịch tư vấn từ widget</h3><p>Hiển thị tối đa 12 yêu cầu, ưu tiên lịch chờ xác nhận và thời gian mong muốn.</p></div><span class="panel-chip"><?= $pendingAppointments ?> lịch đang chờ</span></div>
    <div class="table-responsive"><table class="table clean-table align-middle"><thead><tr><th scope="col">Học sinh</th><th scope="col">Đại sứ</th><th scope="col">Thời gian mong muốn</th><th scope="col">Nội dung</th><th scope="col">Trạng thái</th><th scope="col">Cập nhật</th></tr></thead><tbody>
        <?php foreach ($appointments as $appointment): ?>
            <tr>
                <td><strong><?= e($appointment['student_name']) ?></strong><small class="d-block text-muted"><?= e($appointment['email']) ?></small><?php if ($appointment['phone']): ?><small class="d-block text-muted"><?= e($appointment['phone']) ?></small><?php endif; ?></td>
                <td><strong><?= e($appointment['ambassador_name']) ?></strong><small class="d-block text-muted"><?= e($appointment['ambassador_major']) ?></small></td>
                <td><time datetime="<?= e(date('c', strtotime($appointment['preferred_at']))) ?>"><strong><?= date('H:i', strtotime($appointment['preferred_at'])) ?></strong><small class="d-block text-muted"><?= date('d/m/Y', strtotime($appointment['preferred_at'])) ?></small></time></td>
                <td><details class="appointment-question"><summary>Xem nội dung</summary><p><?= e($appointment['question'] ?: 'Chưa ghi nội dung') ?></p></details></td>
                <td><span class="status-label status-<?= $appointment['status'] === 'completed' ? 'success' : ($appointment['status'] === 'cancelled' ? 'danger' : 'warning') ?>"><?= e(match ($appointment['status']) { 'confirmed' => 'Đã xác nhận', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy', default => 'Chờ xác nhận' }) ?></span></td>
                <td><form method="post" action="actions.php?action=update_appointment_status" class="appointment-action">
                    <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                    <label class="visually-hidden" for="appointment-status-<?= (int) $appointment['id'] ?>">Trạng thái lịch của <?= e($appointment['student_name']) ?></label>
                    <select class="form-select form-select-sm" id="appointment-status-<?= (int) $appointment['id'] ?>" name="status">
                        <option value="pending" <?= $appointment['status'] === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
                        <option value="confirmed" <?= $appointment['status'] === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
                        <option value="completed" <?= $appointment['status'] === 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
                        <option value="cancelled" <?= $appointment['status'] === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                    </select><button class="btn btn-sm btn-light border" type="submit" aria-label="Lưu trạng thái lịch của <?= e($appointment['student_name']) ?>">Lưu</button>
                </form></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$appointments): ?><tr><td colspan="6"><div class="widget-appointments-empty"><i class="bi bi-calendar2-check"></i><div><strong>Chưa có lịch tư vấn</strong><p>Các yêu cầu đặt lịch từ widget sẽ xuất hiện tại đây.</p></div></div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>

<div class="modal fade" id="widgetDemo" tabindex="-1" aria-labelledby="widgetDemoTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content widget-demo-modal"><div class="modal-header"><div><h2 class="modal-title" id="widgetDemoTitle">Góc nhìn của học sinh</h2><p>Demo đầy đủ bộ lọc, hồ sơ đại sứ, chat và đặt lịch tư vấn.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><div class="demo-browser-bar"><span></span><span></span><span></span><p><i class="bi bi-lock-fill"></i> tuyensinh.cmc-u.edu.vn</p></div><iframe src="<?= e($widgetUrl) ?>" title="Demo widget eAmbassador"></iframe></div></div></div></div>
