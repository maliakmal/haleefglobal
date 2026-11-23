<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/config.php';
$adminUser = \Haleef\Admin\AdminAuth::requireUser();
$pdo = \Haleef\Support\Database::connection();
$q = \Haleef\Admin\Lists::select($pdo,'SELECT l.email, t.title, e.slug, a.title AS agenda_title, l.first_requested_at, l.last_requested_at
        FROM agenda_interests l JOIN training_editions e ON e.id=l.edition_id
        JOIN trainings t ON t.id=e.training_id JOIN media_assets a ON a.id=l.asset_id WHERE 1=1',
        ['l.email','t.title','e.slug','a.title'],'l.last_requested_at DESC,l.id DESC','',[],500);
header('Cache-Control: private, no-store');
if (\Haleef\Admin\Lists::exporting()) \Haleef\Admin\Lists::csv('agenda-interest',
    ['Email','Programme','Edition','Agenda file','First request','Latest request'],\Haleef\Admin\Lists::rows($q));
$rows = $q->fetchAll();
$adminPageTitle = 'Agenda interest';
require __DIR__ . '/_header.php';
?>
<section class="admin-panel"><div class="panel-heading"><h2>Agenda downloads</h2></div>
<?php \Haleef\Admin\Lists::filter('/admin/agenda-interest',[],'Email, programme, edition, or file'); ?>
<p>Showing up to 500 recent matches. The CSV includes all matches. A download request is not marketing consent.</p>
<div class="admin-table-wrap"><table><thead><tr><th>Email</th><th>Programme / edition</th><th>File</th><th>First request</th><th>Latest request</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['email']) ?></td><td><?= e($row['title']) ?><small><?= e($row['slug']) ?></small></td><td><?= e($row['agenda_title']) ?></td><td><?= e($row['first_requested_at']) ?></td><td><?= e($row['last_requested_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$rows): ?><p>No requests yet.</p><?php endif; ?></section>
<?php require __DIR__ . '/_footer.php'; ?>
