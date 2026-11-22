<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

use Haleef\Admin\AdminAuth;
use Haleef\Support\Database;
use Haleef\Training\{Editorial, Markdown};

$preview = isset($previewId);
if ($preview) {
    AdminAuth::requireUser();
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex, nofollow');
}
$pdo = Database::connection();
$slug = $_GET['trainer'] ?? '';
$trainer = null;
$courses = [];
if ($preview || $slug !== '') {
    $q = $pdo->prepare($preview ? 'SELECT * FROM trainers WHERE id=?' : 'SELECT * FROM trainers WHERE slug=? AND is_published=1');
    $q->execute([$preview ? $previewId : $slug]);
    $trainer = $q->fetch();
    if (!$trainer) { http_response_code(404); require __DIR__ . '/not-found.php'; exit; }
    $q = $pdo->prepare("SELECT e.id,e.slug,e.starts_at,e.ends_at,e.timezone,t.title,t.slug AS training_slug
        FROM training_trainers tt JOIN training_editions e ON e.id=tt.edition_id
        JOIN trainings t ON t.id=e.training_id JOIN brands b ON b.id=t.brand_id
        WHERE tt.trainer_id=? AND b.code='haleef-learning'" .
        ($preview ? '' : " AND e.publication_status='published' AND t.status='published'") . ' ORDER BY e.starts_at');
    $q->execute([$trainer['id']]);
    $courses = array_filter($q->fetchAll(), static fn($row) => !Editorial::ended($row));
} else {
    $trainers = $pdo->query('SELECT * FROM trainers WHERE is_published=1 ORDER BY name')->fetchAll();
}
$pageTitle = $trainer ? $trainer['name'] : 'Haleef Learning trainers';
$currentPage = 'training';
if ($trainer && !$preview) {
    $shareUrl = rtrim(\Haleef\Support\Env::get('APP_URL') ?: 'https://www.haleefglobal.com', '/') . '/training/trainers/' . $trainer['slug'];
    $shareImage = $trainer['image_path'];
    $shareHeading = 'Share this speaker';
    if (!empty($trainer['headline'])) $pageDescription = $trainer['headline'];
}
require __DIR__ . '/includes/header.php';
?>
<div class="learning-editorial">
<?php if ($preview): ?><div class="learning-preview"><div class="container">Private trainer preview <a href="/admin/trainers/<?= (int)$previewId ?>/edit">Back to editor</a></div></div><?php endif; ?>
<section class="learning-hero"><div class="container"><nav class="learning-crumb"><a href="/training/courses">Courses</a> / <a href="/training/trainers">Trainers</a></nav>
<?php if ($trainer): ?><div class="learning-profile">
<?php if ($trainer['image_path']): ?><img src="<?= e($trainer['image_path']) ?>" alt=""><?php endif; ?>
<div><span class="eyebrow">Trainer</span><h1><?= e($trainer['name']) ?></h1><p><?= e((string)$trainer['headline']) ?></p>
<div class="learning-pills"><?php foreach (array_filter(array_map('trim',explode(',', (string)$trainer['specialties']))) as $tag): ?><span><?= e($tag) ?></span><?php endforeach; ?></div>
<?php $trainerSocialProfile = $trainer; require __DIR__ . '/includes/trainer-linkedin.php'; ?></div></div>
<?php else: ?><span class="eyebrow">Haleef Learning</span><h1>Meet our trainers</h1><?php endif; ?></div></section>
<?php if (!empty($trainer['company_showcase_enabled']) && !empty($trainer['company_name'])): ?>
<section class="learning-trainer-company" aria-labelledby="trainer-company-title" data-reveal>
    <div class="container learning-trainer-company-inner">
        <?php if (!empty($trainer['company_logo_path'])): ?>
        <div class="learning-company-logo"><img src="<?= e($trainer['company_logo_path']) ?>" alt="" loading="lazy" decoding="async"></div>
        <?php endif; ?>
        <div class="learning-company-copy"><span class="eyebrow">Company spotlight</span>
            <h2 id="trainer-company-title"><?= e($trainer['company_name']) ?></h2>
            <?php if (!empty($trainer['company_about'])): ?><p><?= e($trainer['company_about']) ?></p><?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php if ($trainer): ?><section class="container learning-sections"><article><h2>About the trainer</h2><?= Markdown::render($trainer['biography']) ?></article><article><h2>Upcoming courses</h2><div class="learning-links">
<?php foreach ($courses as $course): ?><a href="<?= e($preview ? '/admin/preview/editions/' . $course['id'] : '/training/courses/' . $course['training_slug'] . '/' . $course['slug']) ?>"><?= e($course['title']) ?><small><?= e(Editorial::date($course,'starts_at')->format('d M Y')) ?></small></a><?php endforeach; ?>
<?php if (!$courses): ?><p>No upcoming editions listed.</p><?php endif; ?></div></article></section>
<?php else: ?><section class="container learning-band"><div class="learning-trainers"><?php foreach ($trainers as $tr): ?><article><?php if ($tr['image_path']): ?><img src="<?= e($tr['image_path']) ?>" alt=""><?php endif; ?><div><h2><?= e($tr['name']) ?></h2><p><?= e((string)$tr['headline']) ?></p><a href="/training/trainers/<?= e($tr['slug']) ?>">View profile</a></div></article><?php endforeach; ?></div><?php if (!$trainers): ?><p>Trainer profiles are coming soon.</p><?php endif; ?></section><?php endif; ?>
<?php if ($trainer && !$preview): ?><div class="container learning-band"><?php require __DIR__ . '/includes/learning-share.php'; ?></div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
