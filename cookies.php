<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
$pageTitle = 'Cookie settings';
$currentPage = 'legal';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero legal-hero"><div class="container"><span class="eyebrow">Privacy</span><h1>Cookie settings</h1></div></section>
<section class="section legal-copy"><div class="container">
    <h2>What this site uses</h2>
    <?php if ($analyticsId !== ''): ?>
    <p>Analytics is optional and off unless you accept it. Your choice doesn’t affect forms, bookings, or access to this website. We don’t use advertising cookies.</p>
    <?php else: ?>
    <p>Haleef Global doesn’t use cookies for analytics or advertising on this website. There are no optional cookies to accept or reject.</p>
    <?php endif; ?>
    <div class="cookie-details">
        <section><h3>Session cookie <code>haleef_session</code></h3>
            <p>This first-party cookie helps protect forms, keep staff signed in, and let you retrieve your invoice in the browser you booked from. It is a browser-session cookie. Browsing ordinary pages such as the homepage doesn’t start a session.</p></section>
        <?php if ($analyticsId !== ''): ?>
        <section><h3>Optional analytics (Google Analytics 4)</h3>
            <p>If you accept, Google Analytics uses cookies such as <code>_ga</code> and <code>_ga_…</code> to measure visits and actions such as an agenda request, enquiry, or booking-link click. These cookies are configured to expire after 180 days. We don’t send form fields, booking details, or invoice details to Analytics.</p>
            <p>Google receives browser and device information as part of providing analytics. See <a href="https://policies.google.com/technologies/partner-sites" rel="noreferrer">how Google uses data from sites that use its services</a>.</p>
            <p>Your choice is stored locally in this browser for 180 days. Return here to change or withdraw it at any time.</p>
            <div class="cookie-notice-actions" data-cookie-controls hidden>
                <button class="button cookie-choice" type="button" data-cookie-choice="rejected">Reject or withdraw analytics</button>
                <button class="button cookie-choice" type="button" data-cookie-choice="accepted">Accept analytics</button>
            </div>
            <p role="status" data-cookie-status></p>
            <noscript>Analytics cannot run while JavaScript is off.</noscript>
        </section>
        <?php else: ?>
        <section><h3>Cookie notice preference</h3>
            <p>When you dismiss the notice, your browser remembers that choice under <code>haleef_cookie_notice_v1</code>. The dismissal expires after 180 days. It isn’t sent to us or used to identify you.</p>
            <p><button class="button button-dark" type="button" data-cookie-reset hidden>Reset cookie notice</button></p>
            <p role="status" data-cookie-status></p>
        </section>
        <?php endif; ?>
    </div>
    <h2>External links</h2>
    <p>Links to services such as LinkedIn, WhatsApp or Zoom take you to another service when you choose to use them. Those services have their own cookie and privacy policies.</p>
    <p>You can clear or block our cookies in your browser. Blocking the session cookie can stop forms, sign-in and invoice downloads from working.</p>
    <p>Read the <a href="/privacy">privacy policy</a> or email <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a> with questions.</p>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
