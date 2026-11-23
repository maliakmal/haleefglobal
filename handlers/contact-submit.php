<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/config.php';

use Haleef\Support\{Analytics, ContactEnquiries, Csrf, Database, WebSession};

$result = 'error=1';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['website'] ?? '') === '') {
    WebSession::start();
    $token = is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null;
    if ($token && hash_equals(Csrf::token(), $token)) {
        $now = time();
        $recent = array_values(array_filter($_SESSION['contact_send_attempts'] ?? [], static fn(int $at): bool => $at > $now - 600));
        if (count($recent) < 3) {
            $_SESSION['contact_send_attempts'] = [...$recent, $now];
            session_write_close();
            try {
                ContactEnquiries::submit(Database::connection(), $_POST, SITE_EMAIL);
                $result = 'sent=1';
                if (Analytics::measurementId() !== '') {
                    WebSession::start();
                    $_SESSION['contact_saved_result'] = true;
                    session_write_close();
                }
            } catch (InvalidArgumentException) {
                // Invalid form data; do not send or log the visitor's details.
            } catch (RuntimeException $error) {
                error_log('[Haleef contact] Could not save enquiry (' . get_class($error) . ').');
            } catch (Throwable) {
                error_log('[Haleef contact] Unexpected enquiry storage error.');
            }
        }
    }
}
header('Location: /contact?' . $result, true, 303);
exit;
