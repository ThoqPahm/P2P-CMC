<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_super_admin();
$savedStatement = $db->prepare('SELECT step_key, content FROM rehearsal_scripts WHERE user_id = ?');
$savedStatement->execute([(int) user()['id']]);
$savedScripts = [];
foreach ($savedStatement->fetchAll() as $savedScript) {
    $savedScripts[(string) $savedScript['step_key']] = (string) $savedScript['content'];
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Phòng tập thuyết trình · CMC-eSA</title>
    <link rel="icon" href="assets/img/cmc-university.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/rehearsal.css?v=2">
</head>
<body>
    <main class="rehearsal-shell">
        <section class="demo-pane" aria-label="Bản mẫu đang trình diễn">
            <header class="pane-header demo-header">
                <div><span class="live-dot"></span><strong id="visualType">SLIDE 22</strong></div>
                <p id="visualHint">Dùng phím mũi tên để đi xuyên suốt phần trình bày</p>
                <a id="fullscreenLink" href="presentation.php" target="_blank" rel="noopener">Mở prototype ↗</a>
            </header>
            <div class="slide-stage" id="slideStage">
                <img id="slideImage" src="assets/img/rehearsal-slide-22.png" alt="Slide 22 - Bốn nhóm giải pháp trả lời năm vấn đề">
            </div>
            <iframe class="is-hidden" id="rehearsalPresentation" src="presentation.php?embedded=rehearsal" title="Bản trình diễn CMC-eSA"></iframe>
        </section>

        <aside class="script-pane">
            <header class="script-header">
                <div>
                    <span class="eyebrow">PHÒNG TẬP CHƯƠNG 4</span>
                    <h1>Lời thuyết trình</h1>
                </div>
                <div class="script-actions">
                    <button id="editScript" type="button">Sửa lời</button>
                    <button class="is-hidden" id="cancelEdit" type="button">Hủy</button>
                    <button class="is-primary is-hidden" id="saveScript" type="button">Lưu</button>
                    <button id="focusMode" type="button" aria-pressed="false">Ẩn lời để tập</button>
                </div>
            </header>

            <div class="script-tabs" role="tablist" aria-label="Chế độ học">
                <button class="is-active" data-script-tab="full" role="tab" aria-selected="true">Lời nói</button>
                <button data-script-tab="outline" role="tab" aria-selected="false">Gạch ý</button>
            </div>

            <article class="script-card" id="scriptCard" aria-live="polite">
                <div class="step-meta">
                    <span class="role-badge" id="roleBadge"></span>
                    <span id="stepCounter">SLIDE 22</span>
                </div>
                <h2 id="scriptTitle"></h2>
                <p class="screen-cue"><span>Trên màn hình</span><strong id="screenCue"></strong></p>
                <div class="script-copy" id="scriptCopy"></div>
                <textarea class="script-editor" id="scriptEditor" aria-label="Sửa lời thuyết trình" maxlength="5000"></textarea>
                <p class="save-status" id="saveStatus" role="status"></p>
                <ul class="script-outline" id="scriptOutline"></ul>
                <div class="focus-placeholder" id="focusPlaceholder">
                    <span>Không nhìn lời</span>
                    <strong id="focusPrompt"></strong>
                    <small>Bấm “Hiện lời” khi cần kiểm tra.</small>
                </div>
            </article>

            <footer class="rehearsal-controls">
                <div class="progress-track"><span id="progressBar"></span></div>
                <div class="control-row">
                    <button id="previousStep" type="button">← Step trước</button>
                    <div><kbd>←</kbd><kbd>→</kbd><span>đổi đồng thời màn hình và lời nói</span></div>
                    <button class="is-primary" id="nextStep" type="button">Step tiếp →</button>
                </div>
            </footer>
        </aside>
    </main>
    <script>
    window.CMC_REHEARSAL = <?= json_encode([
        'csrfToken' => csrf_token(),
        'saveUrl' => 'rehearsal-api.php',
        'savedScripts' => $savedScripts,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    </script>
    <script src="assets/js/rehearsal.js?v=5"></script>
</body>
</html>
