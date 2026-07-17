<?php
/**
 * ver_lecheria.php — Muestra todos los campos de una lechería específica
 * 
 * USO: ver_lecheria.php?id=2046010200
 */

// Activar errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cargar la conexión a la base de datos
require_once 'core/Database.php';

header('Content-Type: text/html; charset=utf-8');

// Obtener el ID de la lechería
$idLecheria = $_GET['id'] ?? null;

// Si no hay ID, mostrar error
if (!$idLecheria) {
    echo "<h1>❌ Error</h1>";
    echo "<p>Debes especificar un ID de lechería.</p>";
    echo "<p><strong>Uso:</strong> <code>ver_lecheria.php?id=2046010200</code></p>";
    exit;
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Ver Lechería ID: $idLecheria</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background: #f0f2f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0066CC; border-bottom: 3px solid #0066CC; padding-bottom: 10px; margin-top: 0; }
        h2 { color: #333; margin-top: 30px; border-bottom: 2px solid #e0e0e0; padding-bottom: 8px; }
        .info { background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .info strong { color: #0066CC; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { 
            background: #0066CC; 
            color: white; 
            padding: 12px 15px; 
            text-align: left; 
            font-weight: 600;
        }
        td { padding: 10px 15px; border-bottom: 1px solid #e0e0e0; }
        tr:hover { background: #f5f8ff; }
        .campo { font-weight: 600; color: #0066CC; width: 30%; }
        .valor { font-family: 'Courier New', monospace; word-break: break-word; }
        .nulo { color: #999; font-style: italic; }
        .error { background: #ffebee; padding: 15px; border-left: 4px solid #c62828; border-radius: 4px; }
        .success { background: #e8f5e9; padding: 15px; border-left: 4px solid #2e7d32; border-radius: 4px; }
        .total { background: #f8f9fa; padding: 10px; text-align: center; font-weight: bold; margin-top: 15px; border-radius: 4px; }
        .badge { 
            display: inline-block; 
            padding: 3px 10px; 
            border-radius: 12px; 
            font-size: 12px; 
            font-weight: bold; 
        }
        .badge-success { background: #e8f5e9; color: #2e7d32; }
        .badge-danger { background: #ffebee; color: #c62828; }
        .badge-info { background: #e3f2fd; color: #0066CC; }
        .badge-warning { background: #fff3e0; color: #e65100; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = Database::getInstance();
    
    if (!$pdo) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    // Primero, verificar si el ID existe
    $sqlCheck = "SELECT COUNT(*) AS total FROM LECHERIA WHERE LECHER = ?";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$idLecheria]);
    $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($existe['TOTAL'] == 0) {
        echo "<div class='error'>";
        echo "<h2>❌ Lechería no encontrada</h2>";
        echo "<p>No existe una lechería con el ID: <strong>$idLecheria</strong></p>";
        echo "<p><strong>Sugerencia:</strong> Prueba con otro ID.</p>";
        echo "</div>";
        echo "<p><a href='ver_lecheria.php' style='color:#0066CC;'>← Volver</a></p>";
        exit;
    }
    
    // Consulta para obtener TODOS los campos de la lechería
    $sql = "SELECT * FROM LECHERIA WHERE LECHER = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idLecheria]);
    $lecheria = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lecheria) {
        throw new Exception("Error al obtener los datos");
    }
    
    // Mostrar información básica
    echo "<h1>🏪 Lechería ID: $idLecheria</h1>";
    echo "<div class='info'>";
    echo "<strong>Nombre:</strong> " . htmlspecialchars($lecheria['NOMBRELECH'] ?? 'No disponible') . "<br>";
    echo "<strong>Ubicación:</strong> " . htmlspecialchars($lecheria['ML_CALLE'] ?? '') . ", " . htmlspecialchars($lecheria['ML_COLO'] ?? '');
    if (!empty($lecheria['ML_REFE'])) {
        echo " (Ref: " . htmlspecialchars($lecheria['ML_REFE']) . ")";
    }
    echo "<br>";
    echo "<strong>Coordenadas:</strong> " . ($lecheria['LATITUD'] ?? 'N/A') . ", " . ($lecheria['LONGITUD'] ?? 'N/A');
    echo "</div>";
    
    // Mostrar todos los campos en una tabla
    echo "<h2>📋 Todos los campos</h2>";
    echo "<table>";
    echo "<thead><tr><th>#</th><th>Campo</th><th>Valor</th><th>Tipo</th></tr></thead>";
    echo "<tbody>";
    
    $i = 1;
    foreach ($lecheria as $campo => $valor) {
        // Determinar el tipo de dato
        $tipo = gettype($valor);
        $badge = 'badge-info';
        if ($tipo === 'integer' || $tipo === 'double' || $tipo === 'float') {
            $badge = 'badge-success';
        } elseif ($tipo === 'NULL') {
            $badge = 'badge-warning';
        } elseif ($tipo === 'string' && strlen($valor) > 50) {
            $badge = 'badge-danger';
        }
        
        // Formatear el valor para mostrar
        $valorMostrar = $valor;
        if ($valor === null) {
            $valorMostrar = '<span class="nulo">NULL</span>';
        } elseif (is_numeric($valor) && $tipo === 'string') {
            // Si es un número pero viene como string, mantenerlo
            $valorMostrar = htmlspecialchars($valor);
        } elseif (is_string($valor)) {
            $valorMostrar = htmlspecialchars($valor);
        } elseif (is_bool($valor)) {
            $valorMostrar = $valor ? 'true' : 'false';
        } else {
            $valorMostrar = htmlspecialchars((string)$valor);
        }
        
        // Resaltar campos importantes
        $claseCampo = 'campo';
        $campoImportante = '';
        $camposImportantes = [
            'LECHER' => '🔑 ID',
            'NOMBRELECH' => '📛 Nombre',
            'LATITUD' => '📍 Latitud',
            'LONGITUD' => '📍 Longitud',
            'ML_CALLE' => '🏠 Calle',
            'ML_COLO' => '🏘️ Colonia',
            'ML_REFE' => '📍 Referencia',
            'CPOSTAL' => '📮 Código Postal',
            'ML_TVENTA' => '🏷️ Tipo Venta',
            'PROMOTOR' => '👤 Promotor',
            'TELEFONO_LECHERIA' => '📞 Teléfono',
            'CL_BEN' => '👥 Beneficiarios',
            'CC_FAM' => '👨‍👩‍👧‍👦 Familias'
        ];
        
        if (isset($camposImportantes[$campo])) {
            $campoImportante = ' ' . $camposImportantes[$campo];
        }
        
        echo "<tr>";
        echo "<td>" . $i . "</td>";
        echo "<td class='campo'>" . htmlspecialchars($campo) . $campoImportante . "</td>";
        echo "<td class='valor'>" . $valorMostrar . "</td>";
        echo "<td><span class='badge $badge'>" . $tipo . "</span></td>";
        echo "</tr>";
        $i++;
    }
    
    echo "</tbody></table>";
    echo "<div class='total'>Total: " . count($lecheria) . " campos</div>";
    
    // Estadísticas adicionales
    echo "<h2>📊 Estadísticas de beneficiarios</h2>";
    echo "<div style='display:grid;grid-template-columns:repeat(auto-fit, minmax(200px,1fr));gap:10px;margin:15px 0;'>";
    
    $stats = [
        'CL_BEN' => 'Total Beneficiarios',
        'CC_FAM' => 'Total Familias',
        'CC_BT1' => 'Niños (6m-12a)',
        'CC_BT2' => 'Madres Gestación',
        'CC_BT3' => 'Enf. Crónicas/Discapacidad',
        'CC_BT4' => 'Adultos Mayores 60+',
        'CC_BT5' => 'Adolescentes 13-17',
        'CC_BT6' => 'Madres Lactancia',
        'CC_BT7' => 'Mujeres 45-59'
    ];
    
    foreach ($stats as $campo => $label) {
        $valor = $lecheria[$campo] ?? 0;
        echo "<div style='background:#f8f9fa;padding:15px;border-radius:8px;text-align:center;'>";
        echo "<div style='font-size:11px;color:#6c757d;text-transform:uppercase;'>" . htmlspecialchars($label) . "</div>";
        echo "<div style='font-size:24px;font-weight:bold;color:#0066CC;'>" . number_format($valor) . "</div>";
        echo "</div>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "
    <p style='margin-top:20px;color:#666;'>
        <a href='ver_lecheria.php' style='color:#0066CC;'>← Volver</a>
        &nbsp;|&nbsp;
        <a href='api.php?detalle=$idLecheria' target='_blank' style='color:#0066CC;'>Ver en formato JSON</a>
    </p>
</div>
</body>
</html>";
?>