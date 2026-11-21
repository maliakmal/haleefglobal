<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

use Haleef\Support\Database;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

try {
    Database::connection()->query('SELECT 1')->fetchColumn();
    http_response_code(200);
    echo json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(503);
    echo json_encode(['status' => 'unavailable'], JSON_THROW_ON_ERROR);
}
