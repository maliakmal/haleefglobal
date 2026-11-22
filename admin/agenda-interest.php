<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/config.php';
$adminUser = \Haleef\Admin\AdminAuth::requireUser();
$rows = \Haleef\Support\Database::connection()->query(
    'SELECT l.*, t.title, e.slug, a.title AS agenda_title FROM agenda_interests l
     JOIN training_editions e ON e.id=l.edition_id JOIN trainings t ON t.id=e.training_id
     JOIN media_assets a ON a.id=l.asset_id ORDER BY l.last_requested_at DESC LIMIT 500'
)->fetchAll();
$adminPageTitle = 'Agenda interest';
require __DIR__ . '/_header.php';
?>
<section class="admin-panel"><h2>Agenda downloads</h2><p>Latest 500 email and agenda combinations. A download request is not marketing consent.</p>
<div class="admin-table-wrap"><table><thead><tr><th>Email</th><th>Programme / edition</th><th>File</th><th>First request</th><th>Latest request</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['email']) ?></td><td><?= e($row['title']) ?><small><?= e($row['slug']) ?></small></td><td><?= e($row['agenda_title']) ?></td><td><?= e($row['first_requested_at']) ?></td><td><?= e($row['last_requested_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$rows): ?><p>No requests yet.</p><?php endif; ?></section>
<?php require __DIR__ . '/_footer.php'; ?>
