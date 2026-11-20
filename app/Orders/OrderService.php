<?php
declare(strict_types=1);

namespace Haleef\Orders;

use Haleef\Payments\PaymentMethodRegistry;
use Haleef\Support\Database;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class OrderService
{
    public function __construct(private readonly PaymentMethodRegistry $paymentMethods = new PaymentMethodRegistry())
    {
    }

    public function create(array $edition, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $paymentCode = trim((string) ($input['payment_method'] ?? ''));

        if ($name === '' || !$email) {
            throw new RuntimeException('A valid attendee name and email address are required.');
        }

        $method = $this->paymentMethods->enabledMethod((int) $edition['brand_id'], $paymentCode);
        $amount = (float) $edition['price'];
        $publicToken = bin2hex(random_bytes(32));
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $editionLock = $pdo->prepare('SELECT capacity, registration_status FROM training_editions WHERE id = ? FOR UPDATE');
            $editionLock->execute([$edition['id']]);
            $lockedEdition = $editionLock->fetch();
            if (!$lockedEdition || $lockedEdition['registration_status'] !== 'open') {
                throw new RuntimeException('Registration for this edition has closed.');
            }
            if ($lockedEdition['capacity'] !== null) {
                $seatStatement = $pdo->prepare(
                    "SELECT COUNT(*) FROM registrations r
                     INNER JOIN orders o ON o.id = r.order_id
                     WHERE r.edition_id = ? AND r.status <> 'cancelled'
                       AND (o.status IN ('paid','partially_refunded') OR o.payment_due_at IS NULL OR o.payment_due_at >= NOW())"
                );
                $seatStatement->execute([$edition['id']]);
                if ((int) $seatStatement->fetchColumn() >= (int) $lockedEdition['capacity']) {
                    throw new RuntimeException('This edition has reached capacity. Please contact Haleef about the waitlist.');
                }
            }

            $dueDays = max(1, (int) ($method['configuration']['payment_due_days'] ?? 3));
            $now = new \DateTimeImmutable('now');
            $standardDue = $now->modify('+' . $dueDays . ' days');
            $courseDeadline = (new \DateTimeImmutable((string) $edition['starts_at']))->modify('-1 day');
            $paymentDue = $standardDue < $courseDeadline ? $standardDue : $courseDeadline;
            if ($paymentDue <= $now) {
                $paymentDue = $now->modify('+2 hours');
            }

            $temporary = 'TMP-' . bin2hex(random_bytes(10));
            $orderStatement = $pdo->prepare(
                'INSERT INTO orders (brand_id, edition_id, order_number, public_token_hash, purchaser_name, purchaser_email, purchaser_phone, company_name, quantity, subtotal, total, currency, selected_payment_method, payment_due_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)'
            );
            $orderStatement->execute([
                $edition['brand_id'], $edition['id'], $temporary, hash('sha256', $publicToken), $name, $email,
                trim((string) ($input['phone'] ?? '')) ?: null,
                trim((string) ($input['company'] ?? '')) ?: null,
                $amount, $amount, $edition['currency'], $paymentCode, $paymentDue->format('Y-m-d H:i:s'),
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $orderNumber = sprintf('HL-%s-%06d', date('Y'), $orderId);
            $pdo->prepare('UPDATE orders SET order_number = ? WHERE id = ?')->execute([$orderNumber, $orderId]);

            $attendeeStatement = $pdo->prepare(
                'INSERT INTO attendees (name, email, phone, company_name, job_title) VALUES (?, ?, ?, ?, ?)'
            );
            $attendeeStatement->execute([
                $name, $email,
                trim((string) ($input['phone'] ?? '')) ?: null,
                trim((string) ($input['company'] ?? '')) ?: null,
                trim((string) ($input['job_title'] ?? '')) ?: null,
            ]);
            $attendeeId = (int) $pdo->lastInsertId();
            $registrationNumber = sprintf('HLR-%s-%06d', date('Y'), $orderId);
            $pdo->prepare(
                'INSERT INTO registrations (order_id, edition_id, attendee_id, attendee_email, registration_number) VALUES (?, ?, ?, ?, ?)'
            )->execute([$orderId, $edition['id'], $attendeeId, strtolower((string) $email), $registrationNumber]);

            $order = [
                'id' => $orderId,
                'order_number' => $orderNumber,
                'total' => $amount,
                'currency' => $edition['currency'],
                'public_token' => $publicToken,
                'payment_method' => $method,
            ];

            $pdo->commit();
            return $order;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                throw new RuntimeException('This attendee is already registered for the selected edition.', 0, $exception);
            }
            throw $exception;
        }
    }

    public function findByPublicToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT o.*, e.starts_at, e.ends_at, e.timezone, e.delivery_mode, e.venue_name,
                    t.title AS training_title, t.slug AS training_slug, b.name AS brand_name
             FROM orders o
             INNER JOIN training_editions e ON e.id = o.edition_id
             INNER JOIN trainings t ON t.id = e.training_id
             INNER JOIN brands b ON b.id = o.brand_id
             WHERE o.public_token_hash = ? LIMIT 1'
        );
        $statement->execute([hash('sha256', $token)]);
        $order = $statement->fetch();
        if (!$order) {
            return null;
        }

        $method = $this->paymentMethods->configuredMethod((int) $order['brand_id'], (string) $order['selected_payment_method']);
        $order['payment_method'] = $method;
        $order['payment_instructions'] = $method['adapter']->customerInstructions($method['configuration'], $order);
        return $order;
    }
}

