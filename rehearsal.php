<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_super_admin();
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Phòng tập thuyết trình · CMC-eSA</title>
    <link rel="icon" href="assets/img/cmc-university.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/rehearsal.css?v=1">
</head>
<body>
    <main class="rehearsal-shell">
        <section class="demo-pane" aria-label="Bản mẫu đang trình diễn">
            <header class="pane-header demo-header">
                <div><span class="live-dot"></span><strong>PROTOTYPE</strong></div>
                <p>Thao tác trực tiếp hoặc dùng phím mũi tên</p>
                <a href="presentation.php" target="_blank" rel="noopener">Mở toàn màn hình ↗</a>
            </header>
            <iframe id="rehearsalPresentation" src="presentation.php?embedded=rehearsal" title="Bản trình diễn CMC-eSA"></iframe>
        </section>

        <aside class="script-pane">
            <header class="script-header">
                <div>
                    <span class="eyebrow">PHÒNG TẬP CHƯƠNG 4</span>
                    <h1>Lời thuyết trình</h1>
                </div>
                <button id="focusMode" type="button" aria-pressed="false">Ẩn lời để tập</button>
            </header>

            <div class="script-tabs" role="tablist" aria-label="Chế độ học">
                <button class="is-active" data-script-tab="full" role="tab" aria-selected="true">Lời nói</button>
                <button data-script-tab="outline" role="tab" aria-selected="false">Gạch ý</button>
            </div>

            <article class="script-card" id="scriptCard" aria-live="polite">
                <div class="step-meta">
                    <span class="role-badge" id="roleBadge"></span>
                    <span id="stepCounter">STEP 1 / 8</span>
                </div>
                <h2 id="scriptTitle"></h2>
                <p class="screen-cue"><span>Trên màn hình</span><strong id="screenCue"></strong></p>
                <div class="script-copy" id="scriptCopy"></div>
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
    <script src="assets/js/rehearsal.js?v=2"></script>
</body>
</html>
