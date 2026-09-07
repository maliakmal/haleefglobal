<?php
require __DIR__ . '/includes/config.php';
$service = service_by_slug($_GET['service'] ?? '');
if (!$service) { http_response_code(404); $service = $services[0]; }
$pageTitle = $service['title']; $pageDescription = $service['summary']; $currentPage = 'services';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero service-hero"><div class="container"><span class="eyebrow"><?= e($service['number']) ?> · What we do</span><h1><?= e($service['title']) ?></h1><p><?= e($service['intro']) ?></p></div></section>
<section class="section"><div class="container inner-grid"><div><span class="eyebrow">Capabilities</span><h2>Focused support.<br>Clear ownership.</h2></div><div><ul class="capability-list"><?php foreach ($service['items'] as $item): ?><li><span>↗</span><?= e($item) ?></li><?php endforeach; ?></ul><p class="lead">Every engagement is shaped around your objectives, team and delivery context. We can lead the complete program or own a defined part of it.</p><a class="button button-dark" href="contact.php">Discuss your brief <span>→</span></a></div></div></section>
<?php require __DIR__ . '/includes/cta.php'; require __DIR__ . '/includes/footer.php'; ?>
