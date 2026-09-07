<?php
require __DIR__ . '/includes/config.php';
$pageTitle = 'Events Built With Intent';
$currentPage = 'home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-glow"></div>
    <div class="container hero-inner">
        <div class="hero-kicker"><span></span> Dubai · Middle East · Africa</div>
        <h1>We build rooms<br>where business<br><em>moves forward.</em></h1>
        <div class="hero-bottom">
            <p>Haleef conceives, manages and delivers corporate events, conferences, awards and learning experiences built around real outcomes.</p>
            <div class="hero-actions">
                <a class="button" href="contact.php">Plan your event <span>↗</span></a>
                <a class="text-link" href="work.php">Explore our work <span>→</span></a>
            </div>
        </div>
    </div>
    <div class="marquee" aria-hidden="true"><div>CONFERENCES <i>◆</i> AWARDS <i>◆</i> EXECUTIVE GATHERINGS <i>◆</i> LEARNING EXPERIENCES <i>◆</i> EVENT ADVISORY <i>◆</i></div></div>
</section>

<section class="intro section">
    <div class="container split-intro">
        <div><span class="eyebrow">Haleef means ally</span></div>
        <div>
            <h2>Not simply an organiser.<br>A partner in the outcome.</h2>
            <p class="lead">We become an extension of your team—shaping the idea, building the experience, coordinating the moving parts and delivering the moment.</p>
            <div class="stat-row">
                <div><strong>20</strong><span>years of founder<br>industry experience</span></div>
                <div><strong>3</strong><span>regions of<br>event experience</span></div>
                <div><strong>360°</strong><span>concept through<br>delivery</span></div>
            </div>
        </div>
    </div>
</section>

<section class="services-preview section">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">What we do</span><h2>Built around what<br>your event needs.</h2></div><a class="text-link" href="services.php">View all capabilities <span>→</span></a></div>
        <div class="service-list">
            <?php foreach ($services as $service): ?>
            <a class="service-row" href="service.php?service=<?= e($service['slug']) ?>">
                <span class="service-num"><?= e($service['number']) ?></span>
                <h3><?= e($service['title']) ?></h3>
                <p><?= e($service['summary']) ?></p>
                <span class="service-arrow">↗</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="moneyaura">
    <div class="container moneyaura-grid">
        <div class="ma-copy">
            <span class="eyebrow eyebrow-gold">Created by Haleef</span>
            <div class="ma-mark">MONEYAURA</div>
            <h2>A platform for the people shaping the future of money.</h2>
            <p>MoneyAura brings together financial leaders, regulators, innovators and solution providers through conferences, strategic networking and industry awards.</p>
            <div class="ma-locations"><span>Nairobi</span><span>Accra</span><span>Middle East & Africa</span></div>
            <div class="button-row"><a class="button button-gold" href="work.php">Read the story <span>→</span></a><a class="text-link text-link-light" href="<?= MONEYAURA_URL ?>" target="_blank" rel="noopener">Visit MoneyAura ↗</a></div>
        </div>
        <div class="ma-visual">
            <div class="ma-orbit"><span>IDEAS</span><span>PEOPLE</span><span>PROGRESS</span><b>MA</b></div>
            <div class="ma-caption">Flagship event platform<br>Conference · Awards · Networking</div>
        </div>
    </div>
</section>

<section class="founder section">
    <div class="container founder-grid">
        <div class="founder-image-wrap">
            <img src="assets/images/fahd-ali-akmal.jpg" alt="Fahd Ali Akmal, Founder and Managing Partner of Haleef" width="640" height="960">
            <span class="founder-stamp"><b>20</b> years<br>in events</span>
        </div>
        <div class="founder-copy">
            <span class="eyebrow">Founder story</span>
            <blockquote>“The rooms that matter are the ones built with intent.”</blockquote>
            <p>Fahd Ali Akmal is a conference producer and events strategist with two decades of experience building B2B conferences, summits, awards and exhibition-led experiences across the Middle East, Africa and Asia.</p>
            <p>His work spans banking, fintech, healthcare and education—connecting decision-makers with the people, ideas and opportunities that move industries forward.</p>
            <div class="founder-signoff"><strong>Fahd Ali Akmal</strong><span>Founder & Managing Partner</span></div>
            <a class="text-link" href="about.php#founder">Read Fahd’s story <span>→</span></a>
        </div>
    </div>
</section>

<section class="models section">
    <div class="container">
        <span class="eyebrow">How we can work together</span>
        <h2>Lead. Collaborate.<br>Strengthen.</h2>
        <div class="model-grid">
            <article><span>01</span><h3>Full-service delivery</h3><p>We manage the event from concept through on-site execution.</p></article>
            <article><span>02</span><h3>Collaborative delivery</h3><p>We work alongside your team with clear ownership and shared momentum.</p></article>
            <article><span>03</span><h3>Specialist support</h3><p>We take responsibility for the workstreams where you need us most.</p></article>
            <article><span>04</span><h3>Strategic advisory</h3><p>We help shape the format, plan, commercial model and path to delivery.</p></article>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/cta.php'; require __DIR__ . '/includes/footer.php'; ?>
