<?php
require_auth(['student', 'ambassador']);
$pageTitle = 'AI Copilot sáng tạo';
$campaigns = rows("SELECT id, title, brief, platform FROM campaigns WHERE status = 'active' ORDER BY deadline");
$history = rows('SELECT ar.*, c.title AS campaign_title FROM ai_requests ar LEFT JOIN campaigns c ON c.id = ar.campaign_id WHERE ar.user_id = ? ORDER BY ar.id DESC LIMIT 5', [user()['id']]);
$selectedCampaign = (int) ($_GET['campaign'] ?? 0);
?>
<div class="copilot-layout">
    <section class="copilot-workspace panel-card">
        <div class="copilot-intro">
            <div><span class="copilot-mark"><i class="bi bi-stars"></i></span><p class="topbar-context">Content augmentation</p><h2>Giữ trải nghiệm của bạn. Để Copilot lo phần cấu trúc.</h2><p>Nhận ba hướng kịch bản, hashtag và kiểm tra brand voice trước khi quay.</p></div>
            <span class="copilot-principle"><i class="bi bi-person-check"></i> AI gợi ý, sinh viên quyết định</span>
        </div>

        <form id="copilotForm" class="copilot-form">
            <div class="row g-3">
                <div class="col-lg-7"><label class="form-label" for="copilotCampaign">Chiến dịch</label><select class="form-select" id="copilotCampaign" name="campaign_id" required><option value="">Chọn brief đang thực hiện</option><?php foreach ($campaigns as $campaign): ?><option value="<?= (int) $campaign['id'] ?>" <?= $selectedCampaign === (int) $campaign['id'] ? 'selected' : '' ?>><?= e($campaign['title']) ?></option><?php endforeach; ?></select></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label" for="copilotPlatform">Nền tảng</label><select class="form-select" id="copilotPlatform" name="platform"><option>TikTok</option><option>Reels</option><option>YouTube Shorts</option></select></div>
                <div class="col-sm-6 col-lg-2"><label class="form-label" for="copilotTone">Giọng điệu</label><select class="form-select" id="copilotTone" name="tone"><option>Chân thật</option><option>Năng động</option><option>Gần gũi</option><option>Thông tin</option></select></div>
            </div>
            <div><label class="form-label" for="copilotObjective">Bạn muốn kể điều gì?</label><textarea class="form-control" id="copilotObjective" name="objective" rows="4" maxlength="600" placeholder="Ví dụ: Một ngày học ngành CNTT có gì khác với tưởng tượng của học sinh lớp 12?" required></textarea><small class="form-hint">Nêu trải nghiệm thật, đối tượng xem và điều bạn muốn họ nhớ.</small></div>
            <div class="copilot-actions"><button class="btn btn-brand" type="submit"><i class="bi bi-stars"></i> Tạo hướng kịch bản</button><span><i class="bi bi-shield-check"></i> Không tự đăng nội dung</span></div>
        </form>

        <div class="copilot-result empty" id="copilotResult"><i class="bi bi-lightbulb"></i><h3>Kết quả sẽ xuất hiện ở đây</h3><p>Copilot chỉ tạo khung. Hãy viết lại bằng giọng của chính bạn.</p></div>
    </section>

    <aside class="copilot-side">
        <section class="panel-card"><div class="panel-head"><div><p class="topbar-context">Brand safety</p><h3>Nguyên tắc trước khi đăng</h3></div></div><div class="copilot-checklist"><p><i class="bi bi-check2"></i> Chỉ dùng trải nghiệm bạn đã chứng kiến</p><p><i class="bi bi-check2"></i> Dẫn nguồn khi nói về học phí, quy chế</p><p><i class="bi bi-check2"></i> Không cam kết kết quả tuyển sinh</p><p><i class="bi bi-check2"></i> Gắn #CMCAmbassador trong caption</p></div></section>
        <section class="panel-card"><div class="panel-head"><div><p class="topbar-context">Gần đây</p><h3>Lịch sử gợi ý</h3></div></div><div class="copilot-history"><?php foreach ($history as $item): ?><div><span><?= e($item['campaign_title'] ?: 'Nội dung tự do') ?></span><strong><?= e($item['platform']) ?> | <?= e($item['tone']) ?></strong><small>Brand score <?= (int) $item['brand_score'] ?>/100</small></div><?php endforeach; ?><?php if (!$history): ?><p class="text-muted mb-0">Chưa có lượt tạo nào.</p><?php endif; ?></div></section>
    </aside>
</div>
