<?php
declare(strict_types=1);

namespace Haleef\Orders;

use RuntimeException;

final class Delegates
{
    public const LIMIT = 20;
    public const FIELDS = [
        'first_name'=>['First name','text','given-name',190],
        'surname'=>['Surname','text','family-name',190],
        'job_title'=>['Job title','text','organization-title',190],
        'phone'=>['Phone','tel','tel',80],
        'email'=>['Email','email','email',190],
        'company'=>['Company','text','organization',190],
    ];

    public static function validate(mixed $payload): array
    {
        if (!is_array($payload) || !$payload || count($payload) > self::LIMIT) {
            throw new RuntimeException('Enter details for 1 to ' . self::LIMIT . ' delegates.');
        }
        $delegates = [];
        foreach (array_values($payload) as $i => $raw) {
            $label = 'Delegate ' . ($i + 1);
            $delegate = [];
            foreach (self::FIELDS as $key => [$field,$type,$autocomplete,$limit]) {
                $value = trim(is_string($raw[$key] ?? null) ? $raw[$key] : '');
                if ($value === '' || mb_strlen($value, 'UTF-8') > $limit) {
                    throw new RuntimeException("$label: enter a valid " . strtolower($field) . '.');
                }
                $delegate[$key] = $value;
            }
            $delegate['name'] = $delegate['first_name'] . ' ' . $delegate['surname'];
            $email = strtolower($delegate['email']);
            if (mb_strlen($delegate['name'], 'UTF-8') > 190) throw new RuntimeException("$label: the full name is too long.");
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException("$label: enter a valid email address.");
            $delegate['email'] = $email;
            $delegate['phone'] = DelegatePhone::normalize($raw['phone_region'] ?? null, $delegate['phone'], $label);
            $delegates[] = $delegate;
        }
        return $delegates;
    }

    public static function cents(string $decimal): int
    {
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        return (int)$whole * 100 + (int)str_pad($fraction, 2, '0');
    }
}
