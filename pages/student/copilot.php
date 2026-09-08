<?php
require_auth(['student', 'ambassador']);
$pageTitle = 'Trợ lý nội dung AI';
$campaigns = rows("SELECT id, title, brief, platform FROM campaigns WHERE status = 'active' AND deadline >= date('now', '+7 hours') ORDER BY deadline");
$history = rows('SELECT ar.*, c.title AS campaign_title FROM ai_requests ar LEFT JOIN campaigns c ON c.id = ar.campaign_id WHERE ar.user_id = ? ORDER BY ar.id DESC LIMIT 5', [user()['id']]);
$selectedCampaign = (int) ($_GET['campaign'] ?? 0);
?>
<div class="copilot-layout">
    <section class="copilot-workspace panel-card">
        <div class="copilot-intro">
            <div><span class="copilot-mark"><i class="bi bi-stars"></i></span><p class="topbar-context">HỖ TRỢ SOẠN NỘI DUNG</p><h2>Tạo đề xuất nội dung cho chiến dịch</h2><p>Nhận gợi ý về cấu trúc, ý tưởng triển khai và hashtag từ thông tin bạn cung cấp.</p></div>
            <span class="copilot-principle"><i class="bi bi-person-check"></i> Kiểm tra và chỉnh sửa trước khi sử dụng</span>
        </div>

        <form id="copilotForm" class="copilot-form">
            <div class="row g-3">
                <div class="col-lg-7"><label class="form-label" for="copilotCampaign">Chiến dịch</label><select class="form-select" id="copilotCampaign" name="campaign_id" required><option value="">Chọn chiến dịch</option><?php foreach ($campaigns as $campaign): ?><option value="<?= (int) $campaign['id'] ?>" <?= $selectedCampaign === (int) $campaign['id'] ? 'selected' : '' ?>><?= e($campaign['title']) ?></option><?php endforeach; ?></select></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label" for="copilotPlatform">Nền tảng</label><select class="form-select" id="copilotPlatform" name="platform"><option>TikTok</option><option>Reels</option><option>YouTube Shorts</option></select></div>
                <div class="col-sm-6 col-lg-2"><label class="form-label" for="copilotTone">Giọng điệu</label><select class="form-select" id="copilotTone" name="tone"><option>Chân thật</option><option>Năng động</option><option>Gần gũi</option><option>Thông tin</option></select></div>
            </div>
            <div><label class="form-label" for="copilotObjective">Mô tả nội dung cần xây dựng</label><textarea class="form-control" id="copilotObjective" name="objective" rows="4" maxlength="600" placeholder="Ví dụ: Giới thiệu một ngày học ngành CNTT cho học sinh lớp 12..." required></textarea><small class="form-hint">Nêu trải nghiệm thực tế, đối tượng xem và thông tin chính cần truyền đạt.</small></div>
            <div class="copilot-actions"><button class="btn btn-brand" type="submit"><i class="bi bi-stars"></i> Tạo đề xuất</button><span><i class="bi bi-shield-check"></i> Nội dung không được tự động đăng</span></div>
        </form>

        <div class="copilot-result empty" id="copilotResult"><i class="bi bi-lightbulb"></i><h3>Chưa có đề xuất</h3><p>Chọn chiến dịch và mô tả nội dung để bắt đầu.</p></div>
    </section>

    <aside class="copilot-side">
        <section class="panel-card"><div class="panel-head"><div><p class="topbar-context">KIỂM TRA TRƯỚC KHI ĐĂNG</p><h3>Nguyên tắc nội dung</h3></div></div><div class="copilot-checklist"><p><i class="bi bi-check2"></i> Chỉ sử dụng trải nghiệm bạn đã trực tiếp tham gia</p><p><i class="bi bi-check2"></i> Dẫn nguồn khi đề cập học phí hoặc quy chế</p><p><i class="bi bi-check2"></i> Không cam kết kết quả tuyển sinh</p><p><i class="bi bi-check2"></i> Gắn #CMCAmbassador trong chú thích</p></div></section>
        <section class="panel-card"><div class="panel-head"><div><p class="topbar-context">Gần đây</p><h3>Lịch sử gợi ý</h3></div></div><div class="copilot-history"><?php foreach ($history as $item): ?><div><span><?= e($item['campaign_title'] ?: 'Nội dung tự do') ?></span><strong><?= e($item['platform']) ?> | <?= e($item['tone']) ?></strong><small><?= array_key_exists('sources', json_decode($item['response_json'], true) ?: []) ? 'Bản nháp AI · cần xem lại' : 'Gợi ý trước đây' ?></small></div><?php endforeach; ?><?php if (!$history): ?><p class="text-muted mb-0">Chưa có lượt tạo nào.</p><?php endif; ?></div></section>
    </aside>
</div>
