<?php
// Compatibility front controller and router for the built-in PHP server.
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$publicPath = __DIR__ . '/public' . $uri;

if (PHP_SAPI === 'cli-server' && $uri !== '/' && is_file($publicPath)) {
    $mime = mime_content_type($publicPath) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    readfile($publicPath);
    return true;
}

require __DIR__ . '/public/index.php';
