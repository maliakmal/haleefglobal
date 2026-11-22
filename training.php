<?php
require __DIR__ . '/includes/config.php';
$pageTitle = 'Corporate Training';
$pageDescription = 'Tailored in-house, virtual and bespoke corporate training programmes for teams across the UAE and MENA region.';
$currentPage = 'training';
$agendaReturn = '/training';
try {
    $agendaOptions = (new \Haleef\Training\AgendaEmail(\Haleef\Support\Database::connection()))->eligible();
} catch (Throwable $error) {
    error_log($error->getMessage());
    $agendaOptions = [];
}
header('Cache-Control: private, no-store');
require __DIR__ . '/includes/header.php';
?>
<section class="training-hero">
    <div class="training-hero-image"><img src="https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=2200&q=88" alt="Professionals taking part in a collaborative training session"></div>
    <div class="training-hero-shade"></div>
    <div class="container training-hero-inner">
        <span class="eyebrow">Haleef Corporate Training</span>
        <h1>Learning built for<br><em>the work ahead.</em></h1>
        <div class="training-hero-bottom">
            <p>Tailored programmes that connect expert knowledge, active participation and the real priorities of your organisation.</p>
            <div class="training-hero-actions"><a class="button" href="<?= page_url('training/courses') ?>">Explore programmes</a><a class="text-link text-link-light" href="<?= page_url('contact') ?>?interest=corporate-training">Build a bespoke programme</a></div>
        </div>
    </div>
    <div class="training-hero-note">Dubai · UAE · MENA</div>
</section>

<section class="training-intro section">
    <div class="container training-intro-grid">
        <div><span class="eyebrow">Designed around you</span><h2>Not training off the shelf.<br>Capability built to fit.</h2></div>
        <div><p class="lead">Every engagement starts with the people, the performance need and the business context. We then shape the right learning journey—from a focused leadership workshop to a multi-session development programme.</p><p>Haleef brings together programme design, subject-matter expertise and disciplined delivery so your team gets a relevant experience with a clear path from learning to application.</p></div>
    </div>
</section>

<section class="training-formats section">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">Flexible delivery</span><h2>The right format for<br>your people and pace.</h2></div><p>One format or a considered blend—designed around access, participation and the outcome you need.</p></div>
        <div class="training-format-grid">
            <article><span>01</span><h3>In-house training</h3><p>Private, facilitator-led programmes delivered at your workplace or a selected venue, with content shaped to your organisation.</p></article>
            <article><span>02</span><h3>Live virtual learning</h3><p>Interactive online sessions with discussion, exercises and facilitator feedback for distributed or remote teams.</p></article>
            <article><span>03</span><h3>Workshops & masterclasses</h3><p>Focused, high-participation sessions built around a defined challenge, skill or strategic priority.</p></article>
            <article><span>04</span><h3>Bespoke programmes</h3><p>Fully customised learning programmes designed around your organisation’s specific challenges, people and goals—combining relevant content, expert facilitation and practical application.</p></article>
        </div>
    </div>
</section>

<section class="training-focus section">
    <div class="container training-focus-grid">
        <div class="training-focus-copy"><span class="eyebrow">Programme possibilities</span><h2>Built across the capabilities that move organisations.</h2><p>We develop the brief and identify suitable specialist facilitators around the subject, seniority and sector context required.</p><a class="text-link" href="<?= page_url('contact') ?>?interest=corporate-training">Talk through your training need <span>→</span></a></div>
        <div class="training-topic-list">
            <div><span>01</span><h3>Leadership & people management</h3></div>
            <div><span>02</span><h3>Communication & influence</h3></div>
            <div><span>03</span><h3>Sales & customer experience</h3></div>
            <div><span>04</span><h3>Strategy & business performance</h3></div>
            <div><span>05</span><h3>Project & event management</h3></div>
            <div><span>06</span><h3>Digital, technology & AI awareness</h3></div>
        </div>
    </div>
</section>

<section class="training-process section">
    <div class="container">
        <span class="eyebrow">From need to impact</span>
        <h2>A clear four-step journey.</h2>
        <div class="training-process-grid">
            <article><b>01</b><h3>Diagnose</h3><p>Clarify the audience, current challenge, desired capability and evidence of success.</p></article>
            <article><b>02</b><h3>Design</h3><p>Shape the objectives, content, facilitator profile, delivery format and learning flow.</p></article>
            <article><b>03</b><h3>Deliver</h3><p>Coordinate the trainer, participants, materials, venue or platform and the full learning experience.</p></article>
            <article><b>04</b><h3>Embed</h3><p>Capture feedback and define practical follow-through that supports application beyond the session.</p></article>
        </div>
    </div>
</section>

<section class="training-cta">
    <div class="container training-cta-grid"><div><span class="eyebrow">Start with the challenge</span><h2>Tell us what your team needs to do better.</h2><p>We’ll help turn it into a focused learning brief and the right delivery plan.</p></div><a class="circle-link" href="<?= page_url('contact') ?>?interest=corporate-training"><span>Discuss your<br>programme</span><b>↗</b></a></div>
</section>
<div class="learning-editorial"><?php require __DIR__ . '/includes/learning-agenda.php'; ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
