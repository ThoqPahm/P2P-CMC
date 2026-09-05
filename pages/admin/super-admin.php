<?php
require_super_admin();
$eligibleKnowledgeCount = count(AmbassadorProgram::knowledge($db, true));
$pageTitle = 'Super Admin Console';
$settings = ui_settings();
$providers = AiProviderManager::allConfigs();
$geminiKeys = AiProviderManager::keySlots('gemini');
$knowledgeEntries = rows('SELECT * FROM ai_knowledge_entries ORDER BY is_active DESC, updated_at DESC, id DESC');
$editKnowledgeId = (int) ($_GET['edit_knowledge'] ?? 0);
$editingKnowledge = $editKnowledgeId ? (rows('SELECT * FROM ai_knowledge_entries WHERE id = ?', [$editKnowledgeId])[0] ?? null) : null;
$aiLogs = rows('SELECT * FROM widget_ai_logs ORDER BY id DESC LIMIT 12');
$activeKnowledgeCount = (int) scalar('SELECT COUNT(*) FROM ai_knowledge_entries WHERE is_active = 1');
$configuredProviderCount = count(array_filter($providers, static fn(array $provider): bool => (bool) $provider['has_key']));
$loginThemes = login_theme_registry();
$activeLoginTheme = active_login_theme();
?>
<section class="super-console">
    <header class="super-console-head">
        <div><span><i class="bi bi-shield-lock-fill"></i> Chỉ Super Admin</span><h2>Điều khiển AI &amp; Widget</h2><p>Cấu hình hạ tầng mô hình, dữ liệu gốc và giao diện mà không đưa bí mật xuống trình duyệt.</p></div>
        <div class="super-console-actions"><a class="btn btn-light border" href="index.php?page=appearance-studio"><i class="bi bi-window-sidebar"></i> Theme đăng nhập</a><a class="btn btn-light border" href="index.php?page=admin-widget"><i class="bi bi-arrow-left"></i> Quản trị widget</a><a class="btn btn-brand" href="index.php?page=widget#assistant" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Mở chatbot</a></div>
    </header>

    <nav class="super-console-nav" aria-label="Khu vực cấu hình"><a href="#runtime"><i class="bi bi-sliders"></i> Runtime</a><a href="#providers"><i class="bi bi-cpu"></i> Providers</a><a href="#knowledge"><i class="bi bi-database-check"></i> Dữ liệu gốc</a><a href="#theme"><i class="bi bi-palette"></i> Theme widget</a><a href="index.php?page=appearance-studio"><i class="bi bi-window-sidebar"></i> Theme đăng nhập · <?= e($loginThemes[$activeLoginTheme]['name']) ?></a><a href="#logs"><i class="bi bi-activity"></i> Nhật ký</a></nav>

    <div class="super-status-strip">
        <span><i class="bi bi-power"></i><strong>Chatbot</strong><em><?= $settings['widget_ai_enabled'] === '1' ? 'Đang bật' : 'Đang tắt' ?></em></span>
        <span><i class="bi bi-cpu"></i><strong>Provider chính</strong><em><?= e($providers[$settings['widget_ai_provider']]['name'] ?? 'Chưa chọn') ?></em></span>
        <span><i class="bi bi-key"></i><strong>Gemini key đang bật</strong><em><?= (int) $providers['gemini']['enabled_key_count'] ?>/<?= (int) $providers['gemini']['key_count'] ?> slot</em></span>
        <span><i class="bi bi-database-check"></i><strong>Nguồn đủ điều kiện cho AI</strong><em><?= $eligibleKnowledgeCount ?> / <?= $activeKnowledgeCount ?> mục bật</em></span>
    </div>

    <form method="post" action="actions.php?action=save_super_admin_ai" class="super-settings-form">
        <?= csrf_field() ?>
        <section class="super-section" id="runtime">
            <div class="super-section-heading"><div><h3>Runtime &amp; nguyên tắc trả lời</h3><p>AI chỉ là lớp hỗ trợ trên dữ liệu gốc và hồ sơ đại sứ.</p></div><label class="super-switch"><input type="checkbox" name="widget_ai_enabled" <?= $settings['widget_ai_enabled'] === '1' ? 'checked' : '' ?>><span></span><b>Bật chatbot</b></label></div>
            <div class="super-field-grid">
                <label><span>Tên trợ lý</span><input name="widget_ai_name" value="<?= e($settings['widget_ai_name']) ?>" maxlength="60" required></label>
                <label><span>Provider đang dùng</span><select name="widget_ai_provider" required><?php foreach ($providers as $key => $provider): ?><option value="<?= e($key) ?>" <?= $settings['widget_ai_provider'] === $key ? 'selected' : '' ?>><?= e($provider['name']) ?></option><?php endforeach; ?></select></label>
                <label class="is-wide"><span>Lời chào trong widget</span><textarea name="widget_ai_welcome" rows="2" maxlength="500" required><?= e($settings['widget_ai_welcome']) ?></textarea></label>
                <label class="is-wide"><span>Rule bổ sung của nhà trường</span><textarea name="widget_ai_rules" rows="5" maxlength="3000" required><?= e($settings['widget_ai_rules']) ?></textarea><small>Rule hệ thống chống bịa dữ liệu và prompt injection luôn được giữ, không thể tắt từ giao diện.</small></label>
            </div>
        </section>

        <section class="super-section" id="providers">
            <div class="super-section-heading"><div><h3>Nhà cung cấp mô hình</h3><p>Gemini, DeepSeek, GLM và Qwen dùng chung giao thức Chat Completions.</p></div><span class="super-secure"><i class="bi bi-lock-fill"></i> Key mã hóa AES-256-GCM</span></div>
            <div class="provider-stack">
                <?php foreach ($providers as $key => $provider): ?>
                    <article class="provider-row">
                        <header><div class="provider-mark provider-<?= e($key) ?>"><?= e(mb_strtoupper(mb_substr($key, 0, 1))) ?></div><div><h4><?= e($provider['name']) ?></h4><p><?= e($provider['hint']) ?></p></div><span class="provider-state state-<?= e($provider['last_test_status']) ?>"><i></i><?= $provider['has_key'] ? ($provider['last_test_status'] === 'success' ? 'Kết nối tốt' : 'Đã có key') : 'Chưa có key' ?></span></header>
                        <div class="provider-fields <?= $key === 'gemini' ? 'provider-fields-gemini' : '' ?>">
                            <label class="provider-endpoint"><span>Endpoint</span><input type="url" name="providers[<?= e($key) ?>][endpoint]" value="<?= e($provider['endpoint']) ?>" required></label>
                            <label><span>Model</span><input name="providers[<?= e($key) ?>][model]" value="<?= e($provider['model']) ?>" required></label>
                            <?php if ($key !== 'gemini'): ?><label><span>API key mới</span><input type="password" name="providers[<?= e($key) ?>][api_key]" value="" autocomplete="new-password" placeholder="<?= $provider['has_key'] ? 'Đã lưu · để trống nếu giữ nguyên' : 'Dán API key tại đây' ?>"></label><?php endif; ?>
                        </div>
                        <footer><label><input type="checkbox" name="providers[<?= e($key) ?>][enabled]" <?= $provider['enabled'] ? 'checked' : '' ?>> Cho phép sử dụng</label><?php if ($key !== 'gemini'): ?><label><input type="checkbox" name="providers[<?= e($key) ?>][clear_key]"> Xóa key đã lưu</label><?php endif; ?><button class="btn btn-sm btn-light border" type="submit" formaction="actions.php?action=test_ai_provider" formnovalidate name="provider" value="<?= e($key) ?>"><i class="bi bi-plug"></i> Test kết nối</button><?php if ($provider['last_tested_at']): ?><small><?= e($provider['last_test_message']) ?> · <?= date('H:i d/m/Y', strtotime((string) $provider['last_tested_at'])) ?></small><?php endif; ?></footer>

                        <?php if ($key === 'gemini'): ?>
                            <div class="key-pool">
                                <div class="key-pool-heading">
                                    <div><h5>Vòng xoay API key</h5><p>Mỗi request dùng slot ít lượt nhất. Slot lỗi quota hoặc lỗi tạm thời sẽ nghỉ rồi tự quay lại.</p></div>
                                    <span><i class="bi bi-arrow-repeat"></i> <?= (int) $provider['enabled_key_count'] ?> đang chạy</span>
                                </div>
                                <div class="key-slot-list">
                                    <?php foreach ($geminiKeys as $slot):
                                        $isCooling = $slot['cooldown_until'] && strtotime((string) $slot['cooldown_until'] . ' UTC') > time();
                                        $slotState = !$slot['enabled'] ? 'paused' : ($isCooling ? 'cooldown' : (string) $slot['last_status']);
                                        $stateLabel = match ($slotState) {
                                            'success' => 'Sẵn sàng', 'rate_limited' => 'Đang giới hạn', 'auth_error' => 'Lỗi xác thực',
                                            'temporary_error', 'cooldown' => 'Đang nghỉ', 'failed', 'decrypt_error' => 'Cần kiểm tra',
                                            'paused' => 'Đã tắt', default => 'Chưa test',
                                        };
                                    ?>
                                        <div class="key-slot <?= !$slot['enabled'] ? 'is-paused' : '' ?>">
                                            <div class="key-slot-identity"><span class="key-slot-icon"><i class="bi bi-key-fill"></i></span><div><strong><?= e($slot['label']) ?></strong><code>•••• <?= e($slot['key_suffix']) ?></code></div></div>
                                            <div class="key-slot-metrics"><span><small>Lượt dùng</small><b><?= number_format((int) $slot['use_count']) ?></b></span><span><small>Lần gần nhất</small><b><?= $slot['last_used_at'] ? date('H:i d/m', strtotime((string) $slot['last_used_at'])) : 'Chưa dùng' ?></b></span></div>
                                            <span class="key-health key-health-<?= e($slotState) ?>"><i></i><?= e($stateLabel) ?></span>
                                            <div class="key-slot-actions">
                                                <button class="btn btn-sm btn-light border" type="submit" formaction="actions.php?action=test_ai_provider_key" formnovalidate name="key_id" value="<?= (int) $slot['id'] ?>" title="Test riêng slot này"><i class="bi bi-plug"></i></button>
                                                <button class="btn btn-sm btn-light border" type="submit" formaction="actions.php?action=toggle_ai_provider_key" formnovalidate name="key_id" value="<?= (int) $slot['id'] ?>"><?= $slot['enabled'] ? 'Tạm dừng' : 'Bật lại' ?></button>
                                                <button class="btn btn-sm btn-outline-danger" type="submit" formaction="actions.php?action=delete_ai_provider_key" formnovalidate name="key_id" value="<?= (int) $slot['id'] ?>" onclick="return confirm('Xóa API key này khỏi vòng xoay?')" title="Xóa slot"><i class="bi bi-trash3"></i></button>
                                            </div>
                                            <?php if ($isCooling): ?><small class="key-slot-note">Tự thử lại sau <?= date('H:i:s', strtotime((string) $slot['cooldown_until'] . ' UTC')) ?> · <?= e($slot['last_message'] ?: 'Provider yêu cầu chờ.') ?></small><?php elseif ($slot['last_message']): ?><small class="key-slot-note"><?= e($slot['last_message']) ?></small><?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (!$geminiKeys): ?><div class="key-pool-empty"><i class="bi bi-key"></i><div><strong>Chưa có key trong vòng xoay</strong><p>Thêm ít nhất một slot để Gemini bắt đầu trả lời.</p></div></div><?php endif; ?>
                                </div>
                                <div class="key-pool-add">
                                    <label><span>Tên slot</span><input name="key_label" maxlength="60" placeholder="Ví dụ: Gemini chính"></label>
                                    <label><span>API key mới</span><input type="password" name="api_key" autocomplete="new-password" placeholder="Dán key từ Google AI Studio"></label>
                                    <button class="btn btn-brand" type="submit" formaction="actions.php?action=add_ai_provider_key" formnovalidate name="provider" value="gemini"><i class="bi bi-plus-lg"></i> Thêm vào vòng xoay</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="super-section" id="theme">
            <div class="super-section-heading"><div><h3>Theme widget</h3><p>Bốn màu nền tảng được áp dụng trực tiếp cho widget nhúng.</p></div><span class="theme-live-dot"><i></i> Preview tức thời sau khi lưu</span></div>
            <div class="theme-color-grid">
                <?php foreach (['primary' => 'Màu hành động', 'navy' => 'Màu thương hiệu', 'soft' => 'Nền dịu', 'accent' => 'Màu nhấn'] as $key => $label): ?>
                    <label><input type="color" name="widget_theme_<?= e($key) ?>" value="<?= e($settings['widget_theme_' . $key]) ?>"><span><strong><?= e($label) ?></strong><small><?= e($settings['widget_theme_' . $key]) ?></small></span></label>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="super-save-bar"><span><i class="bi bi-info-circle"></i> Key để trống sẽ giữ nguyên giá trị đang mã hóa.</span><button class="btn btn-brand" type="submit"><i class="bi bi-check2-circle"></i> Lưu cấu hình hệ thống</button></div>
    </form>

    <section class="super-section" id="knowledge">
        <p class="mb-3"><a class="btn btn-outline-brand" href="index.php?page=ambassador-program&tab=knowledge"><i class="bi bi-patch-check"></i> Kiểm chứng nguồn & lịch sử xác nhận</a></p>
        <div class="super-section-heading"><div><h3>Dữ liệu gốc của trường</h3><p>Mục đang bật cần được kiểm chứng và còn hạn trước khi chatbot sử dụng.</p></div><span class="knowledge-count"><?= $eligibleKnowledgeCount ?> mục AI dùng được / <?= $activeKnowledgeCount ?> đang bật</span></div>
        <div class="knowledge-layout">
            <form class="knowledge-editor" method="post" action="actions.php?action=save_ai_knowledge">
                <?= csrf_field() ?><input type="hidden" name="knowledge_id" value="<?= (int) ($editingKnowledge['id'] ?? 0) ?>">
                <h4><?= $editingKnowledge ? 'Sửa dữ liệu gốc' : 'Thêm dữ liệu gốc' ?></h4>
                <label><span>Danh mục</span><input name="category" value="<?= e($editingKnowledge['category'] ?? '') ?>" placeholder="Ví dụ: Tuyển sinh 2026" required></label>
                <label><span>Tiêu đề</span><input name="title" value="<?= e($editingKnowledge['title'] ?? '') ?>" placeholder="Tên thông tin dễ nhận biết" required></label>
                <label><span>Từ khóa</span><input name="keywords" value="<?= e($editingKnowledge['keywords'] ?? '') ?>" placeholder="học phí, học bổng, hồ sơ"></label>
                <label><span>Nội dung đã được phê duyệt</span><textarea name="content" rows="8" placeholder="Dán nội dung chính thức tại đây..." required><?= e($editingKnowledge['content'] ?? '') ?></textarea></label>
                <div><button class="btn btn-brand" type="submit"><?= $editingKnowledge ? 'Lưu thay đổi' : 'Thêm vào kho' ?></button><?php if ($editingKnowledge): ?><a class="btn btn-light border" href="index.php?page=super-admin#knowledge">Hủy sửa</a><?php endif; ?></div>
            </form>
            <div class="knowledge-list">
                <?php foreach ($knowledgeEntries as $entry): ?><article class="knowledge-item <?= $entry['is_active'] ? '' : 'is-disabled' ?>"><div><span><?= e($entry['category']) ?></span><h4><?= e($entry['title']) ?></h4><p><?= e($entry['content']) ?></p><small>Từ khóa: <?= e($entry['keywords'] ?: 'Chưa đặt') ?> · Cập nhật <?= date('d/m/Y H:i', strtotime($entry['updated_at'])) ?></small></div><div class="knowledge-actions"><a class="btn btn-sm btn-light border" href="index.php?page=super-admin&amp;edit_knowledge=<?= (int) $entry['id'] ?>#knowledge"><i class="bi bi-pencil"></i> Sửa</a><form method="post" action="actions.php?action=toggle_ai_knowledge"><?= csrf_field() ?><input type="hidden" name="knowledge_id" value="<?= (int) $entry['id'] ?>"><button class="btn btn-sm <?= $entry['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>" type="submit"><?= $entry['is_active'] ? 'Tạm ẩn' : 'Bật lại' ?></button></form></div></article><?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="super-section" id="logs">
        <div class="super-section-heading"><div><h3>Nhật ký trợ lý gần nhất</h3><p>Dùng để rà soát câu hỏi chưa có dữ liệu và bổ sung knowledge base.</p></div><span><?= count($aiLogs) ?> lượt gần nhất</span></div>
        <div class="super-log-list">
            <?php foreach ($aiLogs as $log): ?><article><div><strong><?= e($log['question']) ?></strong><p><?= e($log['answer']) ?></p></div><span><b><?= e($providers[$log['provider']]['name'] ?? $log['provider']) ?></b><small><?= e($log['model']) ?> · <?= date('H:i d/m', strtotime($log['created_at'])) ?></small></span></article><?php endforeach; ?>
            <?php if (!$aiLogs): ?><div class="super-empty"><i class="bi bi-activity"></i><p>Nhật ký sẽ xuất hiện khi học sinh bắt đầu dùng chatbot.</p></div><?php endif; ?>
        </div>
    </section>
</section>
