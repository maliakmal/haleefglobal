<?php
require __DIR__ . '/includes/config.php';
$pageTitle = 'Plan an Event'; $currentPage = 'contact';
$sent = isset($_GET['sent']); $error = isset($_GET['error']);
$interest = $_GET['interest'] ?? '';
header('Cache-Control: private, no-store');
require __DIR__ . '/includes/header.php';
?>
<section class="contact-page"><div class="contact-index">LET’S TALK · 04</div><div class="container contact-grid"><div class="contact-intro"><span class="eyebrow">Start a conversation</span><h1>Begin with the <em>brief.</em></h1><p>Tell us what you are planning, where you need support and what success should look like. We’ll come back with the right next conversation.</p><div class="contact-direct"><span>Prefer email?</span><a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a></div><div class="contact-location"><span>Based in</span><b>Dubai, UAE</b><small>Working across the Middle East & Africa</small></div></div><div class="form-card">
<?php if ($sent): ?><div class="form-message success">Thank you. Your brief has been received and the Haleef team will be in touch.</div><?php elseif ($error): ?><div class="form-message error">We couldn’t send your message. Please email us directly at <?= SITE_EMAIL ?>.</div><?php endif; ?>
<form action="/handlers/contact-submit.php" method="post">
<?= \Haleef\Support\Csrf::field() ?>
<div class="form-row"><label>Full name<input name="name" type="text" required></label><label>Work email<input name="email" type="email" required></label></div>
<label>Company or organisation<input name="company" type="text"></label>
<div class="form-row"><label>What are you planning?<select name="type" required><option value="">Choose an option</option><option>Full event management</option><option>Support for our internal team</option><option>Event consultation</option><option <?= $interest === 'corporate-training' ? 'selected' : '' ?>>Corporate training programme</option><option>Training or learning event</option><option>Technology consultation</option><option>Sponsorship or partnership</option><option>Not sure yet</option></select></label><label>Location<input name="location" type="text" placeholder="City / country"></label></div>
<label>Tell us about the brief<textarea name="message" rows="6" required placeholder="Objectives, expected audience, timing and where you need support..."></textarea></label>
<label class="honeypot" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
<button class="button button-dark" type="submit">Send your brief <span>→</span></button>
</form></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
