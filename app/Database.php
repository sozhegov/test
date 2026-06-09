<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Фабрика PDO-подключения к MariaDB.
 *
 * Параметры берутся из переменных окружения (DB_HOST, DB_NAME, DB_USER,
 * DB_PASS, DB_PORT) с дефолтами под локальный docker-compose.
 */
final class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::create();
        }

        return self::$instance;
    }

    private static function create(): PDO
    {
        $host = getenv('DB_HOST') ?: 'mariadb';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'test';
        $user = getenv('DB_USER') ?: 'test';
        $pass = getenv('DB_PASS') ?: 'test';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}
