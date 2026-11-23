<?php
declare(strict_types=1);

require __DIR__ . '/includes/config.php';

use Haleef\Training\TrainingRepository;

$pageTitle = 'Upcoming Training Programmes';
$pageDescription = 'Explore upcoming Haleef Learning masterclasses, workshops and professional development programmes.';
$currentPage = 'training';
$databaseError = null;
$agendaOptions = [];
$agendaReturn = '/training/courses';
header('Cache-Control: private, no-store');

try {
    $repository = new TrainingRepository();
    $upcoming = $repository->upcoming();
    $past = $repository->past();
    $agendaOptions = (new \Haleef\Training\AgendaEmail(\Haleef\Support\Database::connection()))->eligible();
} catch (Throwable $exception) {
    $upcoming = $past = [];
    $databaseError = 'The programme calendar is being prepared. Please contact the Haleef team for upcoming dates.';
}

require __DIR__ . '/includes/header.php';
?>
<section class="learning-page-hero">
    <div class="container">
        <span class="eyebrow">Haleef Learning</span>
        <h1>Programmes built for<br><em>professional impact.</em></h1>
        <p>Live online and in-person learning led by experienced specialists, with practical outcomes and selected CPD opportunities.</p>
        <a class="button button-dark" href="/training/trainers">Meet our trainers</a>
    </div>
</section>

<section class="section course-calendar">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">Course calendar</span><h2>Upcoming programmes.</h2></div><p>Choose an edition to review the full programme, trainer and registration details.</p></div>
        <?php if ($databaseError): ?><div class="form-message error"><?= e($databaseError) ?></div><?php endif; ?>
        <?php if (!$upcoming && !$databaseError): ?><div class="empty-state"><h3>New dates are coming.</h3><p>Our next public programmes are being finalised.</p><a class="text-link" href="/contact?interest=corporate-training">Register your interest</a></div><?php endif; ?>
        <div class="course-card-grid">
            <?php foreach ($upcoming as $course): ?>
                <article class="course-card">
                    <div class="course-card-date"><strong><?= e(date('d', strtotime($course['starts_at']))) ?></strong><span><?= e(strtoupper(date('M Y', strtotime($course['starts_at'])))) ?></span></div>
                    <div class="course-card-copy">
                        <div class="course-meta"><span><?= e(str_replace('_', ' ', $course['delivery_mode'])) ?></span><span><?= e(\Haleef\Training\Editorial::timezoneLabel($course)) ?></span><?php if ($course['cpd_available']): ?><span>CPD</span><?php endif; ?></div>
                        <h3><?= e($course['title']) ?></h3>
                        <p><?= e((string) $course['short_description']) ?></p>
                    </div>
                    <div class="course-card-action"><span><?= e(\Haleef\Support\MoneyDisplay::format((float) $course['price'], $course['currency'])) ?></span><a class="button button-dark" href="/training/courses/<?= e($course['training_slug'] . '/' . $course['edition_slug']) ?>">View edition</a></div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($past): ?>
<section class="section past-courses"><div class="container"><span class="eyebrow">Previous editions</span><h2>Past programmes.</h2><div class="past-course-list"><?php foreach ($past as $course): ?><div><span><?= e(date('d M Y', strtotime($course['starts_at']))) ?></span><h3><?= e($course['title']) ?></h3><span><?= e(str_replace('_', ' ', $course['delivery_mode'])) ?></span></div><?php endforeach; ?></div></div></section>
<?php endif; ?>
<div class="learning-editorial"><?php require __DIR__ . '/includes/learning-agenda.php'; ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

