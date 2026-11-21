<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

use Haleef\Support\Database;

echo "Admin name: ";
$name = trim((string) fgets(STDIN));
echo "Admin email: ";
$email = strtolower(trim((string) fgets(STDIN)));
echo "Admin password: ";
$canHide = DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec');
if ($canHide) {
    shell_exec('stty -echo');
}
$password = trim((string) fgets(STDIN));
if ($canHide) {
    shell_exec('stty echo');
    echo "\n";
}

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Name, valid email and a password of at least 12 characters are required.\n");
    exit(1);
}

$statement = Database::connection()->prepare(
    "INSERT INTO admin_users (name, email, password_hash, role) VALUES (?, ?, ?, 'administrator')"
);
$statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
echo "Administrator created.\n";

