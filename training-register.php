<?php
declare(strict_types=1);

require __DIR__ . '/includes/config.php';

use Haleef\Orders\{Delegates, OrderService};
use Haleef\Payments\PaymentMethodRegistry;
use Haleef\Support\{Csrf, MoneyDisplay};
use Haleef\Training\{Editorial, TrainingRepository};

header('Cache-Control: private, no-store');
$error = null;
$edition = null;
$methods = [];
$posted = is_array($_POST['delegates'] ?? null) ? array_slice(array_values($_POST['delegates']),0,Delegates::LIMIT) : [];
$selectedMethod = is_string($_POST['payment_method'] ?? null) ? $_POST['payment_method'] : '';
try {
    $edition = (new TrainingRepository())->findOpenEdition((string) ($_GET['edition'] ?? ''));
    if (!$edition) throw new RuntimeException('This training edition is not currently accepting registrations.');
    $methods = (new PaymentMethodRegistry())->enabledForBrand((int) $edition['brand_id']);
    if (!$methods) throw new RuntimeException('Registration payments are not configured yet. Please contact Haleef.');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::verify(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null);
        if (!empty($_POST['website'])) throw new RuntimeException('Unable to submit this registration.');
        if (isset($_POST['add_delegate'])) {
            if (count($posted) < Delegates::LIMIT) $posted[] = [];
        } elseif (isset($_POST['remove_delegate'])) {
            $remove = filter_var($_POST['remove_delegate'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($remove !== false && isset($posted[$remove])) array_splice($posted,$remove,1);
        } else {
            $order = (new OrderService())->create($edition, $_POST);
            header('Location: /training/booking/' . $order['public_token']);
            exit;
        }
    }
} catch (Throwable $exception) {
    if ($exception instanceof PDOException) error_log($exception->getMessage());
    $error = $exception instanceof PDOException ? 'The booking could not be saved. Please try again.' : $exception->getMessage();
}
if (!$edition) http_response_code(404);
$rows = $posted ?: [[]];
$quantity = count($rows);
$currency = $edition['currency'] ?? '';
$unitCents = Delegates::cents((string)($edition['price'] ?? '0'));
$pageTitle = $edition ? 'Delegate registration — ' . $edition['training_title'] : 'Delegate registration';
$currentPage = 'training';
$renderDelegate = static function (int|string $index, mixed $row): void {
    $first = $index === 0;
    $number = is_int($index) ? (string)($index + 1) : '';
?>
<fieldset class="delegate-card" data-delegate>
    <legend>Delegate <span data-delegate-number><?= e($number) ?></span><?= $first ? ' · Booking contact' : '' ?></legend>
    <?php if (!$first): ?><button class="delegate-remove" type="button" data-remove-delegate hidden>Remove <span class="sr-only">delegate <?= e($number) ?></span></button><noscript><button class="delegate-remove" type="submit" name="remove_delegate" value="<?= e((string)$index) ?>" formnovalidate>Remove delegate <?= e($number) ?></button></noscript><?php endif; ?>
    <div class="delegate-fields">
        <?php foreach (Delegates::FIELDS as $key=>[$label,$type,$autocomplete,$limit]): ?>
        <label><?= e($label) ?><input name="delegates[<?= e((string)$index) ?>][<?= e($key) ?>]" type="<?= e($type) ?>" autocomplete="section-delegate<?= e((string)$index) ?> <?= e($autocomplete) ?>" maxlength="<?= $limit ?>" required value="<?= e(is_string($row[$key] ?? null) ? $row[$key] : '') ?>"></label>
        <?php endforeach; ?>
    </div>
</fieldset>
<?php
};
require __DIR__ . '/includes/header.php';
?>
<section class="delegate-registration">
    <header class="delegate-hero">
        <div class="container">
            <a class="delegate-back" href="<?= $edition ? '/training/courses/' . e($edition['training_slug'] . '/' . $edition['slug']) : '/training/courses' ?>"><?= $edition ? 'Back to the course' : 'Browse courses' ?></a>
            <span class="eyebrow">Delegate Registration</span>
            <h1><?= e($edition['training_title'] ?? 'Registration unavailable') ?></h1>
            <?php if ($edition): ?><div class="delegate-hero-facts">
                <span><?= e(Editorial::date($edition,'starts_at')->format('d M Y') . ' – ' . Editorial::date($edition,'ends_at')->format('d M Y')) ?></span>
                <span><?= e(ucwords(str_replace('_',' ',$edition['delivery_mode']))) ?></span>
                <span><?= e(MoneyDisplay::format($unitCents / 100,$currency)) ?> per delegate</span>
            </div><?php endif; ?>
        </div>
    </header>
    <div class="container delegate-layout">
        <?php if ($error): ?><div class="form-message error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($edition && $methods): ?>
        <form class="delegate-form" method="post" data-delegate-form data-limit="<?= Delegates::LIMIT ?>" data-unit-cents="<?= $unitCents ?>" data-currency-pattern="<?= e(MoneyDisplay::format(0,$currency)) ?>">
            <?= Csrf::field() ?>
            <button type="submit" hidden>Continue to payment</button>
            <div class="delegate-main form-card">
                <h2>Delegate details</h2>
                <p class="delegate-intro">Register yourself or your team in one booking. The first delegate is the booking contact.</p>
                <div data-delegate-list><?php foreach ($rows as $i=>$row) $renderDelegate($i,$row); ?></div>
                <button class="delegate-add" type="button" data-add-delegate hidden><span aria-hidden="true">+</span> Add another delegate</button>
                <noscript><button class="delegate-add" type="submit" name="add_delegate" value="1" formnovalidate <?= $quantity >= Delegates::LIMIT ? 'disabled' : '' ?>>Add another delegate</button></noscript>
                <template data-delegate-template><?php $renderDelegate('__INDEX__',[]); ?></template>
                <p class="delegate-limit" data-delegate-status role="status"></p>
                <h2>Payment method</h2>
                <div class="payment-options"><?php foreach ($methods as $index=>$method): ?>
                    <label class="payment-option"><input type="radio" name="payment_method" value="<?= e($method['code']) ?>" <?= ($selectedMethod !== '' ? $method['code'] === $selectedMethod : $index === 0) ? 'checked' : '' ?> required><span><b><?= e($method['label']) ?></b><small><?= $method['adapter']->supportsOnlineCheckout() ? 'Continue to secure online payment.' : 'Reserve the seats, then follow the bank-transfer instructions.' ?></small></span></label>
                <?php endforeach; ?></div>
                <label class="honeypot" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <aside class="delegate-totals" aria-label="Booking summary">
                <span class="eyebrow">Your booking</span><h2>Registration summary</h2>
                <dl><div><dt>Price per delegate</dt><dd><?= e(MoneyDisplay::format($unitCents / 100,$currency)) ?></dd></div>
                    <div><dt>Delegates</dt><dd data-delegate-count><?= $quantity ?></dd></div>
                    <div class="delegate-total-line"><dt>Total</dt><dd data-delegate-total aria-live="polite"><?= e(MoneyDisplay::format($unitCents * $quantity / 100,$currency)) ?></dd></div>
                </dl>
                <button class="button button-dark delegate-submit" type="submit">Continue to payment</button>
                <p>Seats are confirmed once payment is received and verified.</p>
            </aside>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php if ($edition && $methods): ?><script src="/assets/js/delegate-registration.js" defer></script><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
