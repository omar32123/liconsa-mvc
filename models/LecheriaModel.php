<?php
/**
 * models/LecheriaModel.php — Acceso a datos de la tabla LECHERIA
 *
 * Responsabilidades:
 *   - Encapsular todas las consultas SQL relacionadas con LECHERIA
 *   - Normalizar los datos antes de devolverlos (tipos, charset)
 *   - NO saber nada de HTTP ni de caché
 */
declare(strict_types=1);

class LecheriaModel
{
    public function __construct(private readonly PDO $pdo) {}

    // ── Filtros válidos para el query principal ────────────────────────────
    private const TIPO_MAP = [
        'LIQUIDA' => 1,
        'POLVO'   => 3,
    ];

    // ── Lecherías activas con coordenadas válidas ──────────────────────────
    /**
     * @param int|null    $idRuta  Filtrar por CLAVE_RUTA
     * @param string|null $ambito  'LIQUIDA' | 'POLVO' | null
     * @return array<int, array<string, mixed>>
     */
    public function getActivas(?int $idRuta = null, ?string $ambito = null): array
    {
        $sql = "
            SELECT
                L.LECHER            AS id_lecheria,
                L.NOMBRELECH        AS nombre,
                L.LATITUD           AS latitud,
                L.LONGITUD          AS longitud,
                L.ML_TVENTA         AS tipo_venta,
                L.CLAVE_RUTA        AS id_ruta,
                R.DESCRIPCION       AS nombre_ruta,
                L.EN_OPERACION      AS en_operacion,
                L.TIPO_PUNTO_VENTA  AS tipo_punto_venta,
                L.ML_CALLE          AS calle,
                L.ML_COLO           AS colonia
            FROM
                LECHERIA L
                LEFT JOIN RUTA R ON R.CLAVE_RUTA = L.CLAVE_RUTA
            WHERE
                L.LATITUD  IS NOT NULL AND L.LONGITUD IS NOT NULL
                AND L.LATITUD  <> 0   AND L.LONGITUD <> 0
                AND L.LATITUD  BETWEEN 15.0 AND 18.5
                AND L.LONGITUD BETWEEN -98.5 AND -93.5
                AND L.EN_OPERACION = 0
        ";

        $params = [];

        if ($idRuta !== null) {
            $sql     .= " AND L.CLAVE_RUTA = ?";
            $params[] = $idRuta;
        }

        if ($ambito !== null && isset(self::TIPO_MAP[$ambito])) {
            $sql     .= " AND L.ML_TVENTA = ?";
            $params[] = self::TIPO_MAP[$ambito];
        }

        $sql .= " ORDER BY R.DESCRIPCION, L.NOMBRELECH";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'normalizeRow'], $rows);
    }

    // ── Diagnóstico ────────────────────────────────────────────────────────
    public function getDebugInfo(): array
    {
        $info = [];

        $info['total']              = (int) $this->scalar("SELECT COUNT(*) AS N FROM LECHERIA");
        $info['en_operacion_0']     = (int) $this->scalar("SELECT COUNT(*) AS N FROM LECHERIA WHERE EN_OPERACION = 0");
        $info['con_coords_validas'] = (int) $this->scalar("
            SELECT COUNT(*) AS N FROM LECHERIA
            WHERE EN_OPERACION = 0
              AND LATITUD <> 0 AND LONGITUD <> 0
              AND LATITUD BETWEEN 15.0 AND 18.5
              AND LONGITUD BETWEEN -98.5 AND -93.5
        ");
        $info['ml_tventa_1_liquida'] = (int) $this->scalar("SELECT COUNT(*) AS N FROM LECHERIA WHERE ML_TVENTA = 1");
        $info['ml_tventa_3_polvo']   = (int) $this->scalar("SELECT COUNT(*) AS N FROM LECHERIA WHERE ML_TVENTA = 3");
        $info['vals_en_operacion']   = array_column(
            $this->pdo->query("SELECT DISTINCT EN_OPERACION FROM LECHERIA ROWS 10")->fetchAll(),
            'EN_OPERACION'
        );
        $info['vals_ml_tventa']      = array_column(
            $this->pdo->query("SELECT DISTINCT ML_TVENTA FROM LECHERIA ROWS 10")->fetchAll(),
            'ML_TVENTA'
        );

        $rng = $this->pdo->query("
            SELECT MIN(LATITUD) A, MAX(LATITUD) B, MIN(LONGITUD) C, MAX(LONGITUD) D
            FROM LECHERIA WHERE LATITUD <> 0
        ")->fetch();
        $info['rango_coords'] = [
            'lat_min' => $rng['A'], 'lat_max' => $rng['B'],
            'lng_min' => $rng['C'], 'lng_max' => $rng['D'],
        ];

        $info['muestra_3'] = $this->pdo->query("
            SELECT FIRST 3 LECHER, NOMBRELECH, LATITUD, LONGITUD, ML_TVENTA, EN_OPERACION
            FROM LECHERIA WHERE LATITUD <> 0 AND LONGITUD <> 0
        ")->fetchAll();

        return $info;
    }

    // ── Normalizar fila ────────────────────────────────────────────────────
    private function normalizeRow(array $row): array
    {
        // Firebird devuelve claves MAYÚSCULAS
        $row = array_change_key_case($row, CASE_LOWER);

        $row['latitud']  = (float)($row['latitud']  ?? 0);
        $row['longitud'] = (float)($row['longitud'] ?? 0);

        $tipoVenta = (int)($row['tipo_venta'] ?? 0);
        $row['tipo_venta'] = match ($tipoVenta) {
            1       => 'LIQUIDA',
            3       => 'POLVO',
            default => 'DESCONOCIDO',
        };

        $row['nombre']      = $this->clean((string)($row['nombre']      ?? ''));
        $row['nombre_ruta'] = $this->clean((string)($row['nombre_ruta'] ?? 'Sin ruta'));
        $row['calle']       = $this->clean((string)($row['calle']       ?? ''));
        $row['colonia']     = $this->clean((string)($row['colonia']      ?? ''));

        return $row;
    }

    // ── Helper: scalar query ───────────────────────────────────────────────
    private function scalar(string $sql): mixed
    {
        $row = $this->pdo->query($sql)->fetch();
        return reset($row);
    }

    // ── Helper: limpiar string para json_encode ───────────────────────────
    private function clean(string $s): string
    {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
        return trim($s);
    }

    // ── Obtener información detallada de una lechería ──────────────────────────
/**
 * @param int $idLecheria ID de la lechería
 * @return array<string, mixed>|null Datos detallados o null si no existe
 */
  

// ── Obtener información detallada de una lechería (SOLO TABLA LECHERIA) ──
public function getDetalleLecheria(int $idLecheria): ?array
{
    $sql = "
        SELECT 
            L.LECHER AS id_lecheria,
            L.NOMBRELECH AS nombre,
            L.CLAVE_RUTA AS id_ruta,
            R.DESCRIPCION AS nombre_ruta,
            RR.DESCRIPCION AS ruta_distribucion,
            C.DESCRIPCION AS tipo_lecheria,
            L.ML_CALLE AS calle,
            L.ML_COLO AS colonia,
            L.ML_REFE AS referencia,
            L.CPOSTAL AS codigo_postal,
            L.LATITUD AS latitud,
            L.LONGITUD AS longitud,
            L.ML_TVENTA AS tipo_venta,
            -- Estadísticas principales
            L.CL_BEN AS total_beneficiarios,
            L.CC_FAM AS total_familias,
            L.CC_BT1 AS ninos,
            L.CC_BT4 AS adultos_mayores,
            L.CC_BT2 AS madres_gestacion,
            L.CC_BT6 AS madres_lactancia,
            L.CC_BT3 AS enfermedades_cronicas,
            L.CC_BT5 AS adolescentes,
            L.CC_BT7 AS mujeres_45_59,
            L.MARGLECH AS nivel_marginacion,
            -- Información adicional
            L.PROMOTOR AS promotor_id,
            P.PMT_NOMBRE AS promotor_nombre,
            CON.NOMBRE AS concesionario_nombre,
            L.ALMACEN_RURAL AS almacen,
            L.TELEFONO_LECHERIA AS telefono,
            L.TMATINI AS horario_inicio,
            L.TMATFIN AS horario_fin
        FROM 
            LECHERIA L
            LEFT JOIN RUTA R ON R.CLAVE_RUTA = L.CLAVE_RUTA
            LEFT JOIN RUTA_REPARTIDOR RR ON RR.CLAVE_RUTA_REPARTIDOR = R.CLAVE_RUTA_REPARTIDOR
            LEFT JOIN CONTRATO C ON C.CLAVE_CONTRATO = L.TIPO_CONTR
            LEFT JOIN PROMOTOR P ON P.PMT_NUMERO = L.PROMOTOR
            LEFT JOIN CONCESIONARIO CON ON CON.CLAVE_CONCESIONARIO = L.CLAVE_CONCESIONARIO
        WHERE 
            L.LECHER = ?
    ";
    
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idLecheria]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            error_log("No se encontró lechería con ID: " . $idLecheria);
            return null;
        }
        
        // Valores por defecto
        if (empty($row['PROMOTOR_NOMBRE'])) {
            $row['PROMOTOR_NOMBRE'] = 'No asignado';
        }
        if (empty($row['RUTA_DISTRIBUCION'])) {
            $row['RUTA_DISTRIBUCION'] = 'No asignada';
        }
        if (empty($row['CONCESIONARIO_NOMBRE'])) {
            $row['CONCESIONARIO_NOMBRE'] = 'No asignado';
        }
        if (empty($row['TIPO_LECHERIA'])) {
            $row['TIPO_LECHERIA'] = 'No especificado';
        }
        
        return $this->normalizeDetalleRow($row);
    } catch (PDOException $e) {
        error_log("Error en getDetalleLecheria: " . $e->getMessage());
        return null;
    }
}

// ── Normalizar fila detallada ──────────────────────────────────────────────
// ── Normalizar fila detallada ──────────────────────────────────────────────
private function normalizeDetalleRow(array $row): array
{
    $row = array_change_key_case($row, CASE_LOWER);
    
    // Convertir tipos numéricos
    $row['total_beneficiarios'] = (int)($row['total_beneficiarios'] ?? 0);
    $row['total_familias'] = (int)($row['total_familias'] ?? 0);
    $row['ninos'] = (int)($row['ninos'] ?? 0);
    $row['adultos_mayores'] = (int)($row['adultos_mayores'] ?? 0);
    $row['madres_gestacion'] = (int)($row['madres_gestacion'] ?? 0);
    $row['madres_lactancia'] = (int)($row['madres_lactancia'] ?? 0);
    $row['enfermedades_cronicas'] = (int)($row['enfermedades_cronicas'] ?? 0);
    $row['adolescentes'] = (int)($row['adolescentes'] ?? 0);
    $row['mujeres_45_59'] = (int)($row['mujeres_45_59'] ?? 0);
    $row['nivel_marginacion'] = (int)($row['nivel_marginacion'] ?? 0);
    $row['latitud'] = (float)($row['latitud'] ?? 0);
    $row['longitud'] = (float)($row['longitud'] ?? 0);
    $row['promotor_id'] = (int)($row['promotor_id'] ?? 0);
    
    // Limpiar strings
    $row['nombre'] = $this->clean((string)($row['nombre'] ?? ''));
    $row['nombre_ruta'] = $this->clean((string)($row['nombre_ruta'] ?? 'Sin ruta'));
    $row['ruta_distribucion'] = $this->clean((string)($row['ruta_distribucion'] ?? 'No asignada'));
    $row['promotor'] = $this->clean((string)($row['promotor_nombre'] ?? 'No asignado'));
    $row['concesionario'] = $this->clean((string)($row['concesionario_nombre'] ?? 'No asignado'));
    $row['calle'] = $this->clean((string)($row['calle'] ?? ''));
    $row['colonia'] = $this->clean((string)($row['colonia'] ?? ''));
    $row['referencia'] = $this->clean((string)($row['referencia'] ?? ''));
    $row['codigo_postal'] = $this->clean((string)($row['codigo_postal'] ?? ''));
    $row['almacen'] = $this->clean((string)($row['almacen'] ?? 'No asignado'));
    $row['tipo_lecheria'] = $this->clean((string)($row['tipo_lecheria'] ?? 'No especificado'));
    $row['telefono'] = $this->clean((string)($row['telefono'] ?? 'No disponible'));
    
    // Mapeo de nivel de marginación
    $marginacionMap = [
        1 => 'Muy bajo',
        2 => 'Bajo',
        3 => 'Medio',
        4 => 'Alto',
        5 => 'Muy alto'
    ];
    $row['nivel_marginacion_texto'] = $marginacionMap[$row['nivel_marginacion']] ?? 'No disponible';
    
    // Formatear horario
    $inicio = trim((string)($row['horario_inicio'] ?? ''));
    $fin = trim((string)($row['horario_fin'] ?? ''));
    $row['horario_atencion'] = ($inicio && $fin) ? "$inicio - $fin" : 'No disponible';
    
    $tipoVenta = (int)($row['tipo_venta'] ?? 0);
    $row['tipo_venta'] = match ($tipoVenta) {
        1       => 'LIQUIDA',
        3       => 'POLVO',
        default => 'DESCONOCIDO',
    };
    
    return $row;
}

}
