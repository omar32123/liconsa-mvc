<?php
/**
 * controllers/LecheriaController.php
 *
 * Responsabilidades:
 *   - Leer parámetros HTTP ($_GET)
 *   - Decidir si usar caché o ir a la BD
 *   - Delegar al Model
 *   - Devolver respuesta JSON via Response
 */
declare(strict_types=1);

class LecheriaController
{
    private LecheriaModel $model;

    public function __construct()
    {
        $pdo = Database::getInstance();

        if ($pdo === null) {
            Response::error(
                'Sin conexión a Firebird: ' . (Database::getLastError() ?? 'desconocido'),
                503,
                ['origen' => Database::getOrigin()]
            );
        }

        $this->model = new LecheriaModel($pdo);
    }

    private function detalle(int $id): void{
        // LOG para depurar
    error_log("=== DEBUG: Solicitando detalle para ID: $id ===");
    
    $cacheKey = "lecheria_detalle_{$id}";
    
    $cached = Cache::get($cacheKey);
    if ($cached !== null) {
        Response::json(array_merge($cached, ['cache' => true]));
        return;
    }
    
    try {
        $detalle = $this->model->getDetalleLecheria($id);
        
        // LOG del resultado
        error_log("DEBUG: Resultado de getDetalleLecheria: " . ($detalle ? 'OK' : 'NULL'));
        
        if ($detalle === null) {
            Response::error(
                'Punto de venta no encontrado',
                404,
                ['id' => $id]
            );
            return;
        }
        
        $payload = [
            'origen' => Database::getOrigin(),
            'data'   => $detalle,
            'cache'  => false,
        ];
        
        Cache::set($cacheKey, $payload, 600);
        Response::json($payload);
        
    } catch (PDOException $e) {
        error_log("DEBUG ERROR: " . $e->getMessage());
        Response::error(
            'Error al obtener detalles: ' . $e->getMessage(),
            500,
            ['sql_hint' => 'Verifica la estructura de las tablas relacionadas', 'origen' => Database::getOrigin()]
        );
    }
    }
    
    // ── Dispatcher principal ───────────────────────────────────────────────
    public function handle(): void
    {
        if (isset($_GET['debug'])) {
            $this->debug();
            return;
        }

        if (isset($_GET['cache']) && $_GET['cache'] === 'flush') {
            Cache::flush();
            Response::json(['ok' => true, 'message' => 'Caché limpiada']);
        }

        if (isset($_GET['detalle']) && is_numeric($_GET['detalle'])) {
            $this->detalle((int)$_GET['detalle']);
            return;
        }

        $this->index();
    }

    // ── GET /api.php → listado principal (con caché) ───────────────────────
    private function index(): void
    {
        $idRuta = isset($_GET['ruta']) && $_GET['ruta'] !== ''
            ? (int) $_GET['ruta']
            : null;

        $ambito = isset($_GET['ambito'])
            ? strtoupper(trim($_GET['ambito']))
            : null;

        if ($ambito !== null && !in_array($ambito, ['LIQUIDA', 'POLVO'], true)) {
            $ambito = null;
        }

        // Clave única para esta combinación de filtros
        $cacheKey = 'lecherias_'
            . ($idRuta  !== null ? "ruta{$idRuta}"  : 'all')
            . '_'
            . ($ambito  !== null ? $ambito           : 'all');

        // ── Intentar desde caché ──────────────────────────────────────────
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Response::json(array_merge($cached, ['cache' => true]));
        }

        // ── Consulta real ─────────────────────────────────────────────────
        try {
            $rows = $this->model->getActivas($idRuta, $ambito);
        } catch (PDOException $e) {
            Response::error(
                'Error en consulta: ' . $e->getMessage(),
                500,
                ['sql_hint' => 'Abre /api.php?debug=1 para diagnóstico', 'origen' => Database::getOrigin()]
            );
        }

        $payload = [
            'origen' => Database::getOrigin(),
            'total'  => count($rows),
            'data'   => $rows,
            'cache'  => false,
        ];

        // Guardar en caché 5 minutos (datos de lecherías no cambian a menudo)
        Cache::set($cacheKey, $payload, 300);

        Response::json($payload);
    }

    // ── GET /api.php?debug=1 → diagnóstico (sin caché) ────────────────────
    private function debug(): void
    {
        try {
            $info = $this->model->getDebugInfo();
        } catch (PDOException $e) {
            Response::error('Error de diagnóstico: ' . $e->getMessage());
        }

        Response::json([
            'origen' => Database::getOrigin(),
            'debug'  => $info,
        ]);
    }
}
