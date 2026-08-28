<?php
$widgetToken = bin2hex(random_bytes(24));
$statement = $db->prepare("INSERT INTO widget_access_tokens (token, expires_at) VALUES (?, datetime('now', '+12 hours'))");
$statement->execute([$widgetToken]);
$db->exec("DELETE FROM widget_access_tokens WHERE expires_at <= datetime('now')");
$ambassadors = rows("SELECT id, name, major, hometown, interests, bio, study_year, is_online FROM users WHERE role = 'ambassador' AND status = 'active' ORDER BY is_online DESC, name");
$widgetData = array_map(static fn(array $item): array => [
    'id' => (int) $item['id'],
    'name' => $item['name'],
    'major' => $item['major'],
    'hometown' => $item['hometown'],
    'interests' => array_values(array_filter(array_map('trim', explode(',', (string) $item['interests'])))),
    'bio' => $item['bio'],
    'study_year' => (int) $item['study_year'],
    'online' => (bool) $item['is_online'],
    'initials' => initials($item['name']),
], $ambassadors);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Tư vấn cùng đại sứ CMC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700&amp;display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/widget.css?v=1" rel="stylesheet">
</head>
<body class="widget-body">
<main class="widget-shell" id="widgetShell">
    <header class="widget-header">
        <button class="widget-back is-hidden" id="widgetBack" type="button" aria-label="Quay lại"><i class="bi bi-arrow-left"></i></button>
        <a class="widget-brand" href="#" aria-label="eAmbassador"><img src="assets/img/cmc-university-horizontal.png" alt="CMC University"><span><b>e</b>Ambassador</span></a>
        <button class="widget-close" id="widgetClose" type="button" aria-label="Đóng cửa sổ"><i class="bi bi-x-lg"></i></button>
    </header>

    <section class="widget-view widget-directory" id="directoryView">
        <div class="widget-intro"><div><h1>Hỏi người đang học.</h1><p>Chọn một đại sứ phù hợp để nghe trải nghiệm thật tại CMC.</p></div><span class="verified-pill"><i class="bi bi-patch-check-fill"></i> Đã xác minh</span></div>
        <div class="widget-filters" aria-label="Lọc đại sứ">
            <label class="widget-search"><i class="bi bi-search"></i><input id="widgetSearch" type="search" placeholder="Tên hoặc điều bạn quan tâm..."></label>
            <div class="widget-filter-row">
                <label><span>Ngành</span><select id="majorFilter"><option value="">Tất cả ngành</option></select></label>
                <label><span>Quê quán</span><select id="hometownFilter"><option value="">Tất cả tỉnh thành</option></select></label>
                <label><span>Khóa / năm</span><select id="yearFilter"><option value="">Tất cả</option></select></label>
            </div>
            <div class="availability-filter" role="group" aria-label="Trạng thái hoạt động"><button class="is-active" type="button" data-availability="all">Tất cả</button><button type="button" data-availability="online"><i></i> Đang online</button><button type="button" data-availability="offline">Có thể đặt lịch</button></div>
        </div>
        <div class="widget-results-head"><strong id="resultCount">0 đại sứ phù hợp</strong><span>Ưu tiên người đang online</span></div>
        <div class="ambassador-list" id="ambassadorList"></div>
        <div class="widget-empty is-hidden" id="widgetEmpty"><i class="bi bi-search"></i><h2>Chưa thấy người phù hợp</h2><p>Thử bỏ bớt một bộ lọc để xem thêm đại sứ.</p><button type="button" id="clearFilters">Xóa bộ lọc</button></div>
    </section>

    <section class="widget-view widget-profile is-hidden" id="profileView" aria-live="polite">
        <div class="profile-cover"><span class="profile-avatar" id="profileAvatar"></span><span class="profile-status" id="profileStatus"></span></div>
        <div class="profile-content"><span class="profile-verified"><i class="bi bi-patch-check-fill"></i> Đại sứ sinh viên đã xác minh</span><h1 id="profileName"></h1><p class="profile-major" id="profileMajor"></p><p class="profile-location" id="profileLocation"></p><p class="profile-bio" id="profileBio"></p><div class="profile-tags" id="profileTags"></div><div class="profile-action" id="profileAction"></div><p class="privacy-note"><i class="bi bi-shield-check"></i> Thông tin chỉ dùng để duy trì tư vấn và bảo đảm an toàn.</p></div>
    </section>

    <section class="widget-view widget-form-view is-hidden" id="chatStartView">
        <div class="form-heading"><span class="form-icon"><i class="bi bi-chat-heart"></i></span><h1>Bắt đầu cuộc trò chuyện</h1><p>Giới thiệu ngắn để đại sứ hiểu câu hỏi của bạn.</p></div>
        <form class="widget-form" id="widgetChatForm"><input type="hidden" name="ambassador_id" id="chatAmbassadorId"><label><span>Tên của bạn</span><input name="name" autocomplete="name" required placeholder="Ví dụ: Minh Anh"></label><label><span>Email</span><input name="email" type="email" autocomplete="email" required placeholder="minhanh@email.com"></label><label><span>Ngành đang quan tâm</span><input name="major" id="chatMajor" placeholder="Công nghệ thông tin"></label><label><span>Bạn muốn hỏi điều gì?</span><textarea name="message" rows="4" required placeholder="Ngành học, môi trường, hoạt động sinh viên..."></textarea></label><p class="form-error is-hidden" id="chatError"></p><button class="primary-action" type="submit">Kết nối với đại sứ <i class="bi bi-arrow-right"></i></button></form>
    </section>

    <section class="widget-view widget-chat is-hidden" id="chatView">
        <div class="chat-person"><span class="mini-avatar" id="chatHeaderAvatar"></span><div><strong id="chatHeaderName"></strong><small><i></i> Đang online</small></div></div>
        <div class="widget-messages" id="widgetMessages"></div>
        <form class="widget-composer" id="widgetMessageForm"><textarea id="widgetMessageInput" rows="1" placeholder="Nhập tin nhắn..."></textarea><button type="submit" aria-label="Gửi tin nhắn"><i class="bi bi-send-fill"></i></button></form>
        <small class="chat-safe"><i class="bi bi-shield-check"></i> Cuộc trò chuyện được lưu để đảm bảo an toàn.</small>
    </section>

    <section class="widget-view widget-form-view is-hidden" id="scheduleView">
        <div class="form-heading"><span class="form-icon"><i class="bi bi-calendar2-check"></i></span><h1>Đặt lịch tư vấn</h1><p><span id="scheduleAmbassadorName"></span> đang offline. Chọn thời gian thuận tiện để được liên hệ lại.</p></div>
        <form class="widget-form" id="scheduleForm"><input type="hidden" name="ambassador_id" id="scheduleAmbassadorId"><label><span>Tên của bạn</span><input name="name" autocomplete="name" required></label><label><span>Email</span><input name="email" type="email" autocomplete="email" required></label><label><span>Số điện thoại <small>(không bắt buộc)</small></span><input name="phone" type="tel" autocomplete="tel"></label><label><span>Thời gian mong muốn</span><input name="preferred_at" id="preferredAt" type="datetime-local" required></label><label><span>Nội dung cần tư vấn</span><textarea name="question" rows="3" placeholder="Bạn đang quan tâm điều gì?"></textarea></label><p class="form-error is-hidden" id="scheduleError"></p><button class="primary-action" type="submit">Gửi yêu cầu đặt lịch <i class="bi bi-calendar2-check"></i></button></form>
    </section>

    <section class="widget-view widget-success is-hidden" id="successView"><span><i class="bi bi-check-lg"></i></span><h1>Đã ghi nhận lịch của bạn</h1><p>Đội ngũ eAmbassador sẽ xác nhận thời gian tư vấn qua email.</p><button type="button" id="backToDirectory">Tiếp tục khám phá đại sứ</button></section>
    <footer class="widget-footer"><i class="bi bi-shield-lock"></i> Tư vấn an toàn bởi <strong>eAmbassador</strong></footer>
    <div class="widget-toast is-hidden" id="widgetToast" role="status"></div>
 </main>
<script>
window.eAmbassadorWidget = <?= json_encode(['token' => $widgetToken, 'ambassadors' => $widgetData], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script src="assets/js/widget.js?v=1"></script>
</body>
</html>
