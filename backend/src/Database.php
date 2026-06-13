<?php

declare(strict_types=1);

namespace AbqDog;

use PDO;

final class Database
{
    public static function connect(): PDO
    {
        $pdo = new PDO('sqlite:' . Config::databasePath());
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        return $pdo;
    }
}
