<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/PresentationMode.php';

require_super_admin();
$presentationToken = PresentationMode::issue((int)user()['id']);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Trình diễn Chương 4 · CMC-eSA</title>
    <link rel="icon" href="assets/img/cmc-university.svg" type="image/svg+xml">
    <link href="assets/css/presentation-mode.css?v=3" rel="stylesheet">
</head>
<body class="presentation-mode">
    <main class="presentation-stage">
        <iframe id="presentationFrame" title="Bản mẫu CMC-eSA" allow="fullscreen"></iframe>
        <div class="presentation-transition" id="presentationTransition" aria-hidden="true">
            <div class="presentation-transition-loader" aria-hidden="true"><span></span><span></span><span></span></div>
            <div class="presentation-transition-context">
                <small id="presentationContextKicker">CHUYỂN GÓC NHÌN</small>
                <strong id="presentationContextRole"></strong>
                <span id="presentationContextDestination"></span>
            </div>
        </div>
        <div class="presentation-chapter" id="presentationChapter" aria-live="polite">
            <span id="presentationStep">1 / 8</span>
            <strong id="presentationTitle">Nhà trường định hướng chủ đề</strong>
        </div>
    </main>

    <nav class="presentation-controls" id="presentationControls" aria-label="Điều khiển trình diễn">
        <button id="presentationPrevious" type="button" aria-label="Cảnh trước">←</button>
        <button id="presentationOverview" type="button" aria-label="Xem kịch bản">Kịch bản</button>
        <button id="presentationNext" class="is-primary" type="button">Tiếp theo <span>→</span></button>
        <button id="presentationFullscreen" type="button" aria-label="Toàn màn hình">Toàn màn hình</button>
    </nav>

    <dialog class="presentation-overview" id="presentationOverviewDialog">
        <div class="presentation-overview-head">
            <div><small>CHƯƠNG 4</small><h1>Kịch bản trình diễn CMC-eSA</h1></div>
            <button type="button" id="presentationOverviewClose" aria-label="Đóng">×</button>
        </div>
        <ol id="presentationSceneList"></ol>
        <p><kbd>→</kbd> cảnh tiếp theo · <kbd>←</kbd> quay lại · <kbd>F</kbd> toàn màn hình · <kbd>H</kbd> ẩn/hiện điều khiển</p>
    </dialog>

    <script>
    window.CMC_PRESENTATION = <?= json_encode([
        'token' => $presentationToken,
        'frame' => 'presentation-frame.php',
        'action' => 'presentation-action.php',
        'api' => 'presentation-api.php',
        'routes' => Routes::PAGES,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    </script>
    <script src="assets/js/presentation-mode.js?v=6"></script>
</body>
</html>
