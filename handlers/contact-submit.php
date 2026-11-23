<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/config.php';

use Haleef\Support\ContactEmail;
use Haleef\Support\Csrf;

$result = 'error=1';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['website'] ?? '') === '') {
    $token = is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null;
    if ($token && hash_equals(Csrf::token(), $token)) {
        $now = time();
        $recent = array_values(array_filter($_SESSION['contact_send_attempts'] ?? [], static fn(int $at): bool => $at > $now - 600));
        if (count($recent) < 3) {
            $_SESSION['contact_send_attempts'] = [...$recent, $now];
            session_write_close();
            try {
                ContactEmail::send($_POST, SITE_EMAIL);
                $result = 'sent=1';
            } catch (InvalidArgumentException) {
                // Invalid form data; do not send or log the visitor's details.
            } catch (RuntimeException $error) {
                error_log('[Haleef contact] ' . $error->getMessage());
            } catch (Throwable) {
                error_log('[Haleef contact] Unexpected mail transport error.');
            }
        }
    }
}
header('Location: /contact?' . $result, true, 303);
exit;
