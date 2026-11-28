<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\{AdminAuth, Lists};
use Haleef\Orders\PromoCodes;
use Haleef\Support\{Csrf, Database, Flash};

$adminUser = AdminAuth::requireUser();
$pdo = Database::connection();
$trainingOptions = $pdo->query('SELECT id,title FROM trainings ORDER BY title')->fetchAll(PDO::FETCH_KEY_PAIR);
$id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: null;
$form = ['code'=>'', 'percentage'=>'', 'enabled'=>0, 'training_id'=>'', 'private_only'=>0,
    'starts_at'=>'', 'ends_at'=>'', 'max_uses'=>'', 'emails'=>''];
if ($id !== null) {
    $find = $pdo->prepare('SELECT * FROM promo_codes WHERE id=?');
    $find->execute([$id]);
    $existing = $find->fetch();
    if (!$existing) {
        http_response_code(404);
        require dirname(__DIR__) . '/not-found.php';
        exit;
    }
    $form = array_replace($form, $existing);
    foreach (['starts_at','ends_at'] as $field) {
        $form[$field] = str_replace(' ', 'T', substr((string)$form[$field], 0, 16));
    }
    $emails = $pdo->prepare('SELECT email FROM promo_code_emails WHERE promo_id=? ORDER BY email');
    $emails->execute([$id]);
    $form['emails'] = implode("\n", $emails->fetchAll(PDO::FETCH_COLUMN));
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::verify(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null);
        foreach (['percentage','training_id','starts_at','ends_at','max_uses','emails'] as $field) {
            $form[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
        }
        $form['enabled'] = isset($_POST['enabled']) ? 1 : 0;
        $form['private_only'] = isset($_POST['private_only']) ? 1 : 0;
        if ($id === null) $form['code'] = PromoCodes::normalize($_POST['code'] ?? '');
        if (!PromoCodes::validCode($form['code'])) throw new RuntimeException('Use 2–40 letters, digits, hyphens or underscores, starting with a letter or digit.');
        $percentage = filter_var($form['percentage'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>99]]);
        if ($percentage === false) throw new RuntimeException('Enter a discount from 1% to 99%.');
        $trainingId = $form['training_id'] === '' ? null : filter_var($form['training_id'], FILTER_VALIDATE_INT);
        if ($trainingId !== null && ($trainingId === false || !isset($trainingOptions[$trainingId]))) throw new RuntimeException('Choose a valid programme.');
        $max = $form['max_uses'] === '' ? null : filter_var($form['max_uses'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>1000000]]);
        if ($max === false) throw new RuntimeException('Enter a total limit from 1 to 1,000,000, or leave it blank.');
        $date = static function (string $value): ?string {
            if ($value === '') return null;
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
            if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $value) throw new RuntimeException('Enter a valid date and time.');
            return $parsed->format('Y-m-d H:i:s');
        };
        $starts = $date($form['starts_at']);
        $ends = $date($form['ends_at']);
        if ($starts !== null && $ends !== null && $ends <= $starts) throw new RuntimeException('The end time must be after the start time.');
        if (strlen($form['emails']) > 25000) throw new RuntimeException('The email list is too long.');
        $allowlist = [];
        foreach (preg_split('/[\s,;]+/', $form['emails'], -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $email = PromoCodes::contactEmail($value);
            if ($email === '') throw new RuntimeException('Check the email list. Separate addresses with commas or new lines.');
            $allowlist[$email] = true;
        }
        if ($form['private_only'] && !$allowlist) throw new RuntimeException('Add at least one email for a private code.');
        if (!$form['private_only'] && $allowlist) throw new RuntimeException('Check “Private code” to restrict use to the email list, or clear the list.');

        $pdo->beginTransaction();
        $values = [$percentage,$form['enabled'],$trainingId,$form['private_only'],$starts,$ends,$max];
        if ($id === null) {
            $pdo->prepare('INSERT INTO promo_codes (percentage,enabled,training_id,private_only,starts_at,ends_at,max_uses,code) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([...$values,$form['code']]);
            $savedId = (int)$pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE promo_codes SET percentage=?,enabled=?,training_id=?,private_only=?,starts_at=?,ends_at=?,max_uses=? WHERE id=?')
                ->execute([...$values,$id]);
            $savedId = $id;
            $pdo->prepare('DELETE FROM promo_code_emails WHERE promo_id=?')->execute([$id]);
        }
        $addEmail = $pdo->prepare('INSERT INTO promo_code_emails (promo_id,email) VALUES (?,?)');
        foreach (array_keys($allowlist) as $email) $addEmail->execute([$savedId,$email]);
        $pdo->commit();
        Flash::set('success','Promo code saved.');
        header('Location: /admin/promo-codes?edit=' . $savedId, true, 303);
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $exception instanceof PDOException
            ? (($exception->errorInfo[1] ?? null) === 1062 ? 'That promo code already exists.' : 'The promo code could not be saved.')
            : $exception->getMessage();
    }
}

$rows = $pdo->query("SELECT p.id,p.code,p.percentage,
    IF(p.enabled,'Enabled','Disabled') AS state,COALESCE(t.title,'All programmes') AS programme,
    IF(p.private_only,'Private','Public') AS audience,p.max_uses,
    (SELECT COUNT(*) FROM promo_redemptions r JOIN orders o ON o.id=r.order_id WHERE r.promo_id=p.id AND o.status <> 'cancelled') AS uses
    FROM promo_codes p LEFT JOIN trainings t ON t.id=p.training_id ORDER BY p.id DESC")->fetchAll();
$columns = ['code'=>'Code','percentage'=>'Discount (%)','state'=>'Setting','programme'=>'Programme','audience'=>'Access',
    'uses'=>'Reserved or used','max_uses'=>'Total limit'];
$rows = Lists::searchRows($rows,array_keys($columns));
if (Lists::exporting()) Lists::csv('promo-codes',array_values($columns),
    array_map(static fn($row)=>array_map(static fn($key)=>$row[$key] ?? '',array_keys($columns)),$rows));
$adminPageTitle = 'Promo codes';
require __DIR__ . '/_header.php';
?>
<?php if ($error): ?><div class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="admin-two-column">
<section class="admin-panel">
    <div class="panel-heading"><h2>All codes</h2><a href="/admin/promo-codes">New promo code</a></div>
    <form class="admin-list-filter" method="get"><label>Search codes<input type="search" name="q" value="<?= e(Lists::query()) ?>"></label>
        <button type="submit">Filter</button><a href="/admin/promo-codes?<?= e(http_build_query(['format'=>'csv','q'=>Lists::query()])) ?>">Download CSV</a>
    </form>
    <?php if (!$rows): ?><p>No matching codes.</p><?php endif; ?>
    <?php foreach ($rows as $row): ?>
    <div class="attendee-row"><div><strong><a href="/admin/promo-codes?edit=<?= (int)$row['id'] ?>"><?= e($row['code']) ?></a> · <?= (int)$row['percentage'] ?>% off</strong>
        <span><?= e($row['programme']) ?> · <?= e($row['audience']) ?> · <?= e($row['state']) ?></span></div>
        <div><?= (int)$row['uses'] ?> reserved or used<?= $row['max_uses'] === null ? '' : ' / ' . (int)$row['max_uses'] ?></div></div>
    <?php endforeach; ?>
</section>
<section class="admin-panel admin-form-panel">
    <h2><?= $id ? 'Edit promo code' : 'New promo code' ?></h2>
    <p>One use per booking contact email. The discount applies to all delegates on the booking.</p>
    <form method="post" action="/admin/promo-codes<?= $id ? '?edit=' . (int)$id : '' ?>">
        <?= Csrf::field() ?>
        <label>Code<input name="code" maxlength="40" value="<?= e($form['code']) ?>" <?= $id ? 'readonly' : 'required' ?>></label>
        <label>Discount (%)<input type="number" name="percentage" min="1" max="99" value="<?= e((string)$form['percentage']) ?>" required></label>
        <label>Programme<select name="training_id"><option value="">All programmes</option>
            <?php foreach ($trainingOptions as $key=>$title): ?><option value="<?= (int)$key ?>" <?= (string)$form['training_id'] === (string)$key ? 'selected' : '' ?>><?= e($title) ?></option><?php endforeach; ?>
        </select></label>
        <label>Total uses across all contacts (optional)<input type="number" name="max_uses" min="1" max="1000000" value="<?= e((string)$form['max_uses']) ?>"></label>
        <fieldset><legend>Schedule (<?= e(date_default_timezone_get()) ?>)</legend>
            <label>Starts at (optional)<input type="datetime-local" name="starts_at" value="<?= e($form['starts_at']) ?>"></label>
            <label>Ends at (optional)<input type="datetime-local" name="ends_at" value="<?= e($form['ends_at']) ?>"></label>
        </fieldset>
        <label class="check-label"><input type="checkbox" name="private_only" value="1" <?= $form['private_only'] ? 'checked' : '' ?>> Private code</label>
        <label>Allowed booking contact emails<textarea name="emails" rows="4" maxlength="25000" placeholder="One per line or comma-separated"><?= e($form['emails']) ?></textarea></label>
        <label class="check-label"><input type="checkbox" name="enabled" value="1" <?= $form['enabled'] ? 'checked' : '' ?>> Enable this code</label>
        <button type="submit">Save promo code</button>
    </form>
</section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
