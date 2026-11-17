<?php
require __DIR__ . '/includes/config.php';
$service = service_by_slug($_GET['service'] ?? '');
if (!$service) {
    http_response_code(404);
    require __DIR__ . '/not-found.php';
    return;
}
$pageTitle = $service['title']; $pageDescription = $service['summary']; $currentPage = 'services';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero service-hero">
    <div class="service-hero-image"><img src="<?= e($service['image']) ?>" alt="<?= e($service['title']) ?> by Haleef"><span>Photo: <a href="<?= e($service['image_source']) ?>" target="_blank" rel="noopener"><?= e($service['image_credit']) ?></a></span></div>
    <div class="container service-hero-content"><span class="eyebrow"><?= e($service['number']) ?> · What we do</span><h1><?= e($service['title']) ?></h1><p><?= e($service['intro']) ?></p></div>
</section>
<section class="service-positioning section">
    <div class="container service-positioning-grid">
        <div><span class="eyebrow">Who it is for</span><h2><?= e($service['promise']) ?></h2></div>
        <div><p class="lead"><?= e($service['ideal_for']) ?></p><a class="button button-dark" href="<?= page_url('contact') ?>">Discuss your brief <span>→</span></a></div>
    </div>
</section>
<section class="service-capabilities section">
    <div class="container inner-grid">
        <div><span class="eyebrow">Capabilities</span><h2>Where we can<br>take ownership.</h2></div>
        <ul class="capability-list"><?php foreach ($service['items'] as $item): ?><li><span>↗</span><?= e($item) ?></li><?php endforeach; ?></ul>
    </div>
</section>
<section class="service-method section">
    <div class="container"><span class="eyebrow">How we approach it</span><div class="service-stage-grid">
        <?php foreach ($service['stages'] as $index => $stage): ?><article><b>0<?= $index + 1 ?></b><h3><?= e($stage[0]) ?></h3><p><?= e($stage[1]) ?></p></article><?php endforeach; ?>
    </div></div>
</section>
<section class="service-outcomes section">
    <div class="container service-outcomes-grid"><div><span class="eyebrow">What this creates</span><h2>Useful outcomes,<br>not activity for its<br>own sake.</h2></div><ol><?php foreach ($service['outcomes'] as $outcome): ?><li><?= e($outcome) ?></li><?php endforeach; ?></ol></div>
</section>
<?php require __DIR__ . '/includes/cta.php'; require __DIR__ . '/includes/footer.php'; ?>
