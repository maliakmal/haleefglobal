<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

use Haleef\Support\Database;

$pdo = Database::connection();
$pdo->beginTransaction();
try {
    $ids = $pdo->query(
        "SELECT id FROM orders
         WHERE status = 'pending_payment' AND payment_due_at IS NOT NULL AND payment_due_at < NOW()
         FOR UPDATE"
    )->fetchAll(PDO::FETCH_COLUMN);

    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE registrations SET status = 'cancelled' WHERE order_id IN ({$placeholders}) AND status = 'pending_payment'")->execute($ids);
        $pdo->prepare("UPDATE orders SET status = 'cancelled', notes = CONCAT_WS('\n', notes, 'Automatically cancelled after the bank-transfer payment deadline.') WHERE id IN ({$placeholders})")->execute($ids);
    }

    $pdo->commit();
    echo count($ids) . " unpaid order(s) expired.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

