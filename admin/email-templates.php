<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\{AdminAuth, Lists};
use Haleef\Support\{Csrf, Database, EmailTemplates, Flash};

$adminUser = AdminAuth::requireUser();
$pdo = Database::connection();
header('Cache-Control: private, no-store');
$base = '/admin/email-templates';
$key = is_string($_GET['template'] ?? null) && isset(EmailTemplates::LABELS[$_GET['template']]) ? $_GET['template'] : 'agenda_request';
$url = $base . '?template=' . $key;
$template = EmailTemplates::load($pdo,$key);
$samples = EmailTemplates::samples($key);
$error = '';
$preview = null;
$templateRows = [];
foreach (EmailTemplates::LABELS as $id=>$label) {
    $t = $id === $key ? $template : EmailTemplates::load($pdo,$id);
    $templateRows[] = ['name'=>$label,'key'=>$id,'subject'=>$t['subject'],'body'=>$t['body'],'updated'=>$t['updated_at']];
}
$templateRows = Lists::searchRows($templateRows,['name','key','subject','body']);
if (Lists::exporting()) {
    Lists::csv('email-templates',['Template','Key','Subject','Markdown','Updated'],$templateRows);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ajax = ($_GET['preview'] ?? '') === '1';
    foreach (['subject','body'] as $field) $template[$field] = trim(is_string($_POST[$field] ?? null) ? $_POST[$field] : '');
    try {
        Csrf::verify($_POST['_csrf'] ?? null);
        EmailTemplates::validate($key,$template['subject'],$template['body']);
        if (!$ajax && ($_POST['action'] ?? '') === 'save') {
            $pdo->prepare('UPDATE learning_email_templates SET subject=?,body=? WHERE template_key=?')
                ->execute([$template['subject'],$template['body'],$key]);
            Flash::set('success','Template saved. Already queued emails are unchanged.');
            header('Location: '.$url,true,303);
            exit;
        }
        $preview = EmailTemplates::render($key,$template['subject'],$template['body'],$samples);
    } catch (Throwable $ex) {
        $error = $ex instanceof PDOException ? 'Could not save the template.' : $ex->getMessage();
    }
    if ($ajax) {
        if ($error !== '') http_response_code(422);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($error !== '' ? ['error'=>$error] : $preview,JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
$preview ??= EmailTemplates::render($key,$template['subject'],$template['body'],$samples);
$adminPageTitle = 'Email templates';
require __DIR__ . '/_header.php';
?>
<section class="admin-panel">
    <div class="panel-heading"><h2>Templates</h2><a href="/admin/agenda-email">Agenda delivery queue</a></div>
    <?php Lists::filter($base,[],'Template name, subject, or body'); ?>
    <p><?php if (!$templateRows) echo 'No matching templates.'; ?>
    <?php foreach ($templateRows as $item): ?>
        <a href="<?= e($base.'?template='.$item['key']) ?>" <?= $key===$item['key']?'aria-current="page"':'' ?>><?= e($item['name']) ?></a> &nbsp;
    <?php endforeach; ?></p>
</section>
<section class="admin-panel email-editor-grid">
    <div class="admin-form-panel"><h2><?= e(EmailTemplates::LABELS[$key]) ?></h2>
        <p>Use simple Markdown: headings, lists, bold, and links. Put URL placeholders on their own line. Recipients and attachments are managed by Haleef.</p>
        <?php if ($error): ?><div class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e($url) ?>" data-email-editor data-preview="<?= e($url.'&preview=1') ?>">
            <?= Csrf::field() ?>
            <label>Subject<input name="subject" maxlength="200" value="<?= e($template['subject']) ?>" required></label>
            <label>Message (Markdown)<textarea name="body" rows="15" maxlength="8000" required><?= e($template['body']) ?></textarea></label>
            <div class="email-fields" aria-label="Insert placeholder"><?php foreach ($samples as $name=>$sample): ?>
                <button type="button" data-email-token="<?= e('{{'.$name.'}}') ?>"><?= e($name) ?></button>
            <?php endforeach; ?></div>
            <button type="submit" name="action" value="save">Save template</button>
            <button type="submit" name="action" value="preview">Refresh preview</button>
        </form>
    </div>
    <div><h2>Preview</h2><p>Sample data only. Nothing is sent.</p>
        <p data-email-error role="status"></p>
        <p><strong>Subject:</strong> <span data-email-subject><?= e($preview['subject']) ?></span></p>
        <iframe data-email-html title="HTML email preview" sandbox referrerpolicy="no-referrer" srcdoc="<?= e($preview['html']) ?>"></iframe>
        <details open><summary>Plain-text version</summary><pre data-email-text><?= e($preview['text']) ?></pre></details>
    </div>
</section>
<script src="/assets/js/admin-email-templates.js?v=1" defer></script>
<?php require __DIR__ . '/_footer.php'; ?>
