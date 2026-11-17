<?php
declare(strict_types=1);

// Router for PHP's built-in development server.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$localFile = __DIR__ . $path;

if ($path !== '/' && is_file($localFile)) {
    return false;
}

require __DIR__ . '/index.php';
