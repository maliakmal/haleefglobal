<?php
declare(strict_types=1);

require __DIR__ . '/includes/config.php';

use Haleef\Orders\{Invoices, OrderService};
use Haleef\Support\WebSession;

WebSession::resume();
Invoices::privateHeaders();

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
<section class="booking-page"><div class="container booking-card"><div class="booking-status booking-status-<?= e($order['status']) ?>"><span>Booking status</span><strong><?= e(ucwords(str_replace('_', ' ', $order['status']))) ?></strong></div><span class="eyebrow">Booking <?= e($order['order_number']) ?></span><h1><?= e($order['training_title']) ?></h1><p><?= e(date('d M Y', strtotime($order['starts_at']))) ?> – <?= e(date('d M Y', strtotime($order['ends_at']))) ?> · <?= e(\Haleef\Training\Editorial::timezoneLabel($order)) ?></p><p><?= (int)$order['quantity'] ?> <?= (int)$order['quantity'] === 1 ? 'delegate' : 'delegates' ?> · <?= e(\Haleef\Support\MoneyDisplay::format((float)$order['total'],$order['currency'])) ?> total</p>
<?php if (in_array($order['status'], ['pending_payment', 'partially_paid', 'payment_review'], true)): ?><div class="bank-instructions"><h2>Complete your bank transfer</h2><p><?= e($instructions['instructions']) ?></p><dl><?php foreach (['account_name' => 'Account name', 'bank_name' => 'Bank', 'iban' => 'IBAN', 'swift' => 'SWIFT', 'reference' => 'Payment reference'] as $key => $label): ?><?php if (!empty($instructions[$key])): ?><div><dt><?= e($label) ?></dt><dd><?= e($instructions[$key]) ?></dd></div><?php endif; ?><?php endforeach; ?><div><dt>Amount</dt><dd><?= e(\Haleef\Support\MoneyDisplay::format((float) $order['total'], $order['currency'])) ?></dd></div><?php if ($order['payment_due_at']): ?><div><dt>Payment due</dt><dd><?= e(date('d M Y, H:i', strtotime($order['payment_due_at']))) ?> <?= e(date_default_timezone_get()) ?></dd></div><?php endif; ?></dl><p class="booking-note">The Haleef team will update this booking after verifying the transfer.</p></div><?php elseif (in_array($order['status'], ['paid','partially_refunded'], true)): ?><div class="confirmation-panel"><h2>Your payment has been recorded.</h2><?php if (!empty($order['attendee_zoom_url'])): ?><p>Keep this booking URL private. You can join the session from here.</p><a class="button button-dark" rel="noreferrer" href="<?= e($order['attendee_zoom_url']) ?>">Join on Zoom</a><?php else: ?><p>Contact Haleef if you need joining instructions.</p><?php endif; ?></div><?php else: ?><div class="confirmation-panel"><h2>This booking is not active.</h2><p>Contact Haleef if you need help with this order.</p></div><?php endif; ?>
<?php if ($order['selected_payment_method'] === 'bank_transfer'): ?>
<section class="booking-invoice" aria-labelledby="booking-invoice-heading">
    <h3 id="booking-invoice-heading">Bank transfer invoice</h3>
    <?php if ($order['invoice_number'] && Invoices::sessionOrderId($order['order_number']) === (int)$order['id']): ?>
    <p>Your invoice is ready.</p>
    <a class="button button-dark" href="/training/invoice/<?= e($order['order_number']) ?>">Download invoice PDF</a>
    <?php elseif ($order['invoice_number']): ?>
    <p>For privacy, only the original registration session can download this invoice here. Check the booking contact’s email for the PDF, or <a href="/contact">contact Haleef</a> for help.</p>
    <?php else: ?>
    <p>No automatic invoice was issued for this booking. If you need one, <a href="/contact">contact Haleef</a> with booking reference <?= e($order['order_number']) ?>.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>

