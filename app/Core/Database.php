<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/** Conexão PDO única com MariaDB (driver pdo_mysql). */
final class Database
{
    private static ?PDO $pdo = null;

    /** Usado no start do container: espera o MariaDB ficar pronto (CLI). */
    public static function waitUntilReady(int $tries = 40): void
    {
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
        for ($i = 1; $i <= $tries; $i++) {
            try {
                new PDO($dsn, $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                return;
            } catch (PDOException $e) {
                fwrite(STDERR, "Aguardando o banco ({$i}/{$tries})...\n");
                sleep(2);
            }
        }
        fwrite(STDERR, "Não foi possível conectar ao banco: " . $e->getMessage() . "\n");
        exit(1);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = config('db');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
            try {
                self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'", // mesmo fuso do PHP (Brasília)
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                exit(config('debug') ? 'Erro de conexão com o banco: ' . e($e->getMessage()) : 'Erro interno.');
            }
        }
        return self::$pdo;
    }
}
