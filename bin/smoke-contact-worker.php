<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'haleef_test' || getenv('DB_HOST') !== '127.0.0.1') exit(1);
require dirname(__DIR__) . '/includes/bootstrap.php';

use Haleef\Support\{ContactEnquiries, Database};

$pdo = Database::connection();
if (ContactEnquiries::process($pdo,static fn()=>throw new RuntimeException('Test transport failure')) !== 1) {
    throw new RuntimeException('Expected a queued contact notification.');
}
if ($pdo->query('SELECT status FROM contact_enquiries WHERE id=1')->fetchColumn() !== 'in_progress' ||
    ContactEnquiries::process($pdo,static fn()=>throw new LogicException('Retried too soon')) !== 0) {
    throw new RuntimeException('Transport failure affected the enquiry or ignored backoff.');
}
$pdo->exec('UPDATE contact_email_jobs SET available_at=NOW()');
ContactEnquiries::process($pdo,static function (array $job): string {
    $mail = ContactEnquiries::payload($job,'sender@example.test');
    if ($mail['subject'] !== 'New Haleef website enquiry' || $mail['replyTo']['email'] !== 'logged@example.test' ||
        !str_contains($mail['textContent'],'**not formatting**') ||
        !str_contains($mail['htmlContent'],'&lt;img src=x onerror=alert(1)&gt;') ||
        str_contains($mail['htmlContent'],'<img src=x') || str_contains($mail['htmlContent'],'<strong>not formatting</strong>')) {
        throw new LogicException('Notification did not preserve the queued template or escape visitor content.');
    }
    return 'contact-smoke';
});
if ($pdo->query('SELECT provider_id FROM contact_email_jobs WHERE enquiry_id=1')->fetchColumn() !== 'contact-smoke') {
    throw new RuntimeException('Contact worker result was not recorded.');
}
