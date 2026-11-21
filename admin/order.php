<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\AdminAuth;
use Haleef\Payments\PaymentService;
use Haleef\Support\Csrf;
use Haleef\Support\Database;
use Haleef\Support\Flash;

$adminUser = AdminAuth::requireUser();
$pdo = Database::connection();
$orderId = (int) ($_GET['order_id'] ?? 0);
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::verify($_POST['_csrf'] ?? null);
        (new PaymentService())->recordBankTransfer($orderId, (int) $adminUser['id'], (string) ($_POST['reference'] ?? ''), (float) ($_POST['amount'] ?? 0), $_FILES['evidence'] ?? []);
        Flash::set('success', 'Bank transfer recorded and registration status updated.');
        header('Location: /admin/orders/' . $orderId);
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$statement = $pdo->prepare("SELECT o.*,t.title,e.starts_at,e.ends_at,e.timezone,e.delivery_mode FROM orders o INNER JOIN training_editions e ON e.id=o.edition_id INNER JOIN trainings t ON t.id=e.training_id WHERE o.id=? LIMIT 1");
$statement->execute([$orderId]);
$order = $statement->fetch();
if (!$order) { http_response_code(404); require dirname(__DIR__) . '/not-found.php'; exit; }
$paymentsStatement = $pdo->prepare("SELECT p.*,pe.id AS evidence_id,pe.original_name FROM payments p LEFT JOIN payment_evidence pe ON pe.payment_id=p.id WHERE p.order_id=? ORDER BY p.created_at DESC");
$paymentsStatement->execute([$orderId]);
$payments = $paymentsStatement->fetchAll();
$attendeesStatement = $pdo->prepare("SELECT a.*,r.registration_number,r.status AS registration_status FROM registrations r INNER JOIN attendees a ON a.id=r.attendee_id WHERE r.order_id=?");
$attendeesStatement->execute([$orderId]);
$attendees = $attendeesStatement->fetchAll();
$received = array_sum(array_map(static fn(array $p): float => $p['status']==='paid' ? (float) $p['amount'] : 0, $payments));
$outstanding = max(0, (float) $order['total'] - $received);
$adminPageTitle = 'Order ' . $order['order_number'];
require __DIR__ . '/_header.php';
?>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= e($error) ?></div><?php endif; ?>
<div class="order-heading"><div><span class="status status-<?= e($order['status']) ?>"><?= e(str_replace('_',' ',$order['status'])) ?></span><h2><?= e($order['title']) ?></h2><p><?= e(date('d M Y',strtotime($order['starts_at']))) ?> – <?= e(date('d M Y',strtotime($order['ends_at']))) ?> · <?= e($order['timezone']) ?></p></div><div class="order-total"><span>Order total</span><strong><?= e($order['currency']) ?> <?= e(number_format((float)$order['total'],2)) ?></strong><small>Outstanding <?= e($order['currency']) ?> <?= e(number_format($outstanding,2)) ?></small></div></div>
<div class="admin-two-column"><div><section class="admin-panel"><div class="panel-heading"><div><span>Purchaser</span><h2><?= e($order['purchaser_name']) ?></h2></div></div><dl class="admin-details"><div><dt>Email</dt><dd><?= e($order['purchaser_email']) ?></dd></div><div><dt>Phone</dt><dd><?= e((string)$order['purchaser_phone']) ?: '—' ?></dd></div><div><dt>Company</dt><dd><?= e((string)$order['company_name']) ?: '—' ?></dd></div><div><dt>Payment method</dt><dd><?= e(str_replace('_',' ',$order['selected_payment_method'])) ?></dd></div></dl></section><section class="admin-panel"><div class="panel-heading"><div><span>Registration</span><h2>Attendees</h2></div></div><?php foreach($attendees as $attendee): ?><div class="attendee-row"><div><strong><?= e($attendee['name']) ?></strong><span><?= e($attendee['email']) ?></span></div><div><small><?= e($attendee['registration_number']) ?></small><span class="status status-<?= e($attendee['registration_status']) ?>"><?= e(str_replace('_',' ',$attendee['registration_status'])) ?></span></div></div><?php endforeach; ?></section></div>
<div><section class="admin-panel admin-form-panel"><span>Manual payment</span><h2>Record bank transfer</h2><?php if ($outstanding <= 0): ?><div class="admin-alert admin-alert-success">This order is fully paid.</div><?php else: ?><form method="post" enctype="multipart/form-data"><?= Csrf::field() ?><label>Bank reference<input name="reference" required></label><label>Amount received<input type="number" name="amount" min="0.01" step="0.01" max="<?= e((string)$outstanding) ?>" value="<?= e(number_format($outstanding,2,'.','')) ?>" required></label><label>Transfer confirmation<input type="file" name="evidence" accept="application/pdf,image/jpeg,image/png,image/webp" required><small>PDF, JPG, PNG or WebP. Maximum size is configured on the server.</small></label><button type="submit">Mark payment received</button></form><?php endif; ?></section><section class="admin-panel"><div class="panel-heading"><div><span>Audit trail</span><h2>Payments</h2></div></div><?php if(!$payments): ?><div class="admin-empty">No payments recorded.</div><?php else: ?><?php foreach($payments as $payment): ?><div class="payment-row"><div><strong><?= e($payment['currency']) ?> <?= e(number_format((float)$payment['amount'],2)) ?></strong><span><?= e($payment['provider_reference']) ?></span></div><div><span class="status status-<?= e($payment['status']) ?>"><?= e($payment['status']) ?></span><?php if($payment['evidence_id']): ?><a href="/admin/payment-evidence/<?= (int)$payment['evidence_id'] ?>"><?= e($payment['original_name']) ?></a><?php endif; ?></div></div><?php endforeach; ?><?php endif; ?></section></div></div>
<?php require __DIR__ . '/_footer.php'; ?>

