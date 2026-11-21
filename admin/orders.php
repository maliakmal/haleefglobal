<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\AdminAuth;
use Haleef\Support\Database;

$adminUser = AdminAuth::requireUser();
$orders = Database::connection()->query("SELECT o.*,t.title,e.starts_at FROM orders o INNER JOIN training_editions e ON e.id=o.edition_id INNER JOIN trainings t ON t.id=e.training_id ORDER BY o.created_at DESC")->fetchAll();
$adminPageTitle = 'Orders & registrations';
require __DIR__ . '/_header.php';
?>
<section class="admin-panel"><div class="panel-heading"><div><span>Commercial operations</span><h2>All orders</h2></div></div><?php if (!$orders): ?><div class="admin-empty">No orders yet.</div><?php else: ?><div class="admin-table-wrap"><table><thead><tr><th>Order</th><th>Purchaser</th><th>Training</th><th>Edition</th><th>Amount</th><th>Payment mode</th><th>Status</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td><a href="/admin/orders/<?= (int) $order['id'] ?>"><?= e($order['order_number']) ?></a><small><?= e(date('d M Y H:i', strtotime($order['created_at']))) ?></small></td><td><strong><?= e($order['purchaser_name']) ?></strong><small><?= e($order['purchaser_email']) ?></small></td><td><?= e($order['title']) ?></td><td><?= e(date('d M Y', strtotime($order['starts_at']))) ?></td><td><?= e($order['currency']) ?> <?= e(number_format((float) $order['total'],2)) ?></td><td><?= e(str_replace('_',' ',$order['selected_payment_method'])) ?></td><td><span class="status status-<?= e($order['status']) ?>"><?= e(str_replace('_',' ',$order['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require __DIR__ . '/_footer.php'; ?>

