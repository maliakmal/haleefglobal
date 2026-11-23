<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\{AdminAuth, Lists};
use Haleef\Support\Database;

$adminUser = AdminAuth::requireUser();
$statuses = ['pending_payment'=>'Pending payment','payment_review'=>'Payment review','partially_paid'=>'Partially paid','paid'=>'Paid',
    'partially_refunded'=>'Partially refunded','refunded'=>'Refunded','cancelled'=>'Cancelled'];
$fields = 'o.id,o.order_number,o.created_at,o.purchaser_name,o.purchaser_email,t.title,e.starts_at,o.quantity,o.total,o.currency,o.selected_payment_method,o.status';
$q = Lists::select(Database::connection(),"SELECT $fields FROM orders o
    JOIN training_editions e ON e.id=o.edition_id JOIN trainings t ON t.id=e.training_id WHERE 1=1",
    ['o.order_number','o.purchaser_name','o.purchaser_email','o.company_name','t.title'],'o.created_at DESC,o.id DESC','o.status',$statuses);
if (Lists::exporting()) Lists::csv('orders',['ID','Order','Created','Purchaser','Email','Training','Edition start','Seats','Amount','Currency','Payment method','Status'],Lists::rows($q));
$orders = $q->fetchAll();
$adminPageTitle = 'Orders & registrations';
require __DIR__ . '/_header.php';
?>
<section class="admin-panel"><div class="panel-heading"><div><span>Commercial operations</span><h2>All orders</h2></div></div><?php Lists::filter('/admin/orders',$statuses,'Order, purchaser, company, or training'); ?><?php if (!$orders): ?><div class="admin-empty">No matching orders.</div><?php else: ?><div class="admin-table-wrap"><table><thead><tr><th>Order</th><th>Purchaser</th><th>Training</th><th>Edition</th><th>Amount</th><th>Payment mode</th><th>Status</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td><a href="/admin/orders/<?= (int) $order['id'] ?>"><?= e($order['order_number']) ?></a><small><?= e(date('d M Y H:i', strtotime($order['created_at']))) ?></small></td><td><strong><?= e($order['purchaser_name']) ?></strong><small><?= e($order['purchaser_email']) ?></small></td><td><?= e($order['title']) ?></td><td><?= e(date('d M Y', strtotime($order['starts_at']))) ?></td><td><?= e(\Haleef\Support\MoneyDisplay::format((float) $order['total'], $order['currency'])) ?></td><td><?= e(str_replace('_',' ',$order['selected_payment_method'])) ?></td><td><span class="status status-<?= e($order['status']) ?>"><?= e(str_replace('_',' ',$order['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require __DIR__ . '/_footer.php'; ?>

