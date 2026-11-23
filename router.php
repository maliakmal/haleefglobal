<?php
declare(strict_types=1);

// Router for PHP's built-in development server.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$localFile = __DIR__ . $path;

$protected = ['app', 'bin', 'database', 'deploy', 'docs', 'includes', 'storage', 'vendor', 'composer.json', 'composer.lock'];
$firstSegment = explode('/', ltrim($path, '/'))[0] ?? '';
if (in_array($firstSegment, $protected, true) || preg_match('~(?:^|/)\.~', $path)) {
    http_response_code(404);
    require __DIR__ . '/not-found.php';
    return true;
}

if ($path !== '/' && is_file($localFile)) {
    return false;
}

require __DIR__ . '/index.php';
