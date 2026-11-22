<?php
declare(strict_types=1);

namespace Haleef\Support;

final class MoneyDisplay
{
    private const PREFIX = [
        'AUD'=>'A$', 'CAD'=>'CA$', 'EUR'=>'€', 'GBP'=>'£', 'HKD'=>'HK$', 'INR'=>'₹',
        'JPY'=>'¥', 'NZD'=>'NZ$', 'PKR'=>'₨', 'SGD'=>'S$', 'USD'=>'US$', 'ZAR'=>'R ',
    ];

    // Use common Arabic abbreviations on systems without newer currency glyphs.
    private const SUFFIX = [
        'AED'=>'د.إ', 'BHD'=>'د.ب', 'EGP'=>'ج.م', 'JOD'=>'د.أ', 'KWD'=>'د.ك',
        'OMR'=>'ر.ع.', 'QAR'=>'ر.ق', 'SAR'=>'ر.س',
    ];

    public static function format(float $amount, string $currency): string
    {
        $code = strtoupper(trim($currency));
        $number = number_format($amount, 2, '.', ',');
        if (isset(self::PREFIX[$code])) {
            $sign = str_starts_with($number, '-') ? '-' : '';
            return $sign . self::PREFIX[$code] . ltrim($number, '-');
        }
        if (isset(self::SUFFIX[$code])) return $number . "\u{00A0}\u{2067}" . self::SUFFIX[$code] . "\u{2069}";
        return $code !== '' ? $code . ' ' . $number : $number;
    }
}
