<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\{AdminAuth, Lists};
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
        if (($_POST['action'] ?? '') === 'retry_registration_email') {
            $job = (int)($_POST['job_id'] ?? 0);
            $retry = $pdo->prepare("UPDATE order_registration_email_jobs j JOIN orders o ON o.id=j.order_id
                SET j.status='queued',j.attempts=0,j.available_at=NOW(),j.last_error=NULL
                WHERE j.id=? AND j.order_id=? AND j.status='failed'
                    AND o.status IN ('pending_payment','payment_review','partially_paid') AND o.payment_due_at > ?");
            $retry->execute([$job,$orderId,date('Y-m-d H:i:s')]);
            if ($retry->rowCount() !== 1) throw new RuntimeException('This email is no longer eligible for retry.');
            Flash::set('success', 'Registration email queued for retry.');
            header('Location: /admin/orders/' . $orderId, true, 303);
            exit;
        }
        (new PaymentService())->recordBankTransfer($orderId, (int) $adminUser['id'], (string) ($_POST['reference'] ?? ''), (float) ($_POST['amount'] ?? 0), $_FILES['evidence'] ?? []);
        Flash::set('success', 'Bank transfer recorded and registration status updated.');
        header('Location: /admin/orders/' . $orderId);
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$statement = $pdo->prepare("SELECT o.*,t.title,e.starts_at,e.ends_at,e.timezone,e.delivery_mode,i.invoice_number FROM orders o INNER JOIN training_editions e ON e.id=o.edition_id INNER JOIN trainings t ON t.id=e.training_id LEFT JOIN order_invoices i ON i.order_id=o.id WHERE o.id=? LIMIT 1");
$statement->execute([$orderId]);
$order = $statement->fetch();
if (!$order) { http_response_code(404); require dirname(__DIR__) . '/not-found.php'; exit; }
$invoiceJobs = $pdo->prepare('SELECT id,recipient,attach_invoice,status,attempts,last_error FROM order_registration_email_jobs WHERE order_id=? ORDER BY id');
$invoiceJobs->execute([$orderId]);
$paymentsStatement = $pdo->prepare("SELECT p.*,pe.id AS evidence_id,pe.original_name FROM payments p LEFT JOIN payment_evidence pe ON pe.payment_id=p.id WHERE p.order_id=? ORDER BY p.created_at DESC");
$paymentsStatement->execute([$orderId]);
$payments = $paymentsStatement->fetchAll();
$attendeesStatement = $pdo->prepare("SELECT a.*,r.registration_number,r.status AS registration_status FROM registrations r INNER JOIN attendees a ON a.id=r.attendee_id WHERE r.order_id=?");
$attendeesStatement->execute([$orderId]);
$attendees = $attendeesStatement->fetchAll();
$received = array_sum(array_map(static fn(array $p): float => $p['status']==='paid' ? (float) $p['amount'] : 0, $payments));
$outstanding = max(0, (float) $order['total'] - $received);
$lists = [
    'delegates'=>[$attendees,['name'=>'Name','email'=>'Email','company_name'=>'Company','job_title'=>'Job title','phone'=>'Phone','registration_number'=>'Registration','registration_status'=>'Status']],
    'payments'=>[$payments,['created_at'=>'Recorded','amount'=>'Amount','currency'=>'Currency','provider_reference'=>'Reference','status'=>'Status','original_name'=>'Evidence filename']],
    'emails'=>[$invoiceJobs->fetchAll(),['recipient'=>'Recipient','status'=>'Status','attempts'=>'Attempts','attach_invoice'=>'Includes invoice','last_error'=>'Last error']],
];
$selectedList = is_string($_GET['list'] ?? null) && isset($lists[$_GET['list']]) ? $_GET['list'] : 'delegates';
[$matches,$columns] = $lists[$selectedList];
$matches = Lists::searchRows($matches,array_keys($columns));
if (Lists::exporting()) {
    Lists::csv('order-'.$orderId.'-'.$selectedList,array_values($columns),
        array_map(static fn($row)=>array_map(static fn($key)=>$row[$key] ?? '',array_keys($columns)),$matches));
}
$visible = array_map(static fn($list)=>$list[0],$lists);
$visible[$selectedList] = $matches;
$listUrl = '/admin/orders/'.$orderId;
$csvUrl = $listUrl.'?'.http_build_query(['format'=>'csv','list'=>$selectedList,'q'=>Lists::query()]);
$adminPageTitle = 'Order ' . $order['order_number'];
require __DIR__ . '/_header.php';
?>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= e($error) ?></div><?php endif; ?>
<div class="order-heading"><div><span class="status status-<?= e($order['status']) ?>"><?= e(str_replace('_',' ',$order['status'])) ?></span><h2><?= e($order['title']) ?></h2><p><?= e(date('d M Y',strtotime($order['starts_at']))) ?> – <?= e(date('d M Y',strtotime($order['ends_at']))) ?> · <?= e($order['timezone']) ?></p></div><div class="order-total"><span>Order total · <?= (int)$order['quantity'] ?> <?= (int)$order['quantity'] === 1 ? 'delegate' : 'delegates' ?></span><strong><?= e(\Haleef\Support\MoneyDisplay::format((float)$order['total'], $order['currency'])) ?></strong><small>Outstanding <?= e(\Haleef\Support\MoneyDisplay::format($outstanding, $order['currency'])) ?></small></div></div>
<section class="admin-panel"><form class="admin-list-filter" method="get" action="<?= e($listUrl) ?>">
<label>Order list<select name="list"><?php foreach (['delegates'=>'Delegates','payments'=>'Payments','emails'=>'Registration email jobs'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= $key===$selectedList?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
<label>Search selected list<input name="q" type="search" value="<?= e(Lists::query()) ?>"></label><button type="submit">Filter</button>
<?php if (Lists::query() !== ''): ?><a href="<?= e($listUrl) ?>">Clear</a><?php endif; ?><a href="<?= e($csvUrl) ?>">Download CSV</a></form></section>
<div class="admin-two-column"><div><section class="admin-panel"><div class="panel-heading"><div><span>Purchaser</span><h2><?= e($order['purchaser_name']) ?></h2></div></div><dl class="admin-details"><div><dt>Email</dt><dd><?= e($order['purchaser_email']) ?></dd></div><div><dt>Phone</dt><dd><?= e((string)$order['purchaser_phone']) ?: '—' ?></dd></div><div><dt>Company</dt><dd><?= e((string)$order['company_name']) ?: '—' ?></dd></div><div><dt>Payment method</dt><dd><?= e(str_replace('_',' ',$order['selected_payment_method'])) ?></dd></div></dl></section><section class="admin-panel"><div class="panel-heading"><div><span>Registration</span><h2>Attendees</h2></div></div><?php foreach($visible['delegates'] as $attendee): ?><div class="attendee-row"><div><strong><?= e($attendee['name']) ?></strong><span><?= e($attendee['email']) ?></span><?php if ($attendee['job_title'] || $attendee['company_name']): ?><span><?= e(implode(' · ', array_filter([$attendee['job_title'],$attendee['company_name']]))) ?></span><?php endif; ?><?php if ($attendee['phone']): ?><span><?= e($attendee['phone']) ?></span><?php endif; ?></div><div><small><?= e($attendee['registration_number']) ?></small><span class="status status-<?= e($attendee['registration_status']) ?>"><?= e(str_replace('_',' ',$attendee['registration_status'])) ?></span></div></div><?php endforeach; ?></section></div>
<div><section class="admin-panel admin-form-panel"><span>Manual payment</span><h2>Record bank transfer</h2><?php if ($outstanding <= 0): ?><div class="admin-alert admin-alert-success">This order is fully paid.</div><?php else: ?><form method="post" enctype="multipart/form-data"><?= Csrf::field() ?><label>Bank reference<input name="reference" required></label><label>Amount received<input type="number" name="amount" min="0.01" step="0.01" max="<?= e((string)$outstanding) ?>" value="<?= e(number_format($outstanding,2,'.','')) ?>" required></label><label>Transfer confirmation<input type="file" name="evidence" accept="application/pdf,image/jpeg,image/png,image/webp" required><small>PDF, JPG, PNG or WebP. Maximum size is configured on the server.</small></label><button type="submit">Mark payment received</button></form><?php endif; ?></section><section class="admin-panel"><div class="panel-heading"><div><span>Audit trail</span><h2>Payments</h2></div></div><?php if(!$visible['payments']): ?><div class="admin-empty">No payments recorded.</div><?php else: ?><?php foreach($visible['payments'] as $payment): ?><div class="payment-row"><div><strong><?= e(\Haleef\Support\MoneyDisplay::format((float)$payment['amount'], $payment['currency'])) ?></strong><span><?= e($payment['provider_reference']) ?></span></div><div><span class="status status-<?= e($payment['status']) ?>"><?= e($payment['status']) ?></span><?php if($payment['evidence_id']): ?><a href="/admin/payment-evidence/<?= (int)$payment['evidence_id'] ?>"><?= e($payment['original_name']) ?></a><?php endif; ?></div></div><?php endforeach; ?><?php endif; ?></section></div></div>
<?php if (!empty($order['promo_code'])): ?>
<section class="admin-panel"><h2>Promo discount</h2><p><?= e($order['promo_code']) ?>:
    <?= e(\Haleef\Support\MoneyDisplay::format((float)$order['subtotal'] - (float)$order['total'], $order['currency'])) ?> off
    <?= e(\Haleef\Support\MoneyDisplay::format((float)$order['subtotal'], $order['currency'])) ?> subtotal.</p>
</section>
<?php endif; ?>
<?php if ($order['selected_payment_method'] === 'bank_transfer'): ?>
<section class="admin-panel">
    <?php if ($order['invoice_number']): ?>
    <div class="panel-heading"><div><span><?= e($order['invoice_number']) ?></span><h2>Invoice and registration emails</h2></div>
        <a href="/admin/orders/<?= $orderId ?>/invoice">Download invoice PDF</a></div>
    <?php foreach ($visible['emails'] as $job): ?>
    <div class="attendee-row"><div><strong><?= e($job['recipient']) ?></strong><span><?= $job['attach_invoice'] ? 'Includes invoice' : 'Registration details only' ?></span>
        <?php if ($job['last_error']): ?><small><?= e($job['last_error']) ?></small><?php endif; ?></div>
        <div><span class="status status-<?= e($job['status']) ?>"><?= e($job['status']) ?></span>
        <?php if ($job['status'] === 'failed' && in_array($order['status'], ['pending_payment','payment_review','partially_paid'], true) && strtotime((string)$order['payment_due_at']) > time()): ?>
        <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="retry_registration_email"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><button type="submit">Retry email</button></form>
        <?php endif; ?></div></div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="panel-heading"><div><h2>Bank transfer invoice</h2></div></div>
    <p>No automatic invoice was issued for this booking. Nothing is queued to send for this order.</p>
    <?php if (!\Haleef\Orders\Invoices::enabled()): ?>
    <p>Automatic issuance is currently off in the website configuration. Set <code>BANK_TRANSFER_INVOICES_ENABLED=1</code> after confirming the <a href="/admin/payment-methods">bank settings</a> and running the email worker preflight.</p>
    <?php else: ?>
    <p>Automatic issuance is on now, but it applies to new bank-transfer orders only. This booking needs a manual invoice.</p>
    <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>

