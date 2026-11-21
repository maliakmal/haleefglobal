<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\AdminAuth;
use Haleef\Support\Csrf;
use Haleef\Support\Database;
use Haleef\Support\Flash;

$adminUser = AdminAuth::requireUser();
$pdo = Database::connection();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::verify($_POST['_csrf'] ?? null);
        $title = trim((string) ($_POST['title'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        if ($title === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new RuntimeException('A title and lowercase URL slug are required.');
        }
        $brand = $pdo->query("SELECT id FROM brands WHERE code='haleef-learning' LIMIT 1")->fetchColumn();
        $statement = $pdo->prepare('INSERT INTO trainings (brand_id, slug, title, short_description, description, learning_outcomes, audience, cpd_available, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$brand, $slug, $title, trim((string) ($_POST['short_description'] ?? '')) ?: null, trim((string) ($_POST['description'] ?? '')) ?: null, trim((string) ($_POST['learning_outcomes'] ?? '')) ?: null, trim((string) ($_POST['audience'] ?? '')) ?: null, isset($_POST['cpd_available']) ? 1 : 0, ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft']);
        Flash::set('success', 'Training created. You can now schedule an edition.');
        header('Location: /admin/trainings');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$trainings = $pdo->query("SELECT t.*, COUNT(e.id) AS editions_count FROM trainings t LEFT JOIN training_editions e ON e.training_id=t.id GROUP BY t.id ORDER BY t.created_at DESC")->fetchAll();
$adminPageTitle = 'Trainings';
require __DIR__ . '/_header.php';
?>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= e($error) ?></div><?php endif; ?>
<div class="admin-two-column"><section class="admin-panel"><div class="panel-heading"><div><span>Catalogue</span><h2>Training programmes</h2></div></div><?php if (!$trainings): ?><div class="admin-empty">Create the first Haleef Learning programme.</div><?php else: ?><div class="admin-table-wrap"><table><thead><tr><th>Title</th><th>Status</th><th>CPD</th><th>Editions</th></tr></thead><tbody><?php foreach ($trainings as $training): ?><tr><td><strong><?= e($training['title']) ?></strong><small>/<?= e($training['slug']) ?></small></td><td><span class="status status-<?= e($training['status']) ?>"><?= e($training['status']) ?></span></td><td><?= $training['cpd_available'] ? 'Yes' : '—' ?></td><td><?= (int) $training['editions_count'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section class="admin-panel admin-form-panel"><span>New programme</span><h2>Create training</h2><form method="post"><?= Csrf::field() ?><label>Title<input name="title" required></label><label>URL slug<input name="slug" required placeholder="eu-biosimilars-regulatory-requirements" pattern="[a-z0-9]+(?:-[a-z0-9]+)*"></label><label>Short description<textarea name="short_description" rows="3"></textarea></label><label>Programme overview<textarea name="description" rows="5"></textarea></label><label>Learning outcomes<textarea name="learning_outcomes" rows="4"></textarea></label><label>Who should attend<textarea name="audience" rows="4"></textarea></label><div class="admin-form-row"><label>Status<select name="status"><option value="draft">Draft</option><option value="published">Published</option></select></label><label class="check-label"><input type="checkbox" name="cpd_available" value="1"> CPD available</label></div><button type="submit">Create training</button></form></section></div>
<?php require __DIR__ . '/_footer.php'; ?>

