<?php
declare(strict_types=1);

require __DIR__ . '/includes/config.php';

use Haleef\Orders\OrderService;

try {
    $order = (new OrderService())->findByPublicToken((string) ($_GET['token'] ?? ''));
} catch (Throwable $exception) {
    $order = null;
}
if (!$order) {
    http_response_code(404);
    require __DIR__ . '/not-found.php';
    exit;
}

$pageTitle = 'Booking ' . $order['order_number'];
$currentPage = 'training';
$instructions = $order['payment_instructions'];
require __DIR__ . '/includes/header.php';
?>
<section class="booking-page"><div class="container booking-card"><div class="booking-status booking-status-<?= e($order['status']) ?>"><span>Booking status</span><strong><?= e(ucwords(str_replace('_', ' ', $order['status']))) ?></strong></div><span class="eyebrow">Booking <?= e($order['order_number']) ?></span><h1><?= e($order['training_title']) ?></h1><p><?= e(date('d M Y', strtotime($order['starts_at']))) ?> – <?= e(date('d M Y', strtotime($order['ends_at']))) ?> · <?= e($order['timezone']) ?></p>
<?php if (in_array($order['status'], ['pending_payment', 'partially_paid', 'payment_review'], true)): ?><div class="bank-instructions"><h2>Complete your bank transfer</h2><p><?= e($instructions['instructions']) ?></p><dl><?php foreach (['account_name' => 'Account name', 'bank_name' => 'Bank', 'iban' => 'IBAN', 'swift' => 'SWIFT', 'reference' => 'Payment reference'] as $key => $label): ?><?php if (!empty($instructions[$key])): ?><div><dt><?= e($label) ?></dt><dd><?= e($instructions[$key]) ?></dd></div><?php endif; ?><?php endforeach; ?><div><dt>Amount</dt><dd><?= e($order['currency']) ?> <?= e(number_format((float) $order['total'], 2)) ?></dd></div><?php if ($order['payment_due_at']): ?><div><dt>Payment due</dt><dd><?= e(date('d M Y, H:i', strtotime($order['payment_due_at']))) ?> <?= e($order['timezone']) ?></dd></div><?php endif; ?></dl><p class="booking-note">The Haleef team will update this booking after verifying the transfer.</p></div><?php else: ?><div class="confirmation-panel"><h2>Your registration is confirmed.</h2><p>Joining instructions and programme updates will be sent to <?= e($order['purchaser_email']) ?>.</p></div><?php endif; ?>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>

