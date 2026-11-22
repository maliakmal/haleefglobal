<?php
declare(strict_types=1);

if (empty($shareUrl)) return;
require_once __DIR__ . '/learning-icons.php';
$encodedUrl = rawurlencode($shareUrl);
$encodedText = rawurlencode($pageTitle . ' ' . $shareUrl);
$destinations = [
    'whatsapp'=>['WhatsApp','https://wa.me/?text=' . $encodedText],
    'linkedin'=>['LinkedIn','https://www.linkedin.com/sharing/share-offsite/?url=' . $encodedUrl],
    'facebook'=>['Facebook','https://www.facebook.com/sharer/sharer.php?u=' . $encodedUrl],
    'email'=>['Email','mailto:?subject=' . rawurlencode($pageTitle) . '&body=' . $encodedUrl],
];
?>
<section class="learning-share" data-learning-share aria-labelledby="learning-share-heading">
    <div class="learning-share-heading"><span class="learning-share-mark"><?= learning_icon('share') ?></span><h2 id="learning-share-heading"><?= e($shareHeading ?? 'Share this page') ?></h2></div>
    <div class="learning-share-actions">
        <?php foreach ($destinations as $icon=>[$name,$url]): ?>
            <a class="learn-share-<?= e($icon) ?>" href="<?= e($url) ?>" <?= $icon === 'email' ? '' : 'target="_blank" rel="noopener noreferrer"' ?>><?= learning_icon($icon) ?><span><?= e($name) ?></span></a>
        <?php endforeach; ?>
        <button type="button" data-copy-link hidden><?= learning_icon('copy') ?><span>Copy link</span></button>
        <button type="button" data-native-share hidden><?= learning_icon('share') ?><span>More options</span></button>
    </div>
    <input aria-label="Shareable URL" data-share-url value="<?= e($shareUrl) ?>" readonly>
    <span role="status" data-share-status></span>
</section>
<script src="/assets/js/learning-share.js" defer></script>
