<?php
declare(strict_types=1);

namespace Haleef\Support;

final class WebSession
{
    public const COOKIE = 'haleef_session';

    public static function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) return;
        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
        session_name(self::COOKIE);
        session_set_cookie_params(['lifetime'=>0,'httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);
        session_start();
    }

    public static function resume(): void
    {
        if (isset($_COOKIE[self::COOKIE]) && is_string($_COOKIE[self::COOKIE])) self::start();
    }
}
