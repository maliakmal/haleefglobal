<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

use Haleef\Admin\AdminAuth;
use Haleef\Orders\Invoices;
use Haleef\Support\{Database, WebSession};

Invoices::privateHeaders();
if (!isset($invoiceAdmin, $invoiceReference)) {
    http_response_code(404);
    exit;
}
if ($invoiceAdmin) {
    AdminAuth::requireUser();
    $orderId = $invoiceReference;
} else {
    WebSession::resume();
    $orderId = Invoices::sessionOrderId($invoiceReference);
}
if (!$orderId) {
    http_response_code(404);
    exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
session_write_close();
Invoices::download(Database::connection(), $orderId);
