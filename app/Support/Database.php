<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    /**
     * @param array{host:string,port:int,name:string,user:string,password:string,charset:string} $config
     */
    public static function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        try {
            $pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+08:00'",
            ]);
        } catch (PDOException $exception) {
            throw new PDOException('Database connection failed.', (int) $exception->getCode(), $exception);
        }

        return $pdo;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = Config::get('db');
        if (!is_array($config)) {
            throw new PDOException('Database configuration is missing.');
        }

        self::$pdo = self::connect($config);
        return self::$pdo;
    }
}
