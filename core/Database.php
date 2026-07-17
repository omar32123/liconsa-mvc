<?php
/**
 * core/Database.php — Singleton PDO Firebird
 * Conexión exclusiva al servidor real (LICONSA)
 */
declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;
    private static string $origin  = 'SERVIDOR REAL (LICONSA)';
    private static ?string $lastError = null;

    // ── Configuración de entorno único ──────────────────────────────────────
    private const REMOTE = [
        'host'   => '172.24.10.251',
        'port'   => 3050,
        'dbname' => 'C:/SisDLL20/BD/DB_SIDIST.FDB',
        'user'   => 'SYSDBA',
        'pass'   => '290990',
    ];

    private function __construct() {}

    public static function getInstance(): ?PDO
    {
        // Si ya hay conexión, la devolvemos inmediatamente
        if (self::$instance !== null) {
            return self::$instance;
        }

        $cfg = self::REMOTE;

        try {
            // Nota: Se cambió a charset=NONE para igualar al proyecto que sí funciona
            $dsn = "firebird:dbname={$cfg['host']}/{$cfg['port']}:{$cfg['dbname']};charset=NONE";
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass']);
            
            $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            self::$instance = $pdo;
            self::$lastError = null; // Limpiamos errores previos si los hubiera
            
        } catch (PDOException $e) {
            // Capturamos el error de forma segura para no romper el JSON del frontend
            self::$lastError = $e->getMessage();
            self::$instance  = null;
        }

        return self::$instance;
    }

    public static function getOrigin(): string    { return self::$origin;    }
    public static function getLastError(): ?string { return self::$lastError; }
}