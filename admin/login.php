<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\AdminAuth;
use Haleef\Support\Csrf;

if (AdminAuth::user()) {
    header('Location: /admin');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::verify($_POST['_csrf'] ?? null);
        if (!AdminAuth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            throw new RuntimeException('The email or password was not recognised.');
        }
        header('Location: /admin');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in | Haleef Learning Console</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="/assets/css/admin.css"></head><body class="admin-login-page"><main class="admin-login"><div class="admin-login-brand"><img src="/assets/images/haleef-logo.webp" alt="Haleef"><span>Learning Console</span></div><h1>Welcome back.</h1><p>Manage programmes, registrations and payments.</p><?php if ($error): ?><div class="admin-alert admin-alert-error"><?= e($error) ?></div><?php endif; ?><form method="post"><?= Csrf::field() ?><label>Email<input type="email" name="email" required autofocus></label><label>Password<input type="password" name="password" required></label><button type="submit">Sign in</button></form><a class="admin-back" href="/">Return to Haleef</a></main></body></html>

