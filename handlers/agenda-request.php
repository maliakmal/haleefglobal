<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/config.php';

use Haleef\Support\{Csrf, Database, WebSession};
use Haleef\Training\AgendaEmail;

header('Cache-Control: private, no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
WebSession::start();
$return = is_string($_POST['return_path'] ?? null) ? $_POST['return_path'] : '';
if (!preg_match('~^/training(?:/courses(?:/[a-z0-9-]+){0,2})?$~D', $return)) $return = '/training';
$value = static fn($key) => trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
try {
    Csrf::verify($value('_csrf'));
    $email = strtolower($value('email'));
    if ($value('website') !== '' || strlen($email) > 254 || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address to request the agenda.');
    }
    $id = filter_var($value('edition_id'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $requests = new AgendaEmail(Database::connection());
    $agenda = $id ? ($requests->eligible(null,$id)[0] ?? null) : null;
    if (!$agenda) throw new RuntimeException('That agenda is no longer available. Please choose another edition.');
    $queued = $requests->request($agenda,$email,(string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $_SESSION['agenda_notice'] = ['path'=>$return, 'ok'=>true,
        'message'=>$queued ? 'Request received. Check your inbox in a few minutes for the agenda link.' : 'Request received. Use the link below to download your agenda.',
        'download'=>$queued ? null : $agenda['public_url']];
} catch (Throwable $error) {
    if ($error instanceof PDOException) error_log($error->getMessage());
    $_SESSION['agenda_notice'] = ['path'=>$return, 'ok'=>false,
        'message'=>$error instanceof RuntimeException && !($error instanceof PDOException) ? $error->getMessage() : 'Could not process your request. Please try again.'];
}
header('Location: ' . $return . '#agenda', true, 303);
