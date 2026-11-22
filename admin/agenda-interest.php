<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/config.php';
$adminUser = \Haleef\Admin\AdminAuth::requireUser();
$pdo = \Haleef\Support\Database::connection();
$sql = 'SELECT l.email, t.title, e.slug, a.title AS agenda_title, l.first_requested_at, l.last_requested_at
        FROM agenda_interests l JOIN training_editions e ON e.id=l.edition_id
        JOIN trainings t ON t.id=e.training_id JOIN media_assets a ON a.id=l.asset_id
        ORDER BY l.last_requested_at DESC, l.id DESC';
header('Cache-Control: private, no-store');
if (($_GET['format'] ?? '') === 'csv') {
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $statement = $pdo->query($sql);
    $output = fopen('php://output', 'wb');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="agenda-interest-' . date('Y-m-d') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Email', 'Programme', 'Edition', 'Agenda file', 'First request', 'Latest request'], ',', '"', '', "\r\n");
    while ($row = $statement->fetch(PDO::FETCH_NUM)) {
        $cells = array_map(static function ($value): string {
            $cell = (string) $value;
            // Prevent spreadsheet formula interpretation of user-entered text.
            return preg_match('/^(?:[\t\r\n]|[\p{Z}\s]*[=+@\-＝＋＠－])/u', $cell) ? "'" . $cell : $cell;
        }, $row);
        fputcsv($output, $cells, ',', '"', '', "\r\n");
    }
    fclose($output);
    exit;
}
$rows = $pdo->query($sql . ' LIMIT 500')->fetchAll();
$adminPageTitle = 'Agenda interest';
require __DIR__ . '/_header.php';
?>
<section class="admin-panel"><div class="panel-heading"><h2>Agenda downloads</h2><a href="/admin/agenda-interest?format=csv">Download CSV</a></div>
<p>Showing the latest 500 email and agenda combinations. The CSV includes all records. A download request is not marketing consent.</p>
<div class="admin-table-wrap"><table><thead><tr><th>Email</th><th>Programme / edition</th><th>File</th><th>First request</th><th>Latest request</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['email']) ?></td><td><?= e($row['title']) ?><small><?= e($row['slug']) ?></small></td><td><?= e($row['agenda_title']) ?></td><td><?= e($row['first_requested_at']) ?></td><td><?= e($row['last_requested_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$rows): ?><p>No requests yet.</p><?php endif; ?></section>
<?php require __DIR__ . '/_footer.php'; ?>
