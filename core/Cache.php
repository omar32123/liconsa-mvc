<?php
/**
 * core/Cache.php — Caché sencilla en archivos JSON
 * Reduce la carga sobre Firebird para peticiones frecuentes.
 *
 * Uso:
 *   $data = Cache::get('lecherias');
 *   if ($data === null) {
 *       $data = ... // consulta costosa
 *       Cache::set('lecherias', $data, 300); // TTL 5 minutos
 *   }
 */
declare(strict_types=1);

class Cache
{
    /** Directorio donde se guardan los archivos .json */
    private static string $dir = __DIR__ . '/../cache/';

    /** TTL por defecto: 5 minutos */
    private const DEFAULT_TTL = 300;

    // ── Leer ──────────────────────────────────────────────────────────────
    public static function get(string $key): mixed
    {
        $file = self::path($key);
        if (!file_exists($file)) {
            return null;
        }

        $raw  = file_get_contents($file);
        $meta = json_decode($raw, true);

        if (!$meta || !isset($meta['expires'], $meta['data'])) {
            return null;
        }

        if (time() > $meta['expires']) {
            @unlink($file);
            return null;
        }

        return $meta['data'];
    }

    // ── Escribir ──────────────────────────────────────────────────────────
    public static function set(string $key, mixed $data, int $ttl = self::DEFAULT_TTL): void
    {
        if (!is_dir(self::$dir)) {
            @mkdir(self::$dir, 0775, true);
        }

        $payload = json_encode([
            'expires' => time() + $ttl,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);

        file_put_contents(self::path($key), $payload, LOCK_EX);
    }

    // ── Invalidar ─────────────────────────────────────────────────────────
    public static function invalidate(string $key): void
    {
        $file = self::path($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    // ── Invalidar todo ────────────────────────────────────────────────────
    public static function flush(): void
    {
        foreach (glob(self::$dir . '*.json') ?: [] as $f) {
            @unlink($f);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    private static function path(string $key): string
    {
        return self::$dir . preg_replace('/[^a-z0-9_\-]/i', '_', $key) . '.json';
    }
}
