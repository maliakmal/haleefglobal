<?php
declare(strict_types=1);

namespace Haleef\Orders;

use PDO;
use RuntimeException;

final class PromoCodes
{
    public static function normalize(mixed $value): string
    {
        return is_string($value) ? strtoupper(trim($value)) : '';
    }

    public static function validCode(string $code): bool
    {
        return preg_match('/\A[A-Z0-9][A-Z0-9_-]{1,39}\z/', $code) === 1;
    }

    public static function contactEmail(mixed $value): string
    {
        $email = is_string($value) ? strtolower(trim($value)) : '';
        return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    public static function discount(int $subtotal, int $percentage): int
    {
        // Round once at booking level (half up) and keep a non-zero payment due.
        return max(0, min($subtotal - 1, intdiv($subtotal * $percentage + 50, 100)));
    }

    public static function amount(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    public static function quote(PDO $pdo, string $code, string $email, int $trainingId, int $subtotal, bool $reserve = false): array
    {
        $unavailable = static fn() => new RuntimeException('This promo code is not available for this booking.');
        if (!self::validCode($code)) throw $unavailable();
        if ($email === '') throw new RuntimeException('Enter the booking contact’s email before applying a promo code.');

        // Order creation takes this lock before any ordinary reads in its transaction.
        // All new reservations for a code serialize here, including across editions.
        $find = $pdo->prepare('SELECT * FROM promo_codes WHERE code=?' . ($reserve ? ' FOR UPDATE' : ''));
        $find->execute([$code]);
        $promo = $find->fetch();
        $now = date('Y-m-d H:i:s');
        if (!$promo || !$promo['enabled']
            || ($promo['training_id'] !== null && (int)$promo['training_id'] !== $trainingId)
            || ($promo['starts_at'] !== null && $promo['starts_at'] > $now)
            || ($promo['ends_at'] !== null && $promo['ends_at'] <= $now)) throw $unavailable();
        if ($promo['private_only']) {
            $allowed = $pdo->prepare('SELECT 1 FROM promo_code_emails WHERE promo_id=? AND email=?');
            $allowed->execute([$promo['id'], $email]);
            if (!$allowed->fetchColumn()) throw $unavailable();
        }

        // Booking status is the source of truth: cancellation releases the reservation;
        // paid (including later refunds) and pending bookings continue to count.
        $uses = $pdo->prepare("SELECT COUNT(*) AS total, COUNT(CASE WHEN r.contact_email=? THEN 1 END) AS contact
            FROM promo_redemptions r JOIN orders o ON o.id=r.order_id WHERE r.promo_id=? AND o.status <> 'cancelled'");
        $uses->execute([$email, $promo['id']]);
        $usage = $uses->fetch();
        if ((int)$usage['contact'] > 0 || ($promo['max_uses'] !== null && (int)$usage['total'] >= (int)$promo['max_uses'])) {
            throw $unavailable();
        }
        $rate = (int)$promo['percentage'];
        $discount = self::discount($subtotal, $rate);
        if ($rate < 1 || $rate > 99 || $discount === 0) throw $unavailable();
        return ['id'=>(int)$promo['id'], 'code'=>$promo['code'], 'email'=>$email, 'percentage'=>$rate, 'discount'=>$discount];
    }
}
