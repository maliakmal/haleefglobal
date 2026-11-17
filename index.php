<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$route = trim(rawurldecode($requestPath), '/');

$pages = [
    '' => 'home.php',
    'about' => 'about.php',
    'services' => 'services.php',
    'training' => 'training.php',
    'work' => 'work.php',
    'contact' => 'contact.php',
    'privacy' => 'privacy.php',
    'terms' => 'terms.php',
];

if (isset($pages[$route])) {
    require __DIR__ . '/' . $pages[$route];
    exit;
}

if (preg_match('#^services/([a-z0-9-]+)$#', $route, $matches)) {
    $_GET['service'] = $matches[1];
    require __DIR__ . '/service.php';
    exit;
}

http_response_code(404);
require __DIR__ . '/not-found.php';
