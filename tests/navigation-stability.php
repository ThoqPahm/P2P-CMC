<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$css = file_get_contents($root . '/assets/css/app.css');
$icons = file_get_contents($root . '/assets/css/icon-system.css');
$header = file_get_contents($root . '/includes/header.php');
preg_match('/\.sidebar-link:hover\s*\{([^}]+)\}/', $css, $hover);
if (!$hover || str_contains($hover[1], 'transform:')) {
    throw new RuntimeException('Sidebar hover must not move the label.');
}
if (!str_contains($css, 'scrollbar-gutter: stable')) {
    throw new RuntimeException('Reserve the page scrollbar gutter.');
}
if (!str_contains($icons, '.sidebar-link:active .bi::before { transform: none; animation: none; transition: none; }')) {
    throw new RuntimeException('Navigation glyphs must remain stationary.');
}
if (!str_contains($header, 'rel="preload" href="assets/fonts/inter/InterVariable.woff2" as="font" type="font/woff2" crossorigin')) {
    throw new RuntimeException('Preload the body font with matching CORS mode.');
}
echo "PASS stationary navigation, stable scrollbar gutter and early Inter loading.\n";
