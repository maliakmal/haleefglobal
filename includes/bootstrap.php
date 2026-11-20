<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Autoload.php';

\Haleef\Support\Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set(\Haleef\Support\Env::get('APP_TIMEZONE', 'Asia/Dubai') ?: 'Asia/Dubai');

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $secureRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
    session_name('haleef_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secureRequest,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

