<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$decodedPath = rawurldecode((string) $path);
if (preg_match('~^/(?:data|tmp|app|tools|tests)(?:/|$)|(?:^|/)\.~i', $decodedPath)) {
    http_response_code(403);
    exit('Forbidden');
}
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
