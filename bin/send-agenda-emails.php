<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';

use Haleef\Support\Database;
use Haleef\Support\ContactEnquiries;
use Haleef\Training\AgendaEmail;

if (in_array('--check', $argv, true)) {
    if (!AgendaEmail::configured()) { fwrite(STDERR, "Agenda email is not configured.\n"); exit(1); }
    echo "Agenda email settings are present. No mail sent.\n";
    exit;
}
try {
    $pdo = Database::connection();
    try {
        $count = AgendaEmail::configured() ? (new AgendaEmail($pdo))->process([AgendaEmail::class,'sendBrevo']) : 0;
    } finally {
        ContactEnquiries::process($pdo);
    }
    if ($count) echo gmdate('c') . " Attempted $count agenda email job(s).\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Agenda email worker failed; check database connectivity and migrations.\n");
    exit(1);
}
