<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

use Haleef\Admin\AdminAuth;
use Haleef\Support\{Database, Env};
use Haleef\Training\{AgendaEmail, Editorial, Faqs, Markdown, TrainingRepository};

$preview = isset($previewId);
if ($preview) AdminAuth::requireUser();
header('Cache-Control: private, no-store');
if ($preview) header('X-Robots-Tag: noindex, nofollow');
$pdo = Database::connection();
$edition = null;
if ($preview) {
    if ($previewKind === 'editions') {
        $q = $pdo->prepare('SELECT * FROM training_editions WHERE id=?');
        $q->execute([$previewId]);
        $edition = $q->fetch() ?: null;
    }
    $q = $pdo->prepare("SELECT t.* FROM trainings t JOIN brands b ON b.id=t.brand_id WHERE t.id=? AND b.code='haleef-learning'");
    $q->execute([$previewKind === 'editions' ? ($edition['training_id'] ?? 0) : $previewId]);
    $training = $q->fetch();
    $editions = [];
    if ($training && !$edition) {
        $q = $pdo->prepare('SELECT * FROM training_editions WHERE training_id=? ORDER BY starts_at');
        $q->execute([$training['id']]);
        $editions = $q->fetchAll();
    }
} else {
    $training = (new TrainingRepository())->findPublished((string) ($_GET['training'] ?? ''));
    $editions = $training['editions'] ?? [];
    $editionSlug = (string) ($_GET['edition'] ?? '');
    if ($editionSlug !== '') {
        foreach ($editions as $candidate) if ($candidate['slug'] === $editionSlug) { $edition = $candidate; break; }
        if (!$edition) $training = null;
    }
}
if (!$training) { http_response_code(404); require __DIR__ . '/not-found.php'; exit; }
$courseUrl = '/training/courses/' . $training['slug'];
$trainers = [];
if ($edition) {
    $q = $pdo->prepare('SELECT tr.* FROM training_trainers tt JOIN trainers tr ON tr.id=tt.trainer_id WHERE tt.edition_id=?' . ($preview ? '' : ' AND tr.is_published=1') . ' ORDER BY tt.display_order,tr.name');
    $q->execute([$edition['id']]); $trainers = $q->fetchAll();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code(405); header('Allow: GET, HEAD'); exit; }
$agendaOptions = $preview ? [] : (new AgendaEmail($pdo))->eligible((int)$training['id'], $edition ? (int)$edition['id'] : null);
$agendaInPreview = $preview && !empty($edition['agenda_asset_id']);
$agendaReturn = $courseUrl . ($edition ? '/' . $edition['slug'] : '');
$pageTitle = $training['title'];
$pageDescription = $training['short_description'] ?: 'Haleef Learning professional training programme.';
$currentPage = 'training';
$headerImage = ($edition['image_path'] ?? '') ?: $training['image_path'];
if (!$preview) {
    $shareUrl = rtrim(Env::get('APP_URL') ?: 'https://www.haleefglobal.com', '/') . $courseUrl . ($edition ? '/' . $edition['slug'] : '');
    $shareImage = $headerImage;
    $shareHeading = $edition ? 'Share this edition' : 'Share this programme';
}
$editionFaqs = $edition ? (new Faqs($pdo))->visible((int)$edition['id']) : [];
$format = ['online'=>'Live online training','in_person'=>'In-person training','hybrid'=>'Hybrid training'];
$icon = static function (string $key): string {
    $p = ['video'=>'<rect x="2" y="5" width="14" height="14" rx="2"/><path d="m16 10 6-4v12l-6-4"/>',
        'devices'=>'<rect x="2" y="4" width="16" height="12" rx="2"/><rect x="15" y="10" width="7" height="11" rx="1"/><path d="M6 20h5"/>',
        'chat'=>'<path d="M3 4h18v13H9l-6 4z M7 9h10 M7 13h7"/>','document'=>'<path d="M5 2h9l5 5v15H5z M14 2v6h5 M8 12h8 M8 16h8"/>',
        'award'=>'<circle cx="12" cy="8" r="5"/><path d="m8 12-2 9 6-3 6 3-2-9"/>','pin'=>'<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/>'];
    return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">' . $p[$key] . '</svg>';
};
$canBook = $edition && $edition['registration_status'] === 'open' && !Editorial::ended($edition);
$details = $edition ? Editorial::date($edition,'starts_at')->format('d M Y, H:i') . ' – ' . Editorial::date($edition,'ends_at')->format('d M Y, H:i') : '';
require __DIR__ . '/includes/header.php';
?>
<div class="learning-editorial">
<?php if ($preview): ?><div class="learning-preview"><div class="container">Private preview · <?= $previewKind === 'editions' ? 'Edition' : 'Programme' ?> · booking and downloads disabled <a href="/admin/<?= e($previewKind) ?>/<?= (int)$previewId ?>/edit">Back to editor</a></div></div><?php endif; ?>
<section class="learning-hero">
<?php if ($headerImage): ?><img class="learning-hero-art" src="<?= e($headerImage) ?>" alt=""><?php endif; ?>
<div class="container learning-hero-layout"><div><nav class="learning-crumb"><a href="/training/courses">Courses</a> / <a href="/training/trainers">Trainers</a></nav>
<span class="eyebrow"><?= e($edition ? $format[$edition['delivery_mode']] : 'Haleef Learning programme') ?></span><h1><?= e($training['title']) ?></h1><p><?= e((string)$training['short_description']) ?></p><?php if ($agendaOptions): ?><a class="learning-agenda-jump" href="#agenda">Request the agenda</a><?php endif; ?></div>
<?php if ($edition): ?><aside class="learning-booking"><strong><?= e($details) ?></strong><span><?= e($edition['timezone']) ?></span><span><?= e($edition['venue_name'] ?: $format[$edition['delivery_mode']]) ?></span><b><?= e(\Haleef\Support\MoneyDisplay::format((float)$edition['price'], $edition['currency'])) ?></b>
<?php if ($training['cpd_available'] && $edition['cpd_points'] !== null): ?><span><?= e((string)$edition['cpd_points']) ?> CPD points</span><?php endif; ?>
<?php if ($canBook && !$preview): ?><a class="learning-action" href="/training/register/<?= e($edition['slug']) ?>">Book your seat</a>
<?php elseif ($preview): ?><span>Booking disabled in preview</span>
<?php elseif ($edition['registration_status']==='waitlist' && !Editorial::ended($edition)): ?><a class="learning-action" href="/contact?interest=corporate-training">Enquire about the waitlist</a>
<?php else: ?><span>Registration closed</span><?php endif; ?></aside><?php endif; ?>
</div></section>
<?php if ($edition):
$highlights = [$edition['delivery_mode'] === 'in_person' ? ['pin','In person','Attend at the venue.'] : ['video',$format[$edition['delivery_mode']],$edition['delivery_mode']==='hybrid' ? 'Join online or at the venue.' : 'Join the instructor live online.']];
foreach ((json_decode((string)$edition['features'],true) ?: []) as $feature) if (isset(Editorial::FEATURES[$feature])) $highlights[] = Editorial::FEATURES[$feature];
?><div class="container learning-highlights"><?php foreach ($highlights as [$symbol,$title,$copy]): ?><div><?= $icon($symbol) ?><div><strong><?= e($title) ?></strong><p><?= e($copy) ?></p></div></div><?php endforeach; ?></div><?php endif; ?>
<div class="container learning-course-copy"><?php foreach (['description'=>['overview','Overview'],'audience'=>['audience','Who should attend'],'learning_outcomes'=>['outcomes','Learning outcomes'],'key_notes'=>['notes','Key notes']] as $col=>[$sectionId,$heading]): if (!empty($training[$col])): ?><section id="<?= e($sectionId) ?>" aria-labelledby="<?= e($sectionId) ?>-heading"><h2 id="<?= e($sectionId) ?>-heading"><?= e($heading) ?></h2><div><?= Markdown::render($training[$col]) ?></div></section><?php endif; endforeach; ?></div>
<?php if (!$edition): ?><section class="container learning-band"><h2>Scheduled editions</h2><?php if (!$editions): ?><p>No editions scheduled yet.</p><?php endif; ?><div class="learning-links"><?php foreach ($editions as $item): ?><a href="<?= e($preview ? '/admin/preview/editions/' . $item['id'] : $courseUrl . '/' . $item['slug']) ?>"><?= e(Editorial::date($item,'starts_at')->format('d M Y') . ' · ' . $format[$item['delivery_mode']]) ?></a><?php endforeach; ?></div></section><?php endif; ?>
<?php if ($trainers): ?><section class="container learning-band"><h2>Meet your <?= count($trainers) > 1 ? 'trainers' : 'trainer' ?></h2><div class="learning-trainers"><?php foreach ($trainers as $tr): ?><article>
<?php if ($tr['image_path']): ?><img src="<?= e($tr['image_path']) ?>" alt=""><?php endif; ?><div><h3><?= e($tr['name']) ?></h3><p><?= e((string)$tr['headline']) ?></p><div class="learning-trainer-actions"><a href="<?= e($preview ? '/admin/preview/trainers/' . $tr['id'] : '/training/trainers/' . $tr['slug']) ?>">View trainer profile</a><?php $trainerSocialProfile = $tr; require __DIR__ . '/includes/trainer-linkedin.php'; ?></div></div></article><?php endforeach; ?></div></section><?php endif; ?>
<?php if ($editionFaqs): ?><section class="container learning-band learning-faqs" aria-labelledby="edition-faq-title"><h2 id="edition-faq-title">Frequently asked questions</h2>
<?php foreach ($editionFaqs as $faq): ?><details><summary><?= e($faq['question']) ?></summary><div><?= Markdown::render($faq['answer']) ?></div></details><?php endforeach; ?></section>
<?php elseif (!$edition): ?><section class="container learning-band"><h2>Training for your team</h2><p><a href="/contact?interest=corporate-training">Ask about a private edition.</a></p></section><?php endif; ?>
<?php if (!$preview): ?><div class="container learning-band"><?php require __DIR__ . '/includes/learning-share.php'; ?></div><?php endif; ?>
<?php require __DIR__ . '/includes/learning-agenda.php'; ?>
<?php if ($canBook && !$preview): ?><section class="learning-final"><h2>Secure your place</h2><a class="learning-action" href="/training/register/<?= e($edition['slug']) ?>">Book your seat</a></section><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
