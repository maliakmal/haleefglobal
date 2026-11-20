<?php
declare(strict_types=1);

require __DIR__ . '/includes/config.php';

use Haleef\Orders\OrderService;
use Haleef\Payments\PaymentMethodRegistry;
use Haleef\Support\Csrf;
use Haleef\Training\TrainingRepository;

$error = null;
try {
    $edition = (new TrainingRepository())->findOpenEdition((string) ($_GET['edition'] ?? ''));
    if (!$edition) {
        throw new RuntimeException('This training edition is not currently accepting registrations.');
    }
    $methods = (new PaymentMethodRegistry())->enabledForBrand((int) $edition['brand_id']);
    if (!$methods) {
        throw new RuntimeException('Registration payments are not configured yet. Please contact Haleef.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::verify($_POST['_csrf'] ?? null);
        if (!empty($_POST['website'] ?? '')) {
            throw new RuntimeException('Unable to submit this registration.');
        }
        $order = (new OrderService())->create($edition, $_POST);
        header('Location: /training/booking/' . $order['public_token']);
        exit;
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
    $edition = $edition ?? null;
    $methods = $methods ?? [];
}

if (!$edition) {
    http_response_code(404);
}
$pageTitle = $edition ? 'Register — ' . $edition['training_title'] : 'Training Registration';
$currentPage = 'training';
require __DIR__ . '/includes/header.php';
?>
<section class="registration-page"><div class="container registration-grid"><div class="registration-summary"><span class="eyebrow">Training registration</span><?php if ($edition): ?><h1><?= e($edition['training_title']) ?></h1><div class="registration-facts"><div><span>Dates</span><strong><?= e(date('d M Y', strtotime($edition['starts_at']))) ?> – <?= e(date('d M Y', strtotime($edition['ends_at']))) ?></strong></div><div><span>Format</span><strong><?= e(ucwords(str_replace('_', ' ', $edition['delivery_mode']))) ?></strong></div><div><span>Investment</span><strong><?= e($edition['currency']) ?> <?= e(number_format((float) $edition['price'], 2)) ?></strong></div></div><?php endif; ?></div>
<div class="form-card registration-form-card">
    <?php if ($error): ?><div class="form-message error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($edition && $methods): ?><form method="post" enctype="application/x-www-form-urlencoded"><?= Csrf::field() ?><h2>Attendee details</h2><div class="form-row"><label>Full name<input name="name" required value="<?= e((string) ($_POST['name'] ?? '')) ?>"></label><label>Work email<input name="email" type="email" required value="<?= e((string) ($_POST['email'] ?? '')) ?>"></label></div><div class="form-row"><label>Phone<input name="phone" value="<?= e((string) ($_POST['phone'] ?? '')) ?>"></label><label>Job title<input name="job_title" value="<?= e((string) ($_POST['job_title'] ?? '')) ?>"></label></div><label>Company<input name="company" value="<?= e((string) ($_POST['company'] ?? '')) ?>"></label><h2>Payment method</h2><div class="payment-options"><?php foreach ($methods as $index => $method): ?><label class="payment-option"><input type="radio" name="payment_method" value="<?= e($method['code']) ?>" <?= $index === 0 ? 'checked' : '' ?>><span><b><?= e($method['label']) ?></b><small><?= $method['adapter']->supportsOnlineCheckout() ? 'Continue to secure online payment.' : 'Reserve your place, then complete the transfer using the supplied instructions.' ?></small></span></label><?php endforeach; ?></div><label class="honeypot" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label><button class="button button-dark" type="submit">Create registration</button><p class="form-footnote">Your place is confirmed after payment has been received and verified.</p></form><?php endif; ?>
</div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>

