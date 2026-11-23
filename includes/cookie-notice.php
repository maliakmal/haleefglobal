<aside class="cookie-notice" aria-labelledby="cookie-notice-title" data-cookie-notice
    data-analytics-id="<?= e($analyticsId ?? '') ?>" data-analytics-path="<?= e($analyticsPath ?? '') ?>" hidden>
    <?php if (!empty($analyticsId)): ?>
    <h2 id="cookie-notice-title">Your cookie choices</h2>
    <p>Essential cookies keep forms and bookings working. With your permission, Google Analytics helps us see which pages people use and which actions they take. We don’t use advertising cookies.</p>
    <div class="cookie-notice-actions">
        <button class="button cookie-choice" type="button" data-cookie-choice="rejected">Reject analytics</button>
        <button class="button cookie-choice" type="button" data-cookie-choice="accepted">Accept analytics</button>
        <a class="cookie-more" href="/cookies">Cookie settings</a>
    </div>
    <?php else: ?>
    <h2 id="cookie-notice-title">Essential cookies only</h2>
    <p>We use essential cookies for things like secure forms and bookings. We don’t use analytics or advertising cookies.</p>
    <div class="cookie-notice-actions">
        <button class="button button-dark" type="button" data-cookie-dismiss>Got it</button>
        <a class="cookie-more" href="/cookies">Cookie settings</a>
    </div>
    <?php endif; ?>
</aside>
<script src="/assets/js/cookies.js?v=2" defer></script>
