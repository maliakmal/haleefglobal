<?php
declare(strict_types=1);

require __DIR__ . '/includes/config.php';

use Haleef\Training\TrainingRepository;

try {
    $training = (new TrainingRepository())->findPublished((string) ($_GET['training'] ?? ''));
} catch (Throwable $exception) {
    $training = null;
}

if (!$training) {
    http_response_code(404);
    require __DIR__ . '/not-found.php';
    exit;
}

$pageTitle = $training['title'];
$pageDescription = $training['short_description'] ?: 'Haleef Learning professional training programme.';
$currentPage = 'training';
$openEditions = array_values(array_filter($training['editions'], static fn(array $edition): bool => in_array($edition['registration_status'], ['open', 'waitlist'], true) && strtotime($edition['ends_at']) >= time()));
require __DIR__ . '/includes/header.php';
?>
<section class="course-detail-hero"><div class="container course-detail-hero-grid"><div><span class="eyebrow">Haleef Learning programme</span><h1><?= e($training['title']) ?></h1><p><?= e((string) $training['short_description']) ?></p></div><div class="course-detail-badge"><span>Delivery</span><strong>Expert-led</strong><span><?= $training['cpd_available'] ? 'CPD opportunity' : 'Professional learning' ?></span></div></div></section>

<section class="section course-detail-body"><div class="container course-detail-grid"><div class="course-detail-main">
    <span class="eyebrow">Programme overview</span>
    <div class="rich-copy"><?= nl2br(e((string) $training['description'])) ?></div>
    <?php if ($training['learning_outcomes']): ?><h2>What you will gain</h2><div class="rich-copy"><?= nl2br(e((string) $training['learning_outcomes'])) ?></div><?php endif; ?>
    <?php if ($training['audience']): ?><h2>Who should attend</h2><div class="rich-copy"><?= nl2br(e((string) $training['audience'])) ?></div><?php endif; ?>
</div><aside class="edition-panel"><span class="eyebrow">Available editions</span>
    <?php if (!$openEditions): ?><p>Registration for the next edition has not opened yet.</p><a class="text-link" href="/contact?interest=corporate-training">Register your interest</a><?php endif; ?>
    <?php foreach ($openEditions as $edition): ?><article><div class="edition-date"><?= e(date('d–', strtotime($edition['starts_at'])) . date('d M Y', strtotime($edition['ends_at']))) ?></div><h3><?= e(ucwords(str_replace('_', ' ', $edition['delivery_mode']))) ?></h3><p><?= e($edition['timezone']) ?><?= $edition['venue_name'] ? ' · ' . e($edition['venue_name']) : '' ?></p><strong><?= e($edition['currency']) ?> <?= e(number_format((float) $edition['price'], 2)) ?></strong><a class="button button-dark" href="<?= $edition['registration_status'] === 'waitlist' ? '/contact?interest=corporate-training' : '/training/register/' . e($edition['slug']) ?>"><?= $edition['registration_status'] === 'waitlist' ? 'Join waitlist' : 'Register now' ?></a></article><?php endforeach; ?>
</aside></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>

