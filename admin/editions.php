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
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $starts = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) ($_POST['starts_at'] ?? ''));
        $ends = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) ($_POST['ends_at'] ?? ''));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || !$starts || !$ends || $ends < $starts) {
            throw new RuntimeException('A valid slug and date range are required.');
        }
        $trainingId = (int) ($_POST['training_id'] ?? 0);
        $price = (float) ($_POST['price'] ?? -1);
        $currency = strtoupper(trim((string) ($_POST['currency'] ?? '')));
        if ($trainingId < 1 || $price < 0 || !preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new RuntimeException('Choose a training and enter a valid non-negative price and three-letter currency.');
        }
        $statement = $pdo->prepare('INSERT INTO training_editions (training_id, slug, delivery_mode, timezone, starts_at, ends_at, venue_name, venue_address, capacity, price, currency, cpd_points, registration_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $deliveryMode = in_array(($_POST['delivery_mode'] ?? ''), ['online','in_person','hybrid'], true) ? $_POST['delivery_mode'] : 'online';
        $registrationStatus = in_array(($_POST['registration_status'] ?? ''), ['closed','open','waitlist'], true) ? $_POST['registration_status'] : 'closed';
        $statement->execute([$trainingId, $slug, $deliveryMode, trim((string) ($_POST['timezone'] ?? '')) ?: 'Asia/Dubai', $starts->format('Y-m-d H:i:s'), $ends->format('Y-m-d H:i:s'), trim((string) ($_POST['venue_name'] ?? '')) ?: null, trim((string) ($_POST['venue_address'] ?? '')) ?: null, ($_POST['capacity'] ?? '') !== '' ? (int) $_POST['capacity'] : null, $price, $currency, ($_POST['cpd_points'] ?? '') !== '' ? (float) $_POST['cpd_points'] : null, $registrationStatus]);
        Flash::set('success', 'Training edition scheduled.');
        header('Location: /admin/editions');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$trainings = $pdo->query("SELECT id,title FROM trainings ORDER BY title")->fetchAll();
$editions = $pdo->query("SELECT e.*,t.title FROM training_editions e INNER JOIN trainings t ON t.id=e.training_id ORDER BY e.starts_at DESC")->fetchAll();
$adminPageTitle = 'Editions';
require __DIR__ . '/_header.php';
?>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= e($error) ?></div><?php endif; ?>
<div class="admin-two-column"><section class="admin-panel"><div class="panel-heading"><div><span>Schedule</span><h2>Training editions</h2></div></div><?php if (!$editions): ?><div class="admin-empty">No editions scheduled.</div><?php else: ?><div class="admin-table-wrap"><table><thead><tr><th>Training</th><th>Dates</th><th>Format</th><th>Price</th><th>Registration</th></tr></thead><tbody><?php foreach ($editions as $edition): ?><tr><td><strong><?= e($edition['title']) ?></strong><small><?= e($edition['slug']) ?></small></td><td><?= e(date('d M Y', strtotime($edition['starts_at']))) ?> – <?= e(date('d M Y', strtotime($edition['ends_at']))) ?></td><td><?= e(str_replace('_',' ',$edition['delivery_mode'])) ?></td><td><?= e($edition['currency']) ?> <?= e(number_format((float) $edition['price'],2)) ?></td><td><span class="status status-<?= e($edition['registration_status']) ?>"><?= e($edition['registration_status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section class="admin-panel admin-form-panel"><span>New edition</span><h2>Schedule training</h2><?php if (!$trainings): ?><p>Create a training programme first.</p><?php else: ?><form method="post"><?= Csrf::field() ?><label>Training<select name="training_id" required><?php foreach ($trainings as $training): ?><option value="<?= (int) $training['id'] ?>"><?= e($training['title']) ?></option><?php endforeach; ?></select></label><label>Edition slug<input name="slug" required placeholder="biosimilars-february-2027"></label><div class="admin-form-row"><label>Starts<input type="datetime-local" name="starts_at" required></label><label>Ends<input type="datetime-local" name="ends_at" required></label></div><div class="admin-form-row"><label>Delivery<select name="delivery_mode"><option value="online">Online</option><option value="in_person">In person</option><option value="hybrid">Hybrid</option></select></label><label>Timezone<input name="timezone" value="Asia/Dubai" required></label></div><label>Venue name<input name="venue_name"></label><label>Venue address<textarea name="venue_address" rows="2"></textarea></label><div class="admin-form-row"><label>Price<input type="number" name="price" step="0.01" min="0" value="0" required></label><label>Currency<input name="currency" maxlength="3" value="AED" required></label></div><div class="admin-form-row"><label>Capacity<input type="number" name="capacity" min="1"></label><label>CPD points<input type="number" name="cpd_points" step="0.5" min="0"></label></div><label>Registration<select name="registration_status"><option value="closed">Closed</option><option value="open">Open</option><option value="waitlist">Waitlist</option></select></label><button type="submit">Schedule edition</button></form><?php endif; ?></section></div>
<?php require __DIR__ . '/_footer.php'; ?>

