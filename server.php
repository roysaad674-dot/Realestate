<?php
/**
 * Router for PHP's built-in development server.
 *
 *   php -S localhost:8787 -t public server.php
 *
 * Serves real files out of public/ and sends everything else to the front
 * controller, mirroring the Apache/Nginx rewrite used in production.
 *
 * Assets are streamed here rather than handed back with `return false`,
 * because the built-in server resolves that fallback inconsistently when the
 * router script lives outside the document root.
 */

$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$uri = '/' . ltrim($uri, '/');

$publicDir = __DIR__ . DIRECTORY_SEPARATOR . 'public';
$candidate = realpath($publicDir . str_replace('/', DIRECTORY_SEPARATOR, $uri));

// Serve a static asset, but only from inside public/ (no path traversal).
if (
    $uri !== '/'
    && $candidate !== false
    && is_file($candidate)
    && str_starts_with($candidate, (string) realpath($publicDir))
    && basename($candidate) !== 'index.php'
) {
    $types = [
        'css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
        'json' => 'application/json', 'map' => 'application/json',
        'pdf' => 'application/pdf', 'txt' => 'text/plain', 'webmanifest' => 'application/manifest+json',
    ];
    $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));

    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($candidate));
    header('Cache-Control: public, max-age=3600');
    readfile($candidate);
    exit;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/public/index.php';
