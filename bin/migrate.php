<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

use Haleef\Support\Database;

$pdo = Database::connection();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(190) PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

foreach (glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [] as $file) {
    $version = basename($file);
    if (in_array($version, $applied, true)) {
        echo "Already applied: {$version}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Unable to read migration {$version}");
    }

    try {
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
        foreach ($statements as $sqlStatement) {
            if (trim($sqlStatement) !== '') {
                $pdo->exec($sqlStatement);
            }
        }
        $statement = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $statement->execute([$version]);
        echo "Applied: {$version}\n";
    } catch (Throwable $exception) {
        throw $exception;
    }
}

