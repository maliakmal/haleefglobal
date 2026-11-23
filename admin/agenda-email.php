<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\{AdminAuth, Lists};
use Haleef\Support\{Csrf, Database, Flash};
use Haleef\Training\AgendaEmail;

$adminUser = AdminAuth::requireUser();
$pdo = Database::connection();
$mail = new AgendaEmail($pdo);
$template = $mail->template();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['subject','body'] as $field) $template[$field] = trim(is_string($_POST[$field] ?? null) ? $_POST[$field] : '');
    try {
        Csrf::verify($_POST['_csrf'] ?? null);
        $mail->saveTemplate($template['subject'],$template['body']);
        Flash::set('success','Agenda email template saved. New email jobs will use this version.');
        header('Location: /admin/agenda-email',true,303);
        exit;
    } catch (Throwable $ex) {
        if ($ex instanceof PDOException) error_log($ex->getMessage());
        $error = $ex instanceof PDOException ? 'The template could not be saved.' : $ex->getMessage();
    }
}
$statuses = ['queued'=>'Queued','sent'=>'Sent to Brevo','failed'=>'Failed'];
$q = Lists::select($pdo,'SELECT l.email,t.title,j.created_at,j.status,j.attempts,j.sent_at,j.last_error FROM learning_agenda_email_jobs j
    JOIN agenda_interests l ON l.id=j.interest_id JOIN training_editions e ON e.id=l.edition_id JOIN trainings t ON t.id=e.training_id WHERE 1=1',
    ['l.email','t.title'],'j.id DESC','j.status',$statuses,50);
if (Lists::exporting()) Lists::csv('agenda-email',['Recipient','Course','Queued','Status','Attempts','Sent','Last error'],Lists::rows($q));
$jobs = $q->fetchAll();
$adminPageTitle = 'Agenda email';
require __DIR__ . '/_header.php';
?>
<section class="admin-panel admin-form-panel">
    <h2>Agenda request template</h2>
    <p><?= AgendaEmail::configured() ? 'Email transport settings are present. Check the queue below or run a test request to verify delivery.' : 'Email transport is not enabled. Visitors can still download the PDF after submitting the form.' ?></p>
    <?php if ($error): ?><div class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <p><a href="/admin/email-templates?template=agenda_request">Edit this template in Email Templates</a></p>
</section>
<section class="admin-panel"><h2>Recent email jobs</h2>
    <?php Lists::filter('/admin/agenda-email',$statuses,'Recipient or course'); ?>
    <p>“Sent” means Brevo accepted the message. Delivery to the inbox is not guaranteed.</p>
    <?php if (!$jobs): ?><p>No email jobs yet.</p><?php else: ?>
    <div class="admin-table-wrap"><table><thead><tr><th>Recipient / course</th><th>Queued</th><th>Status</th><th>Attempts</th><th>Last error</th></tr></thead><tbody>
    <?php foreach ($jobs as $job): ?><tr><td><?= e($job['email']) ?><small><?= e($job['title']) ?></small></td><td><?= e($job['created_at']) ?></td><td><?= e($job['status']) ?><small><?= e((string)$job['sent_at']) ?></small></td><td><?= (int)$job['attempts'] ?></td><td><?= e((string)$job['last_error']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
