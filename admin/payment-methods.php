<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\AdminAuth;
use Haleef\Support\Csrf;
use Haleef\Support\Database;
use Haleef\Support\Flash;

$adminUser = AdminAuth::requireUser();
$pdo = Database::connection();
$statement = $pdo->query("SELECT pm.* FROM payment_methods pm INNER JOIN brands b ON b.id=pm.brand_id WHERE b.code='haleef-learning' AND pm.code='bank_transfer' LIMIT 1");
$method = $statement->fetch();
if (!$method) { throw new RuntimeException('Bank transfer payment method is missing. Run the database migrations.'); }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::verify($_POST['_csrf'] ?? null);
        $config = ['account_name'=>trim((string)($_POST['account_name']??'')),'bank_name'=>trim((string)($_POST['bank_name']??'')),'iban'=>trim((string)($_POST['iban']??'')),'swift'=>trim((string)($_POST['swift']??'')),'payment_due_days'=>max(1,(int)($_POST['payment_due_days']??3)),'instructions'=>trim((string)($_POST['instructions']??''))];
        if (isset($_POST['enabled']) && ($config['account_name'] === '' || $config['bank_name'] === '' || $config['iban'] === '')) {
            throw new RuntimeException('Account name, bank name and IBAN are required before bank transfer can be enabled.');
        }
        $update = $pdo->prepare('UPDATE payment_methods SET enabled=?, label=?, config_json=? WHERE id=?');
        $update->execute([isset($_POST['enabled'])?1:0,trim((string)($_POST['label']??''))?:'Bank transfer',json_encode($config,JSON_THROW_ON_ERROR),(int)$method['id']]);
        Flash::set('success','Bank transfer settings updated.');
        header('Location: /admin/payment-methods'); exit;
    } catch(Throwable $exception) { $error=$exception->getMessage(); }
}
$config=json_decode((string)$method['config_json'],true)?:[];
$adminPageTitle='Payment methods';
require __DIR__.'/_header.php';
?>
<?php if($error): ?><div class="admin-alert admin-alert-error"><?= e($error) ?></div><?php endif; ?>
<section class="admin-panel admin-form-panel admin-narrow"><span>Payment adapter</span><h2>Bank transfer</h2><p>Online methods such as Stripe can be added later without changing orders or registrations.</p><form method="post"><?= Csrf::field() ?><label class="check-label"><input type="checkbox" name="enabled" value="1" <?= $method['enabled']?'checked':'' ?>> Accept bank-transfer registrations</label><label>Customer-facing label<input name="label" value="<?= e($method['label']) ?>" required></label><label>Account name<input name="account_name" value="<?= e((string)($config['account_name']??'')) ?>"></label><label>Bank name<input name="bank_name" value="<?= e((string)($config['bank_name']??'')) ?>"></label><label>IBAN<input name="iban" value="<?= e((string)($config['iban']??'')) ?>"></label><label>SWIFT / BIC<input name="swift" value="<?= e((string)($config['swift']??'')) ?>"></label><label>Payment due after registration (days)<input type="number" name="payment_due_days" min="1" max="30" value="<?= e((string)($config['payment_due_days']??3)) ?>"></label><label>Customer instructions<textarea name="instructions" rows="4"><?= e((string)($config['instructions']??'')) ?></textarea></label><button type="submit">Save payment settings</button></form></section>
<?php require __DIR__.'/_footer.php'; ?>

