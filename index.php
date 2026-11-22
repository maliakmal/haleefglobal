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
    'training/trainers' => 'training-trainer.php',
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

if (preg_match('#^training/courses/([a-z0-9-]+)(?:/([a-z0-9-]+))?$#', $route, $matches)) {
    $_GET['training'] = $matches[1];
    $_GET['edition'] = $matches[2] ?? '';
    require __DIR__ . '/training-course.php';
    exit;
}

if (preg_match('#^training/trainers/([a-z0-9-]+)$#', $route, $matches)) {
    $_GET['trainer'] = $matches[1];
    require __DIR__ . '/training-trainer.php';
    exit;
}

if (preg_match('#^admin/preview/(trainings|editions|trainers)/([1-9][0-9]*)$#', $route, $matches)) {
    $previewKind = $matches[1];
    $previewId = (int) $matches[2];
    require __DIR__ . ($previewKind === 'trainers' ? '/training-trainer.php' : '/training-course.php');
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

if (preg_match('#^admin/(trainings|editions|trainers)(?:/(new|[1-9][0-9]*/edit))?$#', $route, $matches)) {
    $editorKind = $matches[1];
    $editorNew = ($matches[2] ?? '') === 'new';
    $editorId = (int) ($matches[2] ?? 0);
    require __DIR__ . '/admin/learning-editor.php';
    exit;
}

if ($route === 'admin/agenda-interest') {
    require __DIR__ . '/admin/agenda-interest.php';
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
