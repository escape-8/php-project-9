<?php

namespace Database;

final class ConnectionPostgres
{
    private static ?ConnectionPostgres $conn = null;

    public function connect(): \PDO
    {
        if (isset($_ENV['DATABASE_URL'])) {
            $databaseUrl = parse_url($_ENV['DATABASE_URL']);
            $username = $databaseUrl['user'];
            $password = $databaseUrl['pass'];
            $host = $databaseUrl['host'];
            $port = $databaseUrl['port'];
            $dbName = ltrim($databaseUrl['path'], '/');

            $conStr ="pgsql:host=$host;port=$port;dbname=$dbName;user=$username;password=$password";
            $pdo = new \PDO($conStr);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $pdo;
        }
            $params = parse_ini_file('../database.dev.ini');
            if ($params === false) {
                throw new \Exception("Error reading database configuration file");
            }

            $conStr = sprintf(
                "pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
                $params['host'],
                $params['port'],
                $params['database'],
                $params['user'],
                $params['password']
            );

            $pdo = new \PDO($conStr);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            return $pdo;
    }

    public static function get()
    {
        if (null === static::$conn) {
            static::$conn = new self();
        }

        return static::$conn;
    }

    protected function __construct()
    {
    }
}