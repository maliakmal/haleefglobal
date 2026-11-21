<?php
declare(strict_types=1);

namespace Haleef\Payments;

use Haleef\Storage\PrivateUploadStorage;
use Haleef\Support\Database;
use RuntimeException;
use Throwable;

final class PaymentService
{
    public function __construct(private readonly PrivateUploadStorage $storage = new PrivateUploadStorage())
    {
    }

    public function recordBankTransfer(int $orderId, int $adminId, string $reference, float $amount, array $evidence): int
    {
        $reference = trim($reference);
        if ($reference === '' || $amount <= 0) {
            throw new RuntimeException('A bank reference and a positive received amount are required.');
        }

        $stored = $this->storage->storeEvidence($evidence);
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $orderStatement = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $orderStatement->execute([$orderId]);
            $order = $orderStatement->fetch();
            if (!$order || in_array($order['status'], ['cancelled', 'refunded'], true)) {
                throw new RuntimeException('This order cannot accept a payment.');
            }

            $totalStatement = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = ? AND status = 'paid'");
            $totalStatement->execute([$orderId]);
            $alreadyReceived = (float) $totalStatement->fetchColumn();
            $outstanding = max(0, (float) $order['total'] - $alreadyReceived);
            if ($outstanding <= 0.00001) {
                throw new RuntimeException('This order is already fully paid.');
            }
            if ($amount > $outstanding + 0.00001) {
                throw new RuntimeException('The received amount is greater than the outstanding order balance.');
            }

            $paymentStatement = $pdo->prepare(
                "INSERT INTO payments (order_id, payment_method_code, provider, provider_reference, amount, currency, status, recorded_by, paid_at)
                 VALUES (?, 'bank_transfer', 'manual', ?, ?, ?, 'paid', ?, NOW())"
            );
            $paymentStatement->execute([$orderId, $reference, $amount, $order['currency'], $adminId]);
            $paymentId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                "INSERT INTO payment_evidence (payment_id, original_name, storage_path, mime_type, size_bytes, sha256, uploaded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $paymentId, $stored['original_name'], $stored['storage_path'], $stored['mime_type'],
                $stored['size_bytes'], $stored['sha256'], $adminId,
            ]);

            $received = $alreadyReceived + $amount;
            $fullyPaid = $received + 0.00001 >= (float) $order['total'];
            $newStatus = $fullyPaid ? 'paid' : 'partially_paid';
            $pdo->prepare('UPDATE orders SET status = ?, paid_at = IF(? = 1, NOW(), paid_at) WHERE id = ?')
                ->execute([$newStatus, $fullyPaid ? 1 : 0, $orderId]);

            if ($fullyPaid) {
                $pdo->prepare("UPDATE registrations SET status = 'confirmed', confirmed_at = NOW() WHERE order_id = ? AND status = 'pending_payment'")
                    ->execute([$orderId]);
            }

            $pdo->commit();
            return $paymentId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            @unlink($this->storage->absolutePath($stored['storage_path']));
            throw $exception;
        }
    }
}

