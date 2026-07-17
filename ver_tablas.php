<?php
/**
 * ver_tablas.php - Lista todas las tablas de la base de datos Firebird
 */

// Configurar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cargar la conexión
require_once 'core/Database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Ver Tablas - LICONSA</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f0f0f0; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #0066CC; }
        .error { background: #ffebee; padding: 15px; border-left: 4px solid #c62828; }
        .success { background: #e8f5e9; padding: 15px; border-left: 4px solid #2e7d32; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #0066CC; color: white; padding: 10px; text-align: left; }
        td { padding: 8px 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f5f5f5; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🗄️ Tablas en inventario.FDB</h1>";

try {
    $pdo = Database::getInstance();
    
    if (!$pdo) {
        throw new Exception("No se pudo conectar: " . Database::getLastError());
    }
    
    echo "<div class='success'>✅ Conectado exitosamente</div>";
    
    // Consulta para listar tablas
    $sql = "
        SELECT 
            RDB\$RELATION_NAME AS nombre
        FROM RDB\$RELATIONS 
        WHERE RDB\$SYSTEM_FLAG = 0 
          AND RDB\$RELATION_NAME NOT LIKE '%\$%'
          AND RDB\$RELATION_NAME NOT LIKE '%IDX%'
        ORDER BY RDB\$RELATION_NAME
    ";
    
    $stmt = $pdo->query($sql);
    $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tablas)) {
        echo "<p>No se encontraron tablas.</p>";
    } else {
        echo "<table>";
        echo "<thead><tr><th>#</th><th>Nombre de la Tabla</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($tablas as $i => $tabla) {
            $nombre = trim($tabla);
            echo "<tr>";
            echo "<td>" . ($i + 1) . "</td>";
            echo "<td><strong>" . htmlspecialchars($nombre) . "</strong></td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
        echo "<p><strong>Total:</strong> " . count($tablas) . " tablas</p>";
    }
    
    echo "<hr>";
    echo "<h2>🔍 Buscar tablas por nombre</h2>";
    echo "<p>Busca tablas que contengan: LECHERIA, RUTA, BENEFICIO, FAMILIA, PRECIO, USUARIO, ENCARGADO, CONTACTO, HORARIO</p>";
    echo "<ul>";
    foreach (['LECHERIA', 'RUTA', 'BENEFICIO', 'FAMILIA', 'PRECIO', 'USUARIO', 'ENCARGADO', 'CONTACTO', 'HORARIO'] as $busqueda) {
        $encontradas = array_filter($tablas, function($t) use ($busqueda) {
            return stripos($t, $busqueda) !== false;
        });
        if (!empty($encontradas)) {
            echo "<li><strong>$busqueda:</strong> " . implode(', ', array_map('trim', $encontradas)) . "</li>";
        }
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Verifica:</strong></p>";
    echo "<ul>";
    echo "<li>La ruta en <code>core/Database.php</code> apunte a <code>database/inventario.FDB</code></li>";
    echo "<li>Que el archivo <code>database/inventario.FDB</code> exista</li>";
    echo "<li>Que el driver <code>pdo_firebird</code> esté instalado</li>";
    echo "</ul>";
    echo "</div>";
}

echo "</div></body></html>";
?>