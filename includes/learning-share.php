<?php
declare(strict_types=1);

if (empty($shareUrl)) return;
$encodedUrl = rawurlencode($shareUrl);
$encodedText = rawurlencode($pageTitle . ' ' . $shareUrl);
$destinations = [
    'WhatsApp'=>'https://wa.me/?text=' . $encodedText,
    'LinkedIn'=>'https://www.linkedin.com/sharing/share-offsite/?url=' . $encodedUrl,
    'Facebook'=>'https://www.facebook.com/sharer/sharer.php?u=' . $encodedUrl,
    'Email'=>'mailto:?subject=' . rawurlencode($pageTitle) . '&body=' . $encodedUrl,
];
?>
<div class="learning-share" data-learning-share>
    <h2><?= e($shareHeading ?? 'Share this page') ?></h2>
    <div class="learning-share-actions">
        <?php foreach ($destinations as $name=>$url): ?>
            <a href="<?= e($url) ?>" <?= $name === 'Email' ? '' : 'target="_blank" rel="noopener noreferrer"' ?>><?= e($name) ?></a>
        <?php endforeach; ?>
        <button type="button" data-copy-link hidden>Copy link</button>
        <button type="button" data-native-share hidden>More options</button>
    </div>
    <input aria-label="Shareable URL" data-share-url value="<?= e($shareUrl) ?>" readonly>
    <span role="status" data-share-status></span>
</div>
<script src="/assets/js/learning-share.js" defer></script>
