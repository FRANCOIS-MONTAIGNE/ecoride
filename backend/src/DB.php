<?php
// backend/src/DB.php

declare(strict_types=1);

namespace App\DB;

use PDO;
use PDOException;

final class DB
{
    private static ?PDO $pdo = null;

    /**
     * Renvoie une instance PDO connectée à la base de données.
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        // Lecture de la configuration depuis config.php
        $config = require __DIR__ . '/config.php';

        if (!is_array($config) || !isset($config['db']) || !is_array($config['db'])) {
            throw new \RuntimeException('Clé "db" manquante dans config.php');
        }

        $db = $config['db'];

        $host    = $db['host']    ?? '127.0.0.1';
        $port    = $db['port']    ?? 3306;
        $name    = $db['name']    ?? 'ecoride_db';
        $charset = $db['charset'] ?? 'utf8';    // utf8mb4 si besoin de support des emojis
        $user    = $db['user']    ?? 'root';
        $pass    = (string)($db['pass'] ?? '');

        // Construction du DSN et création de l'instance PDO  
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'ok'      => false,
                'error'   => 'DB error',
                'details' => $e->getMessage(),
            ]);
            exit;
        }

        return self::$pdo;
    }

    /**
     * Alias pour getConnection()
     */
    public static function pdo(): PDO
    {
        return self::getConnection();
    }
}
