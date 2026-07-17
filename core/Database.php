<?php
/**
 * core/Database.php — Singleton PDO Firebird
 * Detecta automáticamente servidor real (LICONSA) vs Docker local
 */
declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;
    private static string $origin  = 'Desconocido';
    private static ?string $lastError = null;

    // ── Configuración de entornos ──────────────────────────────────────────
    private const REMOTE = [
        'host'   => '172.24.10.251',
        'port'   => 3050,
        'dbname' => 'C:/SisDLL20/BD/DB_SIDIST.FDB',
        'user'   => 'SYSDBA',
        'pass'   => '290990',
        'label'  => 'SERVIDOR REAL (LICONSA)',
    ];

    private function __construct() {}

    public static function getInstance(): ?PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $cfg = self::detectConfig();
        self::$origin = $cfg['label'];

        try {
            $dsn = "firebird:dbname={$cfg['host']}/{$cfg['port']}:{$cfg['dbname']};charset=UTF8";
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$instance = $pdo;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            self::$instance  = null;
        }

        return self::$instance;
    }

    public static function getOrigin(): string    { return self::$origin;    }
    public static function getLastError(): ?string { return self::$lastError; }

    // ── Detectar servidor disponible (timeout 2 s) ─────────────────────────
    private static function detectConfig(): array
    {
        $cfg  = self::REMOTE;
        $sock = @fsockopen($cfg['host'], $cfg['port'], $errno, $errstr, 2);
        if ($sock) {
            fclose($sock);
            return $cfg;
        }
        return self::LOCAL;
    }
}
