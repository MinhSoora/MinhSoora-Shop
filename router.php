<?php
/**
 * Router cho PHP built-in server
 * Chạy: php -S 0.0.0.0:8888 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files
$staticFile = __DIR__ . '/public' . $uri;
if (is_file($staticFile)) return false;

$file = __DIR__ . $uri;
if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    require $file;
    return true;
}

// Route / to index.php
if ($uri === '/') { require __DIR__ . '/index.php'; return true; }

// 404
http_response_code(404);
echo '404 Not Found';
