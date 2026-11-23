<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\{AdminAuth, Lists};
use Haleef\Support\{ContactEnquiries, Csrf, Database, Flash};

$adminUser = AdminAuth::requireUser();
header('Cache-Control: private, no-store');
$pdo = Database::connection();
$base = '/admin/contact-enquiries';
$id = $enquiryId ?? 0;
$error = '';
$row = null;
if ($id) {
    $url = $base.'/'.$id;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            if (($_POST['action'] ?? '') === 'retry') {
                $q = $pdo->prepare("UPDATE contact_email_jobs SET status='queued',attempts=0,available_at=NOW(),last_error=NULL WHERE enquiry_id=? AND status='failed'");
                $q->execute([$id]);
                if (!$q->rowCount()) throw new RuntimeException('Only failed notifications can be retried.');
                Flash::set('success','Notification queued for retry.');
            } else {
                $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
                if (!isset(ContactEnquiries::STATUS[$status])) throw new RuntimeException('Choose a valid enquiry status.');
                $pdo->prepare('UPDATE contact_enquiries SET status=? WHERE id=?')->execute([$status,$id]);
                Flash::set('success','Enquiry status saved.');
            }
            header('Location: '.$url,true,303);
            exit;
        } catch (Throwable $ex) {
            $error = $ex instanceof PDOException ? 'Could not update the enquiry.' : $ex->getMessage();
        }
    }
    $q = $pdo->prepare('SELECT e.*, j.status AS mail_status,j.attempts,j.sent_at,j.last_error FROM contact_enquiries e
        LEFT JOIN contact_email_jobs j ON j.enquiry_id=e.id WHERE e.id=?');
    $q->execute([$id]);
    $row = $q->fetch();
    if (!$row) { http_response_code(404); require dirname(__DIR__) . '/not-found.php'; exit; }
} else {
    $columns = Lists::exporting() ? 'e.id,e.created_at,e.name,e.email,e.company,e.enquiry_type,e.location,e.status,j.status,e.message'
        : 'e.id,e.created_at,e.name,e.email,e.company,e.enquiry_type,e.status,j.status AS mail_status';
    $q = Lists::select($pdo,"SELECT $columns FROM contact_enquiries e LEFT JOIN contact_email_jobs j ON j.enquiry_id=e.id WHERE 1=1",
        ['e.name','e.email','e.company','e.enquiry_type','e.location'],'e.id DESC','e.status',ContactEnquiries::STATUS,500);
    if (Lists::exporting()) Lists::csv('contact-enquiries',['ID','Received','Name','Email','Company','Type','Location','Status','Email status','Message'],Lists::rows($q));
    $rows = $q->fetchAll();
}
$adminPageTitle = $id ? 'Contact enquiry #'.$id : 'Contact enquiries';
require __DIR__ . '/_header.php';
?>
<?php if ($error): ?><div class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($row): ?>
<section class="admin-panel admin-form-panel">
    <div class="panel-heading"><h2><?= e($row['name']) ?></h2><a href="<?= $base ?>">Back to enquiries</a></div>
    <p><?= e($row['created_at']) ?> · <?= e($row['enquiry_type'] ?: 'General enquiry') ?></p>
    <p><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a> · <?= e($row['company']) ?> · <?= e($row['location']) ?></p>
    <h3>Message</h3><p class="enquiry-message"><?= e($row['message']) ?></p>
    <form method="post"><?= Csrf::field() ?><label>Status<select name="status">
        <?php foreach (ContactEnquiries::STATUS as $key=>$label): ?><option value="<?= e($key) ?>" <?= $row['status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select></label><button type="submit">Save status</button></form>
</section>
<section class="admin-panel admin-form-panel"><h2>Staff notification</h2>
    <p><?= e(ContactEnquiries::MAIL_STATUS[$row['mail_status']] ?? 'Not queued') ?> · <?= (int)$row['attempts'] ?> attempts<?= $row['sent_at']?' · '.e($row['sent_at']):'' ?></p>
    <?php if ($row['last_error']): ?><p><?= e($row['last_error']) ?></p><?php endif; ?>
    <?php if ($row['mail_status'] === 'failed'): ?><form method="post"><?= Csrf::field() ?><button name="action" value="retry">Retry notification</button></form><?php endif; ?>
</section>
<?php else: ?>
<section class="admin-panel"><h2>Contact form inbox</h2>
    <?php Lists::filter($base,ContactEnquiries::STATUS,'Name, email, company, or enquiry type'); ?>
    <p>Showing up to 500 recent matches. CSV includes all matches and their messages.</p>
    <div class="admin-table-wrap"><table><thead><tr><th>Received</th><th>Contact</th><th>Type</th><th>Status</th><th>Notification</th></tr></thead><tbody>
    <?php foreach ($rows as $entry): ?><tr><td><?= e($entry['created_at']) ?></td>
    <td><a href="<?= $base.'/'.(int)$entry['id'] ?>"><?= e($entry['name']) ?></a><small><?= e($entry['email']) ?> · <?= e($entry['company']) ?></small></td>
    <td><?= e($entry['enquiry_type']) ?></td><td><?= e(ContactEnquiries::STATUS[$entry['status']]) ?></td>
    <td><?= e(ContactEnquiries::MAIL_STATUS[$entry['mail_status']] ?? 'Not queued') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php if (!$rows): ?><p>No matching enquiries.</p><?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
