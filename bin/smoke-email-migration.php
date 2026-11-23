<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'haleef_test' || getenv('DB_HOST') !== '127.0.0.1') exit(1);
require dirname(__DIR__) . '/includes/bootstrap.php';

use Haleef\Support\Database;

$pdo = Database::connection();
$snapshot = static function () use ($pdo): array {
    $result = [];
    foreach (['learning_email_templates','learning_agenda_email_jobs','order_registration_email_jobs','contact_enquiries','contact_email_jobs'] as $table) {
        $result[$table] = $pdo->query("SELECT * FROM $table ORDER BY 1")->fetchAll();
    }
    $result['invoices'] = $pdo->query('SELECT order_id,invoice_number,SHA2(pdf,256) FROM order_invoices ORDER BY order_id')->fetchAll();
    return $result;
};
$before = $snapshot();
foreach (preg_split('/;\s*(?:\r?\n|$)/',(string)file_get_contents(dirname(__DIR__).'/database/migrations/010_email_templates_and_contact_enquiries.sql')) as $sql) {
    if (trim($sql) !== '') $pdo->exec($sql);
}
if ($before !== $snapshot()) throw new RuntimeException('Repeating the email migration changed existing data.');
