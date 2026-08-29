<?php
$widgetToken = bin2hex(random_bytes(24));
$statement = $db->prepare("INSERT INTO widget_access_tokens (token, expires_at) VALUES (?, datetime('now', '+12 hours'))");
$statement->execute([$widgetToken]);
$db->exec("DELETE FROM widget_access_tokens WHERE expires_at <= datetime('now')");
$ambassadors = rows("SELECT id, name, major, hometown, interests, bio, study_year, is_online FROM users WHERE role = 'ambassador' AND status = 'active' ORDER BY is_online DESC, name");
$answeredQuestions = (int) scalar("SELECT COUNT(*) FROM messages m JOIN users u ON u.id = m.sender_id WHERE u.role = 'ambassador'");
$publishedContent = rows(<<<'SQL'
    SELECT s.id, s.content_type, s.content_url, s.caption, s.platform, s.blog_title, s.blog_excerpt, s.blog_body,
           s.views, s.likes, s.submitted_at, u.name AS author_name, u.major AS author_major, c.title AS campaign_title
    FROM submissions s
    JOIN users u ON u.id = s.user_id
    JOIN campaigns c ON c.id = s.campaign_id
    WHERE s.status = 'approved' AND u.role = 'ambassador'
    ORDER BY s.id DESC
    LIMIT 18
SQL);
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
$contentData = array_map(static fn(array $item): array => [
    'id' => (int) $item['id'],
    'type' => $item['content_type'] === 'blog' ? 'blog' : 'social',
    'format' => $item['content_type'] === 'blog' ? 'Bài viết' : ($item['platform'] ?: 'Nội dung UGC'),
    'title' => $item['content_type'] === 'blog' ? ($item['blog_title'] ?: $item['campaign_title']) : ($item['caption'] ?: $item['campaign_title']),
    'excerpt' => $item['content_type'] === 'blog' ? ($item['blog_excerpt'] ?: $item['caption']) : $item['campaign_title'],
    'body' => $item['content_type'] === 'blog' ? (string) $item['blog_body'] : '',
    'url' => $item['content_url'],
    'author' => $item['author_name'],
    'authorMajor' => $item['author_major'],
    'authorInitials' => initials($item['author_name']),
    'views' => (int) $item['views'],
    'likes' => (int) $item['likes'],
    'publishedAt' => date('d/m/Y', strtotime($item['submitted_at'])),
], $publishedContent);
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
    <link href="assets/css/widget.css?v=11" rel="stylesheet">
</head>
<body class="widget-body">
<main class="widget-shell" id="widgetShell">
    <header class="widget-header">
        <button class="widget-back is-hidden" id="widgetBack" type="button" aria-label="Quay lại"><i class="bi bi-arrow-left"></i></button>
        <div class="widget-brand" aria-label="CMC University eAmbassador"><img src="assets/img/cmc-university-horizontal.png" alt="CMC University"><span><b>e</b>Ambassador</span></div>
        <button class="widget-close" id="widgetClose" type="button" aria-label="Đóng cửa sổ"><i class="bi bi-x-lg"></i></button>
    </header>

    <nav class="widget-navigation" aria-label="Cách kết nối với đại sứ">
        <button class="is-active" type="button" data-availability="all"><i class="bi bi-people"></i><span>Đại sứ</span></button>
        <button type="button" data-widget-tab="chat"><i class="bi bi-chat-dots"></i><span>Chat ngay</span></button>
        <button type="button" data-widget-tab="content"><i class="bi bi-journal-richtext"></i><span>Content</span></button>
        <button type="button" data-availability="offline"><i class="bi bi-calendar2-check"></i><span>Đặt lịch</span></button>
    </nav>

    <section class="widget-view widget-directory" id="directoryView">
        <div class="widget-intro">
            <div><h1>Chọn đại sứ để trò chuyện</h1><p><strong><?= count($ambassadors) ?> đại sứ đã xác minh</strong> · <?= number_format($answeredQuestions, 0, ',', '.') ?> câu trả lời đã được ghi nhận.</p></div>
            <span class="widget-powered"><small>Powered by</small><span class="cmc-mini"><img src="assets/img/cmc-university.svg" alt="CMC University"></span><i aria-hidden="true"></i><strong><b>e</b>Ambassador</strong></span>
        </div>
        <div class="widget-filters" aria-label="Lọc đại sứ">
            <label class="widget-search"><i class="bi bi-search"></i><input id="widgetSearch" type="search" placeholder="Tên hoặc điều bạn quan tâm..."></label>
            <div class="widget-filter-row">
                <label><span>Ngành</span><select id="majorFilter"><option value="">Tất cả ngành</option></select></label>
                <label><span>Quê quán</span><select id="hometownFilter"><option value="">Tất cả tỉnh thành</option></select></label>
                <label><span>Khóa / năm</span><select id="yearFilter"><option value="">Tất cả</option></select></label>
            </div>
        </div>
        <div class="widget-results-head"><strong id="resultCount">0 đại sứ phù hợp</strong><span>Ưu tiên người đang online</span></div>
        <div class="ambassador-list" id="ambassadorList"></div>
        <div class="widget-empty is-hidden" id="widgetEmpty"><i class="bi bi-search"></i><h2>Chưa thấy người phù hợp</h2><p>Thử bỏ bớt một bộ lọc để xem thêm đại sứ.</p><button type="button" id="clearFilters">Xóa bộ lọc</button></div>
    </section>

    <section class="widget-view widget-content is-hidden" id="contentView">
        <div class="content-heading"><div><h1>Câu chuyện từ đại sứ CMC</h1><p>Trải nghiệm học tập và đời sống sinh viên được chia sẻ bởi người đang học.</p></div><span><strong><?= count($contentData) ?></strong> nội dung đã duyệt</span></div>
        <label class="widget-search content-search"><i class="bi bi-search"></i><input id="contentSearch" type="search" placeholder="Tìm chủ đề, ngành học hoặc đại sứ..."></label>
        <div class="content-filter" role="group" aria-label="Loại nội dung"><button class="is-active" type="button" data-content-type="all">Tất cả</button><button type="button" data-content-type="blog">Bài viết</button><button type="button" data-content-type="social">Video &amp; UGC</button></div>
        <div class="content-grid" id="contentGrid"></div>
        <div class="widget-empty is-hidden" id="contentEmpty"><i class="bi bi-journal-text"></i><h2>Chưa có nội dung phù hợp</h2><p>Thử một từ khóa khác hoặc xem toàn bộ nội dung.</p><button type="button" id="clearContentFilters">Xem tất cả</button></div>
    </section>

    <article class="widget-view content-detail is-hidden" id="contentDetailView">
        <div class="content-detail-cover"><span id="detailFormat"></span><i class="bi bi-journal-richtext" id="detailIcon"></i></div>
        <div class="content-detail-copy"><h1 id="detailTitle"></h1><p class="content-detail-lead" id="detailExcerpt"></p><div class="content-author"><span id="detailAuthorAvatar"></span><div><strong id="detailAuthor"></strong><small id="detailAuthorMeta"></small></div></div><div class="content-article-body" id="detailBody"></div><a class="content-source is-hidden" id="detailSource" href="#" target="_blank" rel="noopener">Xem nội dung gốc <i class="bi bi-box-arrow-up-right"></i></a></div>
    </article>

    <section class="widget-view widget-profile is-hidden" id="profileView" aria-live="polite">
        <div class="profile-cover"><div class="profile-hero"><span class="profile-avatar" id="profileAvatar"></span><div><span class="profile-verified"><i class="bi bi-patch-check-fill"></i> Đại sứ sinh viên đã xác minh</span><h1 id="profileName"></h1><p id="profileMajor"></p></div></div></div>
        <div class="profile-content">
            <div class="profile-presence" id="profilePresence"><span><i class="bi" id="profilePresenceIcon"></i></span><div><strong id="profileStatusLabel"></strong><p id="profileStatusDetail"></p></div><small id="profileResponseBadge"></small></div>
            <div class="profile-action" id="profileAction"></div>
            <div class="profile-facts" aria-label="Thông tin đại sứ"><div><span><i class="bi bi-mortarboard"></i></span><small>Ngành học</small><strong id="profileFieldMajor"></strong></div><div><span><i class="bi bi-calendar3"></i></span><small>Năm học</small><strong id="profileStudyYear"></strong></div><div><span><i class="bi bi-geo-alt"></i></span><small>Quê quán</small><strong id="profileLocation"></strong></div></div>
            <section class="profile-section profile-about"><h2>Về mình</h2><p class="profile-about-lead" id="profileAboutLead"></p><p class="profile-bio" id="profileBio"></p></section>
            <div class="profile-detail-grid">
                <section class="profile-section"><h2>Sở thích &amp; mối quan tâm</h2><p class="profile-section-note">Những chủ đề đại sứ yêu thích và thường xuyên tìm hiểu.</p><div class="profile-tags" id="profileTags"></div></section>
                <section class="profile-section"><h2>Có thể chia sẻ cùng bạn</h2><p class="profile-section-note">Bắt đầu bằng một trong những chủ đề dưới đây.</p><ul class="profile-share-list" id="profileShareList"></ul></section>
            </div>
            <p class="privacy-note"><i class="bi bi-shield-check"></i> Hồ sơ đã được xác minh. Cuộc trò chuyện được lưu để bảo đảm an toàn cho cả hai bên.</p>
        </div>
    </section>

    <section class="widget-view widget-chat is-hidden" id="chatView">
        <div class="chat-person"><span class="mini-avatar" id="chatHeaderAvatar"></span><div><strong id="chatHeaderName"></strong><small id="chatHeaderStatus"><i></i> Đang online</small></div></div>
        <div class="widget-messages" id="widgetMessages"></div>
        <form class="widget-composer" id="widgetMessageForm"><textarea id="widgetMessageInput" rows="1" placeholder="Nhập tin nhắn..."></textarea><button type="submit" aria-label="Gửi tin nhắn"><i class="bi bi-send-fill"></i></button></form>
        <small class="chat-safe"><i class="bi bi-shield-check"></i> Cuộc trò chuyện được lưu để đảm bảo an toàn.</small>
    </section>

    <section class="widget-view widget-form-view is-hidden" id="scheduleView">
        <div class="form-heading"><span class="form-icon"><i class="bi bi-calendar2-check"></i></span><h1>Đặt lịch tư vấn</h1><p><span id="scheduleAmbassadorName"></span> đang offline. Chọn thời gian thuận tiện để được liên hệ lại.</p></div>
        <form class="widget-form" id="scheduleForm"><input type="hidden" name="ambassador_id" id="scheduleAmbassadorId"><label><span>Tên của bạn</span><input name="name" autocomplete="name" required></label><label><span>Email</span><input name="email" type="email" autocomplete="email" required></label><label><span>Số điện thoại <small>(không bắt buộc)</small></span><input name="phone" type="tel" autocomplete="tel"></label><label><span>Thời gian mong muốn</span><input name="preferred_at" id="preferredAt" type="datetime-local" required></label><label><span>Nội dung cần tư vấn</span><textarea name="question" rows="3" placeholder="Bạn đang quan tâm điều gì?"></textarea></label><p class="form-error is-hidden" id="scheduleError"></p><button class="primary-action" type="submit">Gửi yêu cầu đặt lịch <i class="bi bi-calendar2-check"></i></button></form>
    </section>

    <section class="widget-view widget-success is-hidden" id="successView"><span><i class="bi bi-check-lg"></i></span><h1>Đã ghi nhận lịch của bạn</h1><p>Đội ngũ eAmbassador sẽ xác nhận thời gian tư vấn qua email.</p><button type="button" id="backToDirectory">Tiếp tục khám phá đại sứ</button></section>
    <dialog class="offline-message-dialog" id="offlineMessageDialog" aria-label="Trạng thái tin nhắn">
        <div class="offline-dialog-card">
            <button class="offline-dialog-close" id="offlineDialogClose" type="button" aria-label="Đóng thông báo"><i class="bi bi-x-lg"></i></button>
            <div id="offlineEmailStep">
                <span class="offline-dialog-icon"><i class="bi bi-envelope"></i></span>
                <h2 id="offlineMessageTitle">Xác nhận email để gửi</h2>
                <p id="offlineEmailDescription"></p>
                <form class="offline-email-form" id="offlineEmailForm"><label><span id="offlineReplyEmailLabel">Email của bạn</span><input id="offlineReplyEmail" name="email" type="email" autocomplete="email" required placeholder="ban@email.com"></label><p class="offline-email-error is-hidden" id="offlineEmailError"></p><div class="offline-dialog-actions" id="offlineEmailActions"><button class="secondary-action" id="scheduleBeforeMessage" type="button">Đặt lịch</button><button class="primary-action" type="submit"><i class="bi bi-send-fill"></i> Gửi tin nhắn</button></div></form>
            </div>
            <div class="is-hidden" id="offlineSentStep">
                <span class="offline-dialog-icon"><i class="bi bi-envelope-check"></i></span>
                <h2>Tin nhắn đã được gửi</h2>
                <p><strong id="offlineRecipientName"></strong> hiện đang offline. Khi có phản hồi, eAmbassador sẽ thông báo qua <strong id="offlineNotificationEmail"></strong>.</p>
                <div class="offline-dialog-actions"><button class="secondary-action" id="continueOfflineChat" type="button">Tiếp tục chat</button><button class="primary-action" id="scheduleFromMessage" type="button"><i class="bi bi-calendar2-check"></i> Đặt lịch tư vấn</button></div>
            </div>
        </div>
    </dialog>
    <footer class="widget-footer"><span class="widget-trust"><i class="bi bi-shield-check"></i> Kết nối an toàn · Thông tin được bảo vệ</span></footer>
    <div class="widget-toast is-hidden" id="widgetToast" role="status"></div>
 </main>
<script>
window.eAmbassadorWidget = <?= json_encode(['token' => $widgetToken, 'ambassadors' => $widgetData, 'content' => $contentData], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script src="assets/js/widget.js?v=11"></script>
</body>
</html>
