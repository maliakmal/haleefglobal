<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

use Haleef\Support\Env;

$database = Env::get('DB_DATABASE');
$username = Env::get('DB_USERNAME');
$password = Env::get('DB_PASSWORD', '') ?? '';
$host = Env::get('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
$port = Env::int('DB_PORT', 3306);

if (!$database || !$username) {
    throw new RuntimeException('Database backup configuration is incomplete.');
}

$backupRoot = Env::get('BACKUP_STORAGE_PATH', 'storage/backups') ?: 'storage/backups';
if (!str_starts_with($backupRoot, '/')) {
    $backupRoot = dirname(__DIR__) . '/' . $backupRoot;
}
if (!is_dir($backupRoot) && !mkdir($backupRoot, 0750, true) && !is_dir($backupRoot)) {
    throw new RuntimeException('Unable to prepare database backup storage.');
}

$filename = sprintf('%s/haleef-%s-%s.sql.gz', rtrim($backupRoot, '/'), date('Ymd-His'), bin2hex(random_bytes(3)));
$gzip = gzopen($filename, 'wb9');
if ($gzip === false) {
    throw new RuntimeException('Unable to create the compressed database backup.');
}

$command = [
    'mysqldump',
    '--host=' . $host,
    '--port=' . $port,
    '--user=' . $username,
    '--single-transaction',
    '--quick',
    '--triggers',
    '--default-character-set=utf8mb4',
    $database,
];
$descriptors = [
    0 => ['file', '/dev/null', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$currentEnvironment = getenv();
$environment = array_merge(is_array($currentEnvironment) ? $currentEnvironment : [], ['MYSQL_PWD' => $password]);
$process = proc_open($command, $descriptors, $pipes, null, $environment);
if (!is_resource($process)) {
    gzclose($gzip);
    @unlink($filename);
    throw new RuntimeException('Unable to start mysqldump.');
}

try {
    while (!feof($pipes[1])) {
        $chunk = fread($pipes[1], 1024 * 1024);
        if ($chunk === false) {
            throw new RuntimeException('Unable to read the database dump.');
        }
        if ($chunk !== '' && gzwrite($gzip, $chunk) === false) {
            throw new RuntimeException('Unable to write the compressed database backup.');
        }
    }
    fclose($pipes[1]);
    $errorOutput = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);
    gzclose($gzip);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        @unlink($filename);
        throw new RuntimeException('mysqldump failed: ' . trim($errorOutput));
    }
} catch (Throwable $exception) {
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    if (is_resource($gzip)) {
        gzclose($gzip);
    }
    @unlink($filename);
    throw $exception;
}

chmod($filename, 0600);
$retentionDays = max(1, Env::int('BACKUP_RETENTION_DAYS', 14));
$deleteBefore = time() - ($retentionDays * 86400);
foreach (glob(rtrim($backupRoot, '/') . '/haleef-*.sql.gz') ?: [] as $oldBackup) {
    if (is_file($oldBackup) && filemtime($oldBackup) < $deleteBefore) {
        unlink($oldBackup);
    }
}

echo "Database backup created: {$filename}\n";
