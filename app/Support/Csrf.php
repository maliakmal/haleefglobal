<?php
declare(strict_types=1);

namespace Haleef\Support;

use RuntimeException;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(?string $token): void
    {
        if (!$token || !hash_equals(self::token(), $token)) {
            throw new RuntimeException('Your session expired. Please refresh the page and try again.');
        }
    }
}

