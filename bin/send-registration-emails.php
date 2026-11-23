<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';

use Haleef\Orders\Invoices;
use Haleef\Orders\RegistrationEmail;
use Haleef\Support\Database;
use Haleef\Support\ContactEnquiries;

if (in_array('--check', $argv, true)) {
    echo 'Automatic bank-transfer invoices: ' . (Invoices::enabled() ? 'enabled' : 'disabled') . ".\n";
    Invoices::issuer();
    if (!RegistrationEmail::configured()) throw new RuntimeException('Brevo registration sender is not configured.');
    foreach (['dom','gd','mbstring'] as $extension) {
        if (!extension_loaded($extension)) throw new RuntimeException("Missing PHP extension: $extension.");
    }
    require dirname(__DIR__) . '/vendor/autoload.php';
    Database::connection()->query('SELECT id FROM order_registration_email_jobs LIMIT 0');
    echo "Invoice worker prerequisites are present (no email sent).\n";
    exit;
}
$pdo = Database::connection();
try {
    if (Invoices::enabled()) {
        if (!RegistrationEmail::configured()) throw new RuntimeException('Brevo registration sender is not configured.');
        RegistrationEmail::process($pdo, [RegistrationEmail::class,'sendBrevo']);
    }
} finally {
    ContactEnquiries::process($pdo);
}
