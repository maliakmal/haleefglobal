<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

use Haleef\Orders\RegistrationEmail;
use Haleef\Support\Database;
use Haleef\Support\Env;

if (PHP_SAPI !== 'cli' || Env::get('DB_DATABASE') !== 'haleef_test' || Env::get('DB_HOST') !== '127.0.0.1') exit(1);
$pdo = Database::connection();
$pdf = file_get_contents($argv[1]);
if (!str_starts_with($pdf, '%PDF-')) throw new RuntimeException('Expected a real PDF.');

$received = [];
$transport = static function (array $job) use (&$received, $pdf): string {
    $payload = RegistrationEmail::payload($job);
    $email = $payload['to'][0]['email'];
    if (!str_contains($payload['textContent'], 'Advanced Learning Updated') || !str_contains($payload['textContent'], 'HLR-') ||
        str_contains($payload['textContent'], 'zoom.us') || str_contains($payload['textContent'], '/training/booking/') ||
        !str_contains($payload['htmlContent'], '<strong>Advanced Learning Updated</strong>')) {
        throw new LogicException('Registration details were incomplete or disclosed a private URL.');
    }
    if ($email === 'attendee@example.test') {
        if (base64_decode($payload['attachment'][0]['content'] ?? '', true) !== $pdf ||
            str_contains($payload['textContent'], 'casey@example.test')) throw new LogicException('Purchaser invoice mismatch.');
    } elseif ($email !== 'casey@example.test' || isset($payload['attachment']) ||
        str_contains($payload['textContent'], 'attendee@example.test')) {
        throw new LogicException('Delegate email disclosed another recipient or the invoice.');
    }
    $received[] = $email;
    return 'smoke-message';
};
if (RegistrationEmail::process($pdo, $transport) !== 2 || RegistrationEmail::process($pdo, $transport) !== 0 ||
    $received !== ['attendee@example.test', 'casey@example.test']) {
    throw new RuntimeException('Expected each job to be sent just once in normal processing.');
}
