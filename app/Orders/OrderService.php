<?php
declare(strict_types=1);

namespace Haleef\Orders;

use Haleef\Payments\PaymentMethodRegistry;
use Haleef\Support\Database;
use Haleef\Training\Editorial;
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
        $delegates = Delegates::validate($input['delegates'] ?? null);
        $quantity = count($delegates);
        $contact = $delegates[0];
        $paymentCode = is_string($input['payment_method'] ?? null) ? trim($input['payment_method']) : '';

        $method = $this->paymentMethods->enabledMethod((int) $edition['brand_id'], $paymentCode);
        $issueInvoice = $paymentCode === 'bank_transfer' && Invoices::enabled();
        $billingAddress = $issueInvoice ? Invoices::billingAddress($input['billing_address'] ?? '') : '';
        if ($issueInvoice && !RegistrationEmail::configured()) {
            error_log('Bank transfer invoices enabled without a Brevo registration sender.');
            throw new RuntimeException('Bank transfer registration is temporarily unavailable. Please contact Haleef.');
        }
        $publicToken = bin2hex(random_bytes(32));
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $editionLock = $pdo->prepare('SELECT e.*, t.status AS training_status, t.title AS training_title FROM training_editions e JOIN trainings t ON t.id=e.training_id WHERE e.id = ? FOR UPDATE');
            $editionLock->execute([$edition['id']]);
            $lockedEdition = $editionLock->fetch();
            if (!$lockedEdition || $lockedEdition['registration_status'] !== 'open' || $lockedEdition['publication_status'] !== 'published' || $lockedEdition['training_status'] !== 'published' || Editorial::ended($lockedEdition)) {
                throw new RuntimeException('Registration for this edition has closed.');
            }
            $unitCents = Delegates::cents((string)$lockedEdition['price']);
            $totalCents = $unitCents * $quantity;
            if ($totalCents > 999999999999) throw new RuntimeException('This group total is too large. Please contact Haleef to book.');
            $amount = sprintf('%d.%02d', intdiv($totalCents,100), $totalCents % 100);
            $edition['currency'] = $lockedEdition['currency'];
            if ($lockedEdition['capacity'] !== null) {
                $seatStatement = $pdo->prepare(
                    "SELECT COUNT(*) FROM registrations r
                     INNER JOIN orders o ON o.id = r.order_id
                     WHERE r.edition_id = ? AND r.status <> 'cancelled'
                       AND (o.status IN ('paid','partially_refunded') OR o.payment_due_at IS NULL OR o.payment_due_at >= NOW())"
                );
                $seatStatement->execute([$edition['id']]);
                if ((int) $seatStatement->fetchColumn() + $quantity > (int) $lockedEdition['capacity']) {
                    throw new RuntimeException('There are not enough seats for your group. Reduce the number of delegates or contact Haleef.');
                }
            }

            $dueDays = max(1, (int) ($method['configuration']['payment_due_days'] ?? 3));
            $now = new \DateTimeImmutable('now');
            $standardDue = $now->modify('+' . $dueDays . ' days');
            $courseDeadline = Editorial::date($lockedEdition, 'starts_at')->modify('-1 day');
            $paymentDue = $standardDue < $courseDeadline ? $standardDue : $courseDeadline;
            if ($paymentDue <= $now) {
                $paymentDue = $now->modify('+2 hours');
            }

            $temporary = 'TMP-' . bin2hex(random_bytes(10));
            $orderStatement = $pdo->prepare(
                'INSERT INTO orders (brand_id, edition_id, order_number, public_token_hash, purchaser_name, purchaser_email, purchaser_phone, company_name, quantity, subtotal, total, currency, selected_payment_method, payment_due_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $orderStatement->execute([
                $edition['brand_id'], $edition['id'], $temporary, hash('sha256', $publicToken), $contact['name'], $contact['email'],
                $contact['phone'], $contact['company'], $quantity, $amount, $amount, $edition['currency'], $paymentCode,
                $paymentDue->setTimezone($now->getTimezone())->format('Y-m-d H:i:s'),
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $orderNumber = sprintf('HL-%s-%06d', date('Y'), $orderId);
            $pdo->prepare('UPDATE orders SET order_number = ? WHERE id = ?')->execute([$orderNumber, $orderId]);

            $attendeeStatement = $pdo->prepare(
                'INSERT INTO attendees (name, email, phone, company_name, job_title, first_name, surname) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $registration = $pdo->prepare(
                'INSERT INTO registrations (order_id, edition_id, attendee_id, attendee_email, registration_number) VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($delegates as $i=>$delegate) {
                $attendeeStatement->execute([$delegate['name'],$delegate['email'],$delegate['phone'],$delegate['company'],
                    $delegate['job_title'],$delegate['first_name'],$delegate['surname']]);
                $attendeeId = (int)$pdo->lastInsertId();
                $number = sprintf('HLR-%s-%06d', date('Y'), $orderId) . ($i ? '-' . ($i + 1) : '');
                $registration->execute([$orderId,$edition['id'],$attendeeId,$delegate['email'],$number]);
                $delegates[$i]['registration_number'] = $number;
                $delegates[$i]['registration_id'] = (int)$pdo->lastInsertId();
            }

            $order = [
                'id' => $orderId,
                'order_number' => $orderNumber,
                'total' => (float)$amount,
                'quantity' => $quantity,
                'currency' => $edition['currency'],
                'public_token' => $publicToken,
                'payment_method' => $method,
            ];

            if ($issueInvoice) {
                try {
                    $invoice = Invoices::issue($pdo, $order, $lockedEdition, $contact, $billingAddress,
                        $now, $paymentDue->setTimezone($now->getTimezone()), $unitCents);
                    RegistrationEmail::queue($pdo, $orderId, $invoice, $delegates);
                    $order['has_invoice'] = true;
                } catch (Throwable $exception) {
                    error_log('Could not issue bank transfer invoice: ' . get_class($exception) . ' (' . $exception->getCode() . ')');
                    throw new RuntimeException('We couldn’t prepare your invoice. No booking was placed. Please try again or contact Haleef.', 0, $exception);
                }
            }

            $pdo->commit();
            return $order;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                throw new RuntimeException('One of these delegates is already registered for this edition. No seats were booked.', 0, $exception);
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
            "SELECT o.*, e.starts_at, e.ends_at, e.timezone, e.delivery_mode, e.venue_name,
                    CASE WHEN o.status='paid' AND e.delivery_mode IN ('online','hybrid')
                         AND EXISTS (SELECT 1 FROM registrations r WHERE r.order_id=o.id AND r.status IN ('confirmed','attended'))
                         THEN e.zoom_url ELSE NULL END AS attendee_zoom_url,
                    t.title AS training_title, t.slug AS training_slug, b.name AS brand_name, i.invoice_number, i.payment_snapshot
             FROM orders o
             INNER JOIN training_editions e ON e.id = o.edition_id
             INNER JOIN trainings t ON t.id = e.training_id
             INNER JOIN brands b ON b.id = o.brand_id
             LEFT JOIN order_invoices i ON i.order_id=o.id
             WHERE o.public_token_hash = ? LIMIT 1"
        );
        $statement->execute([hash('sha256', $token)]);
        $order = $statement->fetch();
        if (!$order) {
            return null;
        }

        $method = $this->paymentMethods->configuredMethod((int) $order['brand_id'], (string) $order['selected_payment_method']);
        $order['payment_method'] = $method;
        $order['payment_instructions'] = $order['invoice_number']
            ? json_decode($order['payment_snapshot'], true, 512, JSON_THROW_ON_ERROR)
            : $method['adapter']->customerInstructions($method['configuration'], $order);
        return $order;
    }
}

