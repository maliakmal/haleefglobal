<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\AdminAuth;
use Haleef\Support\Database;

$adminUser = AdminAuth::requireUser();
$pdo = Database::connection();
$stats = [
    'upcoming' => (int) $pdo->query("SELECT COUNT(*) FROM training_editions WHERE ends_at >= NOW()")->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending_payment','payment_review','partially_paid')")->fetchColumn(),
    'confirmed' => (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'confirmed'")->fetchColumn(),
    'revenue' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid' AND currency = 'AED'")->fetchColumn(),
];
$recent = $pdo->query("SELECT o.id, o.order_number, o.purchaser_name, o.total, o.currency, o.status, o.created_at, t.title FROM orders o INNER JOIN training_editions e ON e.id=o.edition_id INNER JOIN trainings t ON t.id=e.training_id ORDER BY o.created_at DESC LIMIT 8")->fetchAll();
$adminPageTitle = 'Dashboard';
require __DIR__ . '/_header.php';
?>
<section class="stat-grid"><article><span>Upcoming editions</span><strong><?= $stats['upcoming'] ?></strong></article><article><span>Awaiting payment</span><strong><?= $stats['pending'] ?></strong></article><article><span>Confirmed attendees</span><strong><?= $stats['confirmed'] ?></strong></article><article><span>Recorded revenue</span><strong><?= e(\Haleef\Support\MoneyDisplay::format($stats['revenue'], 'AED')) ?></strong></article></section>
<section class="admin-panel"><div class="panel-heading"><div><span>Latest activity</span><h2>Recent orders</h2></div><a href="/admin/orders">View all</a></div><?php if (!$recent): ?><div class="admin-empty">No registrations have been received yet.</div><?php else: ?><div class="admin-table-wrap"><table><thead><tr><th>Order</th><th>Purchaser</th><th>Training</th><th>Amount</th><th>Status</th></tr></thead><tbody><?php foreach ($recent as $order): ?><tr><td><a href="/admin/orders/<?= (int) $order['id'] ?>"><?= e($order['order_number']) ?></a></td><td><?= e($order['purchaser_name']) ?></td><td><?= e($order['title']) ?></td><td><?= e(\Haleef\Support\MoneyDisplay::format((float) $order['total'], $order['currency'])) ?></td><td><span class="status status-<?= e($order['status']) ?>"><?= e(str_replace('_', ' ', $order['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require __DIR__ . '/_footer.php'; ?>

