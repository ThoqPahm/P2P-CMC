<?php
declare(strict_types=1);
// Original login markup is intentionally frozen; font/icon assets load in the shared header.
$root = dirname(__DIR__);
$expected = [
    'pages/public/login.php' => 'afba79fdc151114a0781094b10515ed4fe74103737b1c124ab4b77abc79ebaa7',
    'pages/public/login-themes/eyes.php' => 'aad330ac15f9e74013a653c4b91a9365f15f613aadb806cecf874a973ebffb71',
    'pages/public/login-themes/particles.php' => '52c5e4d42286a4e330657138ed3f104c4ec5ef4ae306701de4fadd805bd35e35',
];
foreach ($expected as $file => $hash) {
    if (hash_file('sha256', "$root/$file") !== $hash) throw new RuntimeException("Original login changed: $file");
}
$typography = file_get_contents("$root/assets/css/typography.css");
if (!str_contains($typography, 'font-family: Inter;') || !str_contains($typography, 'font-family: Syne, "Trebuchet MS", sans-serif;')) throw new RuntimeException('Body/wordmark fonts must remain distinct');
foreach (['includes/header.php', 'pages/public/widget.php'] as $entry) {
    $html = file_get_contents("$root/$entry");
    if (!str_contains($html, 'family=Syne') || !str_contains($html, 'typography.css') || !str_contains($html, 'phosphor.css')) throw new RuntimeException("Missing requested font/icon assets: $entry");
}
echo "PASS original login markup, Inter body, Syne wordmark and Phosphor entry points.\n";
