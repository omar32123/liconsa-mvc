<?php
/**
 * ver_tabla.php - Versión ultra simple
 * USO: ver_tabla.php?tabla=LECHERIA
 */

require_once 'core/Database.php';

$tabla = $_GET['tabla'] ?? 'LECHERIA';

try {
    $pdo = Database::getInstance();
    
    if (!$pdo) {
        throw new Exception("Error de conexión");
    }
    
    echo "<h1>Estructura de: $tabla</h1>";
    echo "<pre>";
    
    // Mostrar campos
    $sql = "SELECT FIRST 10 * FROM $tabla";
    $stmt = $pdo->query($sql);
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($datos)) {
        print_r(array_keys($datos[0]));
        echo "\n\n";
        print_r($datos);
    } else {
        echo "No hay datos";
    }
    
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>