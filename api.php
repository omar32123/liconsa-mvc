<?php
/**
 * api.php — Punto de entrada del API REST
 *
 * Rutas:
 *   GET /api.php                  → todas las lecherías activas
 *   GET /api.php?ruta=5           → filtrar por ruta
 *   GET /api.php?ambito=LIQUIDA   → filtrar por tipo (LIQUIDA|POLVO)
 *   GET /api.php?debug=1          → diagnóstico de BD
 *   GET /api.php?cache=flush      → limpiar caché manual
 * 
 * error_reporting(E_ALL);
 * ini_set('display_errors', '0');
 * ini_set('log_errors',     '1');
 */

// ── Capturar output previo (BOM, warnings de PHP, etc.) ───────────────────

// Activar errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ── Autoload de clases ────────────────────────────────────────────────────
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Cache.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/models/LecheriaModel.php';
require_once __DIR__ . '/controllers/LecheriaController.php';

// ── Dispatch ──────────────────────────────────────────────────────────────
(new LecheriaController())->handle();
