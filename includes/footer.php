</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand brand-footer" href="<?= page_url() ?>"><img src="/assets/images/haleef-logo.webp" alt="Haleef" width="180" height="82"></a>
            <p>Your strategic ally in creating events that move business forward.</p>
        </div>
        <div>
            <span class="footer-label">Explore</span>
            <a href="<?= page_url('services') ?>">What we do</a>
            <a href="<?= page_url('training') ?>">Corporate training</a>
            <a href="<?= page_url('work') ?>">Our work</a>
            <a href="<?= page_url('about') ?>">About Haleef</a>
        </div>
        <div>
            <span class="footer-label">Connect</span>
            <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a>
            <a href="mailto:ask@haleeflearning.com">ask@haleeflearning.com</a>
            <a href="<?= LINKEDIN_URL ?>" target="_blank" rel="noopener">LinkedIn ↗</a>
            <span>United Arab Emirates · Hungary</span>
        </div>
        <div class="footer-action">
            <span class="eyebrow">Have something in mind?</span>
            <a class="footer-cta" href="<?= page_url('contact') ?>">Let’s build the room <span>↗</span></a>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>© <?= date('Y') ?> Haleef. All rights reserved.</span>
        <div><a href="<?= page_url('privacy') ?>">Privacy</a><a href="<?= page_url('cookies') ?>#cookie-preferences">Cookie settings</a><a href="<?= page_url('terms') ?>">Terms</a></div>
    </div>
</footer>
<?php require __DIR__ . '/whatsapp-widget.php'; ?>
<?php require __DIR__ . '/cookie-notice.php'; ?>
<script src="/assets/js/site.js"></script>
</body>
</html>
