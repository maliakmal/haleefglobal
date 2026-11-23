<?php
declare(strict_types=1);

namespace Haleef\Support;

final class Analytics
{
    public static function measurementId(): string
    {
        $value = trim(Env::get('GA4_MEASUREMENT_ID') ?? '');
        return preg_match('/^G-[A-Z0-9]{4,20}$/D',$value) ? $value : '';
    }

    public static function publicPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH);
        if (!is_string($path)) return '';
        $path = '/' . trim($path,'/');
        if (in_array($path,['/','/about','/services','/work','/contact','/training','/training/courses','/training/trainers'],true)) return $path;
        if (http_response_code() >= 400) return '';
        if (preg_match('~^/(?:services/[a-z0-9-]+|training/(?:courses/[a-z0-9-]+(?:/[a-z0-9-]+)?|trainers/[a-z0-9-]+))$~D',$path)) return $path;
        return '';
    }
}
