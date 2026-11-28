<?php
$offices = [
    [
        'key' => 'ajman',
        'city' => 'Ajman',
        'country' => 'United Arab Emirates',
        'company' => 'Haleef Global FZC LLC',
        'address' => ['Office BC-878846, 26th Floor', 'Amber Gem Tower, Business District',
            'Ajman NuVentures Centre Free Zone (ANCFZ)', 'Ajman, United Arab Emirates'],
    ],
    [
        'key' => 'budapest',
        'city' => 'Budapest',
        'country' => 'Hungary',
        'company' => 'Haleef Global Kft.',
        'address' => ['Páskomliget utca 67, 6/25', '1156 Budapest', 'Hungary'],
    ],
];
?>
<section class="section contact-offices" id="our-offices" aria-labelledby="contact-offices-title">
    <div class="container">
        <header class="contact-offices-heading">
            <span class="eyebrow">UAE &amp; Hungary</span>
            <h2 id="contact-offices-title">Our offices</h2>
        </header>
        <div class="contact-office-grid">
            <?php foreach ($offices as $office): ?>
                <article class="contact-office-card contact-office-<?= e($office['key']) ?>" aria-labelledby="office-<?= e($office['key']) ?>">
                    <header class="contact-office-card-header">
                        <div>
                            <p><?= e($office['country']) ?></p>
                            <h3 id="office-<?= e($office['key']) ?>"><?= e($office['city']) ?></h3>
                        </div>
                        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M19 10c0 5-7 10-7 10S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.4"/></svg>
                    </header>
                    <div class="contact-office-card-body">
                        <p class="contact-office-company"><?= e($office['company']) ?></p>
                        <address>
                            <?php foreach ($office['address'] as $addressLine): ?>
                                <span><?= e($addressLine) ?></span>
                            <?php endforeach; ?>
                        </address>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
