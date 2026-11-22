<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\AdminAuth;
use Haleef\Support\{Csrf, Database, Flash};
use Haleef\Training\Faqs;

$adminUser = AdminAuth::requireUser();
$pdo = Database::connection();
$faqs = new Faqs($pdo);
$editionId = $faqEditionId ?? 0;
$base = '/admin/faqs';
$error = '';
if ($editionId) {
    $q = $pdo->prepare("SELECT e.slug,t.title FROM training_editions e JOIN trainings t ON t.id=e.training_id
        JOIN brands b ON b.id=t.brand_id WHERE e.id=? AND b.code='haleef-learning'");
    $q->execute([$editionId]);
    $edition = $q->fetch();
    if (!$edition) { http_response_code(404); require dirname(__DIR__) . '/not-found.php'; exit; }
    $base = "/admin/editions/$editionId/faqs";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
        $p = static fn(string $key): string => trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
        $action = $p('action');
        if ($id === false) throw new RuntimeException('Invalid FAQ.');
        if (!$editionId && $action === 'library') {
            [$question, $answer, $order] = Faqs::validate($p('question'), $p('answer'), $p('sort_order'), true);
            $args = [$question, $answer, isset($_POST['enabled']) ? 1 : 0, $order ?? 100];
            if ($id) {
                $pdo->prepare('UPDATE learning_faqs SET question=?,answer=?,default_enabled=?,sort_order=? WHERE id=?')->execute([...$args,$id]);
            } else {
                $pdo->prepare('INSERT INTO learning_faqs (question,answer,default_enabled,sort_order) VALUES (?,?,?,?)')->execute($args);
            }
        } elseif ($editionId && $id && $action === 'inherit') {
            $pdo->prepare('DELETE FROM learning_edition_faqs WHERE edition_id=? AND faq_id=?')->execute([$editionId,$id]);
        } elseif ($editionId && $id && $action === 'shared') {
            [$question, $answer, $order] = Faqs::validate($p('question'), $p('answer'), $p('sort_order'), false);
            $pdo->prepare('INSERT INTO learning_edition_faqs (edition_id,faq_id,enabled,question,answer,sort_order)
                VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),question=VALUES(question),
                answer=VALUES(answer),sort_order=VALUES(sort_order)')
                ->execute([$editionId,$id,isset($_POST['enabled']) ? 1 : 0,$question,$answer,$order]);
        } elseif ($editionId && $action === 'custom') {
            [$question, $answer, $order] = Faqs::validate($p('question'), $p('answer'), $p('sort_order'), true);
            $args = [$question,$answer,$order ?? 100,isset($_POST['enabled']) ? 1 : 0,$editionId];
            if ($id) {
                $pdo->prepare('UPDATE learning_edition_faqs SET question=?,answer=?,sort_order=?,enabled=? WHERE edition_id=? AND id=? AND faq_id IS NULL')
                    ->execute([...$args,$id]);
            } else {
                $pdo->prepare('INSERT INTO learning_edition_faqs (question,answer,sort_order,enabled,edition_id) VALUES (?,?,?,?,?)')->execute($args);
            }
        } elseif ($editionId && $id && $action === 'remove') {
            $pdo->prepare('DELETE FROM learning_edition_faqs WHERE edition_id=? AND id=? AND faq_id IS NULL')->execute([$editionId,$id]);
        } else {
            throw new RuntimeException('Invalid FAQ action.');
        }
        Flash::set('success', 'FAQ settings saved.');
        header('Location: ' . $base, true, 303);
        exit;
    } catch (Throwable $ex) {
        if ($ex instanceof PDOException) error_log($ex->getMessage());
        $error = $ex instanceof PDOException ? 'Could not save the FAQ. Please try again.' : $ex->getMessage();
    }
}

$adminPageTitle = $editionId ? 'Edition FAQs' : 'FAQ Library';
require __DIR__ . '/_header.php';
$form = static function (array $row, string $mode, bool $new = false): void {
    $shared = $mode === 'shared';
    $enabled = $shared ? ($row['enabled'] ?? $row['default_enabled']) : ($row['enabled'] ?? $row['default_enabled'] ?? ($mode === 'custom'));
    $question = $shared ? $row['local_question'] : ($row['question'] ?? '');
    $answer = $shared ? $row['local_answer'] : ($row['answer'] ?? '');
    $order = $shared ? $row['local_order'] : ($row['sort_order'] ?? '');
    $id = (int)($row['id'] ?? 0);
?>
<form method="post"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <label class="check-label"><input type="checkbox" name="enabled" <?= $enabled ? 'checked' : '' ?>><?= $mode === 'library' ? 'Include by default on editions' : 'Show on this edition' ?></label>
    <?php if ($shared): ?><p class="admin-help">Leave the overrides blank to use the library content and order.</p><details><summary>View library answer</summary><div><?= \Haleef\Training\Markdown::render($row['answer']) ?></div></details><?php endif; ?>
    <label><?= $shared ? 'Question override' : 'Question' ?><input name="question" maxlength="255" value="<?= e((string)$question) ?>" <?= $shared ? 'placeholder="' . e($row['question']) . '"' : 'required' ?>></label>
    <label><?= $shared ? 'Answer override' : 'Answer' ?> (Markdown)<textarea name="answer" maxlength="20000" rows="5" <?= $shared ? '' : 'required' ?>><?= e((string)$answer) ?></textarea></label>
    <label>Display order<input type="number" min="0" max="99999" name="sort_order" value="<?= e((string)$order) ?>" <?= $shared ? 'placeholder="' . (int)$row['sort_order'] . '"' : '' ?>></label>
    <div><button name="action" value="<?= e($mode) ?>"><?= $new ? 'Add FAQ' : 'Save FAQ' ?></button>
    <?php if ($shared && $row['enabled'] !== null): ?><button name="action" value="inherit" formnovalidate>Reset to library defaults</button><?php endif; ?></div>
</form>
<?php if ($mode === 'custom' && $id): ?><form method="post" onsubmit="return confirm('Remove this edition-only FAQ?')"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= $id ?>"><button name="action" value="remove">Remove edition FAQ</button></form><?php endif;
};
?>
<?php if ($error): ?><div class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="admin-panel admin-form-panel">
<?php if ($editionId): ?><div class="panel-heading"><h2><?= e($edition['title']) ?> · <?= e($edition['slug']) ?></h2><div><a href="/admin/editions/<?= $editionId ?>/edit">Back to edition</a> · <a href="/admin/preview/editions/<?= $editionId ?>" target="_blank" rel="noopener">Preview edition</a></div></div>
<p>Enable, hide or change a reusable question for this edition only. Changes here do not affect the <a href="/admin/faqs">FAQ library</a>.</p>
<?php else: ?><h2>Reusable FAQs</h2><p>Library edits flow through to editions that inherit the content. Default entries appear automatically, unless an edition hides them.</p><?php endif; ?>
<?php foreach (($editionId ? $faqs->sharedForEdition($editionId) : $faqs->library()) as $row): ?>
<details class="faq-admin-item"><summary><?= e($row['local_question'] ?? $row['question']) ?> · <?= ($row['enabled'] ?? $row['default_enabled']) ? ($editionId ? 'Shown' : 'Default') : ($editionId ? 'Hidden' : 'Optional') ?></summary>
<?php $form($row,$editionId ? 'shared' : 'library'); ?></details>
<?php endforeach; ?></section>
<section class="admin-panel admin-form-panel"><h2><?= $editionId ? 'Edition-only FAQs' : 'Add a library FAQ' ?></h2>
<?php if ($editionId): foreach ($faqs->custom($editionId) as $row): ?><details class="faq-admin-item"><summary><?= e($row['question']) ?> · <?= $row['enabled'] ? 'Shown' : 'Hidden' ?></summary><?php $form($row,'custom'); ?></details><?php endforeach; endif; ?>
<?php $form([], $editionId ? 'custom' : 'library', true); ?></section>
<?php require __DIR__ . '/_footer.php'; ?>
