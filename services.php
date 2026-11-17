<?php
require __DIR__ . '/includes/config.php';
$pageTitle = 'What We Do'; $currentPage = 'services';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="container"><span class="eyebrow">What we do</span><h1>Whatever the brief,<br>we build around the <em>outcome.</em></h1><p>End-to-end management, collaborative support and specialist expertise for purposeful corporate and learning experiences.</p></div></section>
<section class="section"><div class="container service-detail-list">
<?php foreach ($services as $service): ?>
<article class="service-detail" id="<?= e($service['slug']) ?>">
    <span><?= e($service['number']) ?></span><div><h2><?= e($service['title']) ?></h2><p class="lead"><?= e($service['intro']) ?></p></div>
    <ul><?php foreach ($service['items'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
    <a class="circle-arrow" href="<?= service_url($service['slug']) ?>" aria-label="Learn about <?= e($service['title']) ?>">↗</a>
</article>
<?php endforeach; ?>
</div></section>
<?php require __DIR__ . '/includes/cta.php'; require __DIR__ . '/includes/footer.php'; ?>
