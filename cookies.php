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
    <p>Haleef Global doesn’t use cookies for analytics or advertising on this website. There are no optional cookies to accept or reject.</p>
    <div class="cookie-details">
        <section><h3>Session cookie <code>haleef_session</code></h3>
            <p>This first-party cookie helps protect forms, keep staff signed in, and let you retrieve your invoice in the browser you booked from. It is a browser-session cookie. Browsing ordinary pages such as the homepage doesn’t start a session.</p></section>
        <section><h3>Cookie notice preference</h3>
            <p>When you dismiss the notice, your browser remembers that choice under <code>haleef_cookie_notice_v1</code>. The dismissal expires after 180 days. It isn’t sent to us or used to identify you.</p>
            <p><button class="button button-dark" type="button" data-cookie-reset hidden>Reset cookie notice</button></p>
            <p role="status" data-cookie-status></p>
        </section>
    </div>
    <h2>External links</h2>
    <p>Links to services such as LinkedIn, WhatsApp or Zoom take you to another service when you choose to use them. Those services have their own cookie and privacy policies.</p>
    <p>You can clear or block our cookies in your browser. Blocking the session cookie can stop forms, sign-in and invoice downloads from working.</p>
    <p>Read the <a href="/privacy">privacy policy</a> or email <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a> with questions.</p>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
