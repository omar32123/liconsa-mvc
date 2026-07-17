<?php
/**
 * core/Response.php — Envío de respuestas JSON normalizadas
 */
declare(strict_types=1);

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');

        // Limpiar cualquier output previo (warnings de PHP, BOM, etc.)
        if (ob_get_level()) ob_clean();

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        echo $json ?: '{"error":"json_encode falló"}';
        exit;
    }

    public static function error(string $message, int $status = 500, array $extra = []): never
    {
        self::json(array_merge(['error' => $message], $extra), $status);
    }
}
