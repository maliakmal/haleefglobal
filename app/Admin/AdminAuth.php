<?php
declare(strict_types=1);

namespace Haleef\Admin;

use Haleef\Support\Database;

final class AdminAuth
{
    public static function attempt(string $email, string $password): bool
    {
        $now = time();
        if ((int) ($_SESSION['admin_login_block_until'] ?? 0) > $now) {
            return false;
        }

        $statement = Database::connection()->prepare('SELECT * FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
        $statement->execute([strtolower(trim($email))]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $failures = (int) ($_SESSION['admin_login_failures'] ?? 0) + 1;
            $_SESSION['admin_login_failures'] = $failures;
            if ($failures >= 5) {
                $_SESSION['admin_login_block_until'] = $now + 900;
                $_SESSION['admin_login_failures'] = 0;
            }
            return false;
        }

        session_regenerate_id(true);
        unset($_SESSION['admin_login_failures'], $_SESSION['admin_login_block_until']);
        $_SESSION['admin_user_id'] = (int) $user['id'];
        Database::connection()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        return true;
    }

    public static function user(): ?array
    {
        $id = (int) ($_SESSION['admin_user_id'] ?? 0);
        if ($id < 1) {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT id, name, email, role FROM admin_users WHERE id = ? AND is_active = 1 LIMIT 1');
        $statement->execute([$id]);
        $user = $statement->fetch();
        return $user ?: null;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            header('Location: /admin/login');
            exit;
        }
        return $user;
    }

    public static function logout(): void
    {
        unset($_SESSION['admin_user_id']);
        session_regenerate_id(true);
    }
}

