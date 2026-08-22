<?php
$pageTitle = 'Gặp đại sứ sinh viên';
$major = trim((string) ($_GET['major'] ?? ''));
$hometown = trim((string) ($_GET['hometown'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$sql = "SELECT * FROM users WHERE role = 'ambassador' AND status = 'active'";
$params = [];
if ($major !== '') { $sql .= ' AND major = ?'; $params[] = $major; }
if ($hometown !== '') { $sql .= ' AND hometown = ?'; $params[] = $hometown; }
if ($search !== '') { $sql .= ' AND (name LIKE ? OR interests LIKE ? OR bio LIKE ?)'; array_push($params, "%$search%", "%$search%", "%$search%"); }
$sql .= ' ORDER BY is_online DESC, name';
$ambassadors = rows($sql, $params);
$majors = rows("SELECT DISTINCT major FROM users WHERE role = 'ambassador' AND major IS NOT NULL ORDER BY major");
$hometowns = rows("SELECT DISTINCT hometown FROM users WHERE role = 'ambassador' AND hometown IS NOT NULL ORDER BY hometown");
$selectedId = (int) ($_GET['ambassador'] ?? 0);
?>
<section class="directory-hero"><div class="container"><span class="hero-kicker"><i class="bi bi-patch-check-fill"></i> Cộng đồng đã xác minh</span><h1>Tìm một người hiểu điều bạn đang hỏi.</h1><p>Lọc theo ngành, quê quán hay sở thích. Mỗi góc nhìn thật giúp lựa chọn của bạn rõ ràng hơn.</p></div></section>
<section class="directory-section"><div class="container">
    <form class="filter-bar" method="get"><input type="hidden" name="page" value="ambassadors"><div class="filter-search"><i class="bi bi-search"></i><input name="search" value="<?= e($search) ?>" placeholder="Tên, sở thích hoặc điều bạn quan tâm..."></div><select name="major" class="form-select"><option value="">Tất cả ngành học</option><?php foreach ($majors as $item): ?><option <?= $major === $item['major'] ? 'selected' : '' ?>><?= e($item['major']) ?></option><?php endforeach; ?></select><select name="hometown" class="form-select"><option value="">Tất cả quê quán</option><?php foreach ($hometowns as $item): ?><option <?= $hometown === $item['hometown'] ? 'selected' : '' ?>><?= e($item['hometown']) ?></option><?php endforeach; ?></select><button class="btn btn-brand">Tìm kiếm</button></form>
    <div class="directory-meta"><p>Tìm thấy <strong><?= count($ambassadors) ?> đại sứ</strong> phù hợp</p><span><i></i> Ưu tiên người đang online</span></div>
    <div class="row g-4">
        <?php foreach ($ambassadors as $index => $ambassador): ?>
            <div class="col-md-6 col-lg-4"><article class="ambassador-card h-100">
                <div class="ambassador-cover cover-<?= ($index % 3) + 1 ?>"><span class="profile-monogram"><?= e(initials($ambassador['name'])) ?></span><?php if ($ambassador['is_online']): ?><span class="online-badge"><i></i> Đang online</span><?php else: ?><span class="offline-badge">Phản hồi trong ngày</span><?php endif; ?></div>
                <div class="ambassador-body"><span class="verified"><i class="bi bi-patch-check-fill"></i> Sinh viên đã xác minh</span><h3><?= e($ambassador['name']) ?></h3><p class="major"><i class="bi bi-book"></i> <?= e($ambassador['major']) ?> | Năm <?= (int) $ambassador['study_year'] ?></p><p class="location"><i class="bi bi-geo-alt"></i> <?= e($ambassador['hometown']) ?></p><p><?= e($ambassador['bio']) ?></p><div class="tag-row"><?php foreach (array_slice(explode(',', $ambassador['interests']), 0, 3) as $interest): ?><span><?= e(trim($interest)) ?></span><?php endforeach; ?></div><button class="btn btn-card w-100 mt-3 chat-trigger" data-ambassador-id="<?= (int) $ambassador['id'] ?>" data-ambassador-name="<?= e($ambassador['name']) ?>" data-ambassador-major="<?= e($ambassador['major']) ?>" data-ambassador-initials="<?= e(initials($ambassador['name'])) ?>">Mở cuộc trò chuyện <i class="bi bi-arrow-up-right"></i></button></div>
            </article></div>
        <?php endforeach; ?>
        <?php if (!$ambassadors): ?><div class="col-12"><div class="empty-state"><i class="bi bi-search"></i><h2>Chưa tìm thấy đại sứ phù hợp</h2><p>Thử bỏ bớt bộ lọc hoặc tìm bằng từ khóa khác nhé.</p><a class="btn btn-brand" href="index.php?page=ambassadors">Xóa bộ lọc</a></div></div><?php endif; ?>
    </div>
</div></section>

<div class="chat-panel" id="chatPanel" aria-hidden="true">
    <div class="chat-head"><div class="d-flex align-items-center gap-3"><span class="avatar" id="chatAvatar">MA</span><div><strong id="chatName">Đại sứ CMC</strong><small><i></i> <span id="chatMajor">Đang trực tuyến</span></small></div></div><button class="btn-close btn-close-white" id="chatClose" aria-label="Đóng"></button></div>
    <div class="chat-welcome" id="chatWelcome"><div class="chat-shield"><i class="bi bi-shield-check"></i></div><h3>Bắt đầu một cuộc trò chuyện thật</h3><p>Thông tin của bạn chỉ được dùng để duy trì cuộc trò chuyện và hỗ trợ tuyển sinh.</p>
        <form id="startChatForm"><input type="hidden" name="ambassador_id" id="chatAmbassadorId"><div class="mb-3"><label class="form-label">Tên của bạn</label><input class="form-control" name="name" placeholder="Ví dụ: Minh" <?= user() && user()['role'] === 'prospect' ? '' : 'required' ?>></div><div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" placeholder="minh@email.com" <?= user() && user()['role'] === 'prospect' ? '' : 'required' ?>></div><div class="mb-3"><label class="form-label">Bạn muốn hỏi điều gì?</label><textarea class="form-control" name="message" rows="3" placeholder="Ngành này học những gì, môi trường ra sao..."></textarea></div><button class="btn btn-brand w-100" type="submit">Bắt đầu chat <i class="bi bi-send-fill"></i></button></form>
    </div>
    <div class="chat-room d-none" id="chatRoom"><div class="message-list" id="messageList"></div><form class="chat-composer" id="messageForm"><input type="hidden" id="conversationId"><textarea id="messageInput" rows="1" placeholder="Nhập tin nhắn..."></textarea><button type="submit" aria-label="Gửi"><i class="bi bi-send-fill"></i></button></form><small class="chat-safety"><i class="bi bi-shield-check"></i> Cuộc trò chuyện được lưu để đảm bảo an toàn.</small></div>
</div><div class="chat-overlay" id="chatOverlay"></div>
<?php if ($selectedId): ?><script>window.autoOpenAmbassador = <?= $selectedId ?>;</script><?php endif; ?>
