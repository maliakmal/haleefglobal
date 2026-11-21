<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$route = trim(rawurldecode($requestPath), '/');

$pages = [
    '' => 'home.php',
    'about' => 'about.php',
    'services' => 'services.php',
    'training' => 'training.php',
    'training/courses' => 'training-courses.php',
    'work' => 'work.php',
    'contact' => 'contact.php',
    'privacy' => 'privacy.php',
    'terms' => 'terms.php',
    'health' => 'health.php',
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

if (preg_match('#^training/courses/([a-z0-9-]+)$#', $route, $matches)) {
    $_GET['training'] = $matches[1];
    require __DIR__ . '/training-course.php';
    exit;
}

if (preg_match('#^training/register/([a-z0-9-]+)$#', $route, $matches)) {
    $_GET['edition'] = $matches[1];
    require __DIR__ . '/training-register.php';
    exit;
}

if (preg_match('#^training/booking/([a-f0-9]{64})$#', $route, $matches)) {
    $_GET['token'] = $matches[1];
    require __DIR__ . '/training-booking.php';
    exit;
}

if ($route === 'admin/login') {
    require __DIR__ . '/admin/login.php';
    exit;
}

if ($route === 'admin/logout') {
    require __DIR__ . '/admin/logout.php';
    exit;
}

if ($route === 'admin') {
    require __DIR__ . '/admin/index.php';
    exit;
}

if ($route === 'admin/trainings') {
    require __DIR__ . '/admin/trainings.php';
    exit;
}

if ($route === 'admin/editions') {
    require __DIR__ . '/admin/editions.php';
    exit;
}

if ($route === 'admin/orders') {
    require __DIR__ . '/admin/orders.php';
    exit;
}

if (preg_match('#^admin/orders/([0-9]+)$#', $route, $matches)) {
    $_GET['order_id'] = $matches[1];
    require __DIR__ . '/admin/order.php';
    exit;
}

if ($route === 'admin/payment-methods') {
    require __DIR__ . '/admin/payment-methods.php';
    exit;
}

if ($route === 'admin/resources') {
    require __DIR__ . '/admin/resources.php';
    exit;
}

if (preg_match('#^admin/payment-evidence/([0-9]+)$#', $route, $matches)) {
    $_GET['evidence_id'] = $matches[1];
    require __DIR__ . '/admin/payment-evidence.php';
    exit;
}

http_response_code(404);
require __DIR__ . '/not-found.php';
