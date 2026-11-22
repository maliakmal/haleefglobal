<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';

use Haleef\Support\Database;
use Haleef\Training\AgendaEmail;

if (!AgendaEmail::configured()) {
    if (in_array('--check', $argv, true)) fwrite(STDERR, "Agenda email is not configured.\n");
    exit(in_array('--check', $argv, true) ? 1 : 0);
}
if (in_array('--check', $argv, true)) {
    echo "Agenda email settings are present. No mail sent.\n";
    exit;
}
try {
    $count = (new AgendaEmail(Database::connection()))->process([AgendaEmail::class,'sendBrevo']);
    if ($count) echo gmdate('c') . " Attempted $count agenda email job(s).\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Agenda email worker failed; check database connectivity and migrations.\n");
    exit(1);
}
