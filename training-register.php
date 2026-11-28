<?php
declare(strict_types=1);

require __DIR__ . '/includes/config.php';

use Haleef\Orders\{DelegatePhone, Delegates, Invoices, OrderService, PromoCodes};
use Haleef\Payments\PaymentMethodRegistry;
use Haleef\Support\WebSession;
use Haleef\Support\{Csrf, Database, MoneyDisplay};
use Haleef\Training\{Editorial, TrainingRepository};

$error = null;
$edition = null;
$methods = [];
$posted = is_array($_POST['delegates'] ?? null) ? array_slice(array_values($_POST['delegates']),0,Delegates::LIMIT) : [];
$selectedMethod = is_string($_POST['payment_method'] ?? null) ? $_POST['payment_method'] : '';
$promoCode = substr(PromoCodes::normalize($_POST['promo_code'] ?? ''), 0, 80);
$promo = null;
$canQuote = false;
try {
    $edition = (new TrainingRepository())->findOpenEdition((string) ($_GET['edition'] ?? ''));
    if (!$edition) throw new RuntimeException('This training edition is not currently accepting registrations.');
    $methods = (new PaymentMethodRegistry())->enabledForBrand((int) $edition['brand_id']);
    if (!$methods) throw new RuntimeException('Registration payments are not configured yet. Please contact Haleef.');
    WebSession::start();
    header('Cache-Control: private, no-store');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::verify(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null);
        if (!empty($_POST['website'])) throw new RuntimeException('Unable to submit this registration.');
        if (isset($_POST['add_delegate'])) {
            if (count($posted) < Delegates::LIMIT) $posted[] = [];
        } elseif (isset($_POST['remove_delegate'])) {
            $remove = filter_var($_POST['remove_delegate'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($remove !== false && isset($posted[$remove])) array_splice($posted,$remove,1);
        } elseif (!isset($_POST['apply_promo'])) {
            $canQuote = true;
            $order = (new OrderService())->create($edition, $_POST);
            if (!empty($order['has_invoice'])) Invoices::grant($order);
            header('Location: /training/booking/' . $order['public_token']);
            exit;
        }
        $canQuote = isset($_POST['apply_promo']) || !empty($_POST['promo_rate']);
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
$subtotalCents = $unitCents * $quantity;
if ($edition && $canQuote && $promoCode !== '') {
    try {
        $promo = PromoCodes::quote(Database::connection(), $promoCode, PromoCodes::contactEmail($rows[0]['email'] ?? ''),
            (int)$edition['training_id'], $subtotalCents);
    } catch (Throwable $exception) {
        $error ??= $exception instanceof PDOException ? 'The promo code could not be checked right now.' : $exception->getMessage();
    }
}
$pageTitle = $edition ? 'Delegate registration — ' . $edition['training_title'] : 'Delegate registration';
$currentPage = 'training';
$renderDelegate = static function (int|string $index, mixed $row): void {
    $first = $index === 0;
    $number = is_int($index) ? (string)($index + 1) : '';
    $regions = DelegatePhone::regions();
    $region = is_string($row['phone_region'] ?? null) && isset($regions[$row['phone_region']]) ? $row['phone_region'] : DelegatePhone::DEFAULT_REGION;
?>
<fieldset class="delegate-card" data-delegate>
    <legend>Delegate <span data-delegate-number><?= e($number) ?></span><?= $first ? ' · Booking contact' : '' ?></legend>
    <?php if (!$first): ?><button class="delegate-remove" type="button" data-remove-delegate hidden>Remove <span class="sr-only">delegate <?= e($number) ?></span></button><noscript><button class="delegate-remove" type="submit" name="remove_delegate" value="<?= e((string)$index) ?>" formnovalidate>Remove delegate <?= e($number) ?></button></noscript><?php endif; ?>
    <div class="delegate-fields">
        <?php foreach (Delegates::FIELDS as $key=>[$label,$type,$autocomplete,$limit]): ?>
        <?php if ($key === 'phone'): ?>
        <div class="delegate-phone">
            <label>Country code
                <select name="delegates[<?= e((string)$index) ?>][phone_region]" required>
                    <?php foreach ($regions as $iso=>[$country,$code]): ?>
                    <option value="<?= e($iso) ?>" <?= $region === $iso ? 'selected' : '' ?>><?= e($country . ' (+' . $code . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Phone number
                <input name="delegates[<?= e((string)$index) ?>][phone]" type="tel" inputmode="tel" autocomplete="section-delegate<?= e((string)$index) ?> tel-national" maxlength="80" required value="<?= e(is_string($row['phone'] ?? null) ? $row['phone'] : '') ?>">
            </label>
        </div>
        <?php continue; endif; ?>
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
                <span><?= e(Editorial::timezoneLabel($edition)) ?></span>
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
                <?php if (Invoices::enabled() && in_array('bank_transfer', array_column($methods, 'code'), true)): ?>
                <fieldset class="delegate-card"><legend>Billing details</legend><div class="delegate-fields">
                    <label>Billing address (optional)<textarea name="billing_address" rows="3" maxlength="500" autocomplete="billing street-address"><?= e(is_string($_POST['billing_address'] ?? null) ? $_POST['billing_address'] : '') ?></textarea></label>
                </div><p>The bank transfer invoice uses the first delegate’s company and contact details.</p></fieldset>
                <?php endif; ?>
                <h2>Payment method</h2>
                <div class="payment-options"><?php foreach ($methods as $index=>$method): ?>
                    <label class="payment-option"><input type="radio" name="payment_method" value="<?= e($method['code']) ?>" <?= ($selectedMethod !== '' ? $method['code'] === $selectedMethod : $index === 0) ? 'checked' : '' ?> required><span><b><?= e($method['label']) ?></b><small><?= $method['adapter']->supportsOnlineCheckout() ? 'Continue to secure online payment.' : 'Reserve the seats, then follow the bank-transfer instructions.' ?></small></span></label>
                <?php endforeach; ?></div>
                <label class="honeypot" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <aside class="delegate-totals" aria-label="Booking summary">
                <span class="eyebrow">Your booking</span><h2>Registration summary</h2>
                <div class="delegate-fields">
                    <label>Promo code (optional)<input name="promo_code" maxlength="40" autocomplete="off" value="<?= e($promoCode) ?>"></label>
                </div>
                <button class="delegate-add" type="submit" name="apply_promo" value="1" formnovalidate>Apply promo code</button>
                <input type="hidden" name="promo_rate" value="<?= e((string)($promo['percentage'] ?? '')) ?>">
                <input type="hidden" name="promo_unit" value="<?= $unitCents ?>">
                <p data-promo-notice role="status"><?= $promo ? 'Promo applied to the whole booking.' : 'Use the booking contact’s email when applying a code.' ?></p>
                <dl><div><dt>Price per delegate</dt><dd><?= e(MoneyDisplay::format($unitCents / 100,$currency)) ?></dd></div>
                    <div><dt>Delegates</dt><dd data-delegate-count><?= $quantity ?></dd></div>
                    <div><dt>Subtotal</dt><dd data-delegate-subtotal><?= e(MoneyDisplay::format($subtotalCents / 100,$currency)) ?></dd></div>
                    <div data-promo-line<?= $promo ? '' : ' hidden' ?>><dt>Promo discount</dt><dd data-promo-discount><?= $promo ? '−' . e(MoneyDisplay::format($promo['discount'] / 100,$currency)) : '' ?></dd></div>
                    <div class="delegate-total-line"><dt>Total</dt><dd data-delegate-total aria-live="polite"><?= e(MoneyDisplay::format(($subtotalCents - ($promo['discount'] ?? 0)) / 100,$currency)) ?></dd></div>
                </dl>
                <button class="button button-dark delegate-submit" type="submit">Continue to payment</button>
                <p>Seats are confirmed once payment is received and verified.</p>
            </aside>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php if ($edition && $methods): ?><script src="/assets/js/delegate-registration.js?v=3" defer></script><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
