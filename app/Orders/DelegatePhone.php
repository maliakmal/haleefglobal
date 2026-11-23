<?php
declare(strict_types=1);

namespace Haleef\Orders;

use RuntimeException;

final class DelegatePhone
{
    public const DEFAULT_REGION = 'AE';

    public static function regions(): array
    {
        static $regions;
        return $regions ??= require __DIR__ . '/dialing-regions.php';
    }

    public static function normalize(mixed $region, string $phone, string $field): string
    {
        $regions = self::regions();
        if ($region !== null && (!is_string($region) || !isset($regions[$region]))) {
            throw new RuntimeException("$field: select a country code.");
        }
        $phone = strtr(trim($phone), [
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);
        if (!preg_match('/^\+?[0-9() .\x{00a0}\x{202f}-]+$/uD', $phone)) {
            throw new RuntimeException("$field: enter a valid phone number without an extension.");
        }
        $digits = preg_replace('/[^0-9]/', '', $phone);
        [, $code, $dropZero] = $regions[$region ?? self::DEFAULT_REGION];
        if (str_starts_with($phone, '+') || str_starts_with($digits, '00')) {
            $digits = str_starts_with($phone, '+') ? $digits : substr($digits, 2);
            if ($region !== null && !str_starts_with($digits, $code)) {
                throw new RuntimeException("$field: the phone number does not match the selected country code.");
            }
        } else {
            if ($dropZero && str_starts_with($digits, '0')) $digits = substr($digits, 1);
            $digits = $code . $digits;
        }
        if (!preg_match('/^[1-9][0-9]{6,14}$/D', $digits)
            || ($region !== null && strlen($digits) - strlen($code) < 4)) {
            throw new RuntimeException("$field: enter a valid phone number.");
        }
        return '+' . $digits;
    }
}
