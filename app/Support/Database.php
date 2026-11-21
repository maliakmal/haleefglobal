<?php
declare(strict_types=1);

namespace Haleef\Support;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = Env::get('DB_HOST');
        $database = Env::get('DB_DATABASE');
        $username = Env::get('DB_USERNAME');
        $password = Env::get('DB_PASSWORD', '');
        $port = Env::int('DB_PORT', 3306);

        if (!$host || !$database || !$username) {
            throw new RuntimeException('Database configuration is incomplete. Copy .env.example to .env and configure DB_* values.');
        }

        try {
            self::$connection = new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to the Haleef database.', 0, $exception);
        }

        return self::$connection;
    }
}

