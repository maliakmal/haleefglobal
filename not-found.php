<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Page Not Found';
$currentPage = 'not-found';
require __DIR__ . '/includes/header.php';
?>
<section class="not-found-page"><div class="container not-found-grid"><div class="not-found-number">404</div><div><span class="eyebrow">Wrong room</span><h1>This event isn’t<br>on the agenda.</h1><p>The page may have moved or the address may be incorrect.</p><a class="button" href="<?= page_url() ?>">Return home <span>→</span></a></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
