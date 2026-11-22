<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'haleef_test' || getenv('DB_HOST') !== '127.0.0.1') exit(1);
require dirname(__DIR__) . '/includes/bootstrap.php';

use Haleef\Support\Database;
use Haleef\Training\AgendaEmail;

$pdo = Database::connection();
$worker = new AgendaEmail($pdo);
$job = static fn() => $pdo->query('SELECT * FROM learning_agenda_email_jobs')->fetchAll();
if (count($job()) !== 1) throw new RuntimeException('Repeated requests should create one job.');
$worker->process(static function (): never { throw new RuntimeException('Transient test failure'); });
$afterFailure = $job()[0];
if ($afterFailure['status'] !== 'queued' || (int)$afterFailure['attempts'] !== 1
    || $worker->process(static function (): never { throw new LogicException('Retried too soon.'); }) !== 0) {
    throw new RuntimeException('Backoff did not work.');
}
$pdo->exec('UPDATE learning_agenda_email_jobs SET available_at=NOW()');
$worker->process(static function (string $email, string $subject, string $body): string {
    if ($email !== 'lead@example.test' || $subject !== 'Agenda for Advanced Learning Updated'
        || !str_contains($body,'23 Feb 2035') || !str_contains($body,'https://resources.haleeflearning.com/agendas/')
        || str_contains($body,'{{')) throw new LogicException('Email template was not rendered as expected.');
    return 'smoke-provider-id';
});
$sent = $job()[0];
if ($sent['status'] !== 'sent' || (int)$sent['attempts'] !== 2 || $sent['provider_id'] !== 'smoke-provider-id') {
    throw new RuntimeException('Provider acceptance was not recorded.');
}
putenv('AGENDA_EMAIL_ENABLED=0');
$agenda = $worker->eligible(null,1)[0];
if ($worker->request($agenda,'fallback@example.test','127.0.0.1') || count($job()) !== 1) {
    throw new RuntimeException('Unconfigured transport must keep download fallback without queuing mail.');
}
