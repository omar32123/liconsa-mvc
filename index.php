<?php
/**
 * index.php — Vista principal del mapa de Lecherías LICONSA Oaxaca
 * La lógica vive en public/assets/app.js (MVC de cliente)
 */

// Cargar la API key de configuración (o definirla directamente)
$gmapsKey = defined('GMAPS_API_KEY') ? GMAPS_API_KEY : 'AIzaSyDYRjq8xldMAKT8f7iyEcY6WfBNGymThqI';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecherías BIENESTAR — Oaxaca</title>

    <!-- Fuentes -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($gmapsKey) ?>&libraries=marker" async defer id="gmaps-script"></script>

    <!-- Estilos -->
    <link rel="stylesheet" href="public/assets/styles1.css">
</head>
<body>

    <!-- ═══ HEADER ═══════════════════════════════════════════════════════ -->
    <header>
        <div class="logo">
            <div class="logo-icon">🥛</div>
            <h1>Lecherías <strong>BIENESTAR</strong></h1>
        </div>
        <div class="header-sep"></div>
        <div class="header-meta">
            <span>Oaxaca,</span>
            <strong>MX</strong>
        </div>
        <div id="status-badge">
            <div id="status-dot"></div>
            <span id="status-text">Conectando...</span>
        </div>
    </header>

    <!-- ═══ BOTÓN MENÚ MÓVIL ══════════════════════════════════════════════ -->
    <button class="menu-fab" id="menuFab" aria-label="Menú">
        <span class="material-icons">menu</span>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ═══ BANNER DE ERROR ═══════════════════════════════════════════════ -->
    <div id="error-banner" hidden></div>

    <!-- ═══ CUERPO PRINCIPAL ══════════════════════════════════════════════ -->
    <div class="app-body">

        <!-- SIDEBAR -->
        <aside id="sidebar">

            <!-- Estadísticas -->
            <div class="sidebar-section">
                <div class="sidebar-title">📊 Estadísticas</div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="num" id="stat-total">—</div>
                        <div class="lbl">Total</div>
                    </div>
                    <div class="stat-card">
                        <div class="num" id="stat-rutas">—</div>
                        <div class="lbl">Rutas</div>
                    </div>
                    <div class="stat-card liquid">
                        <div class="num" id="stat-liquid">—</div>
                        <div class="lbl">Líquida</div>
                    </div>
                    <div class="stat-card polvo">
                        <div class="num" id="stat-polvo">—</div>
                        <div class="lbl">Polvo</div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="sidebar-section">
                <div class="sidebar-title">🔍 Filtros</div>
                <div class="search-container">
                    <input
                        type="text"
                        id="search-input"
                        placeholder="Buscar lechería, ruta, calle..."
                        autocomplete="off"
                    />
                </div>
                <div class="tipo-btns">
                    <button id="btn-liquid" class="tipo-btn active-liquid" data-tipo="liquid">💧 Líquida</button>
                    <button id="btn-polvo"  class="tipo-btn active-polvo"  data-tipo="polvo">📦 Polvo</button>
                </div>
            </div>

            <!-- Rutas -->
            <div class="sidebar-section routes-section">
                <div class="sidebar-title">🛣️ Rutas</div>
                <button class="toggle-all-btn" id="btn-toggle-all">👁 Ver Todas</button>
                <div id="routes-list"></div>
            </div>

        </aside>

        <!-- MAPA -->
        <div id="map"></div>

        <!-- ═══ ✅ PANEL LATERAL DE DETALLES ════════════════════════════════════ -->
        <div id="detail-panel" class="detail-panel">
            <div class="detail-header">
                <h3 id="detail-nombre">Cargando...</h3>
                <button id="detail-close" class="detail-close-btn" aria-label="Cerrar detalles">✕</button>
            </div>
            <div id="detail-loading" style="text-align:center;padding:40px;">
                <div style="display:inline-block;width:40px;height:40px;border:4px solid #f3f3f3;border-top:4px solid #3498db;border-radius:50%;animation:spin 1s linear infinite;"></div>
                <p style="margin-top:15px;color:#6c757d;">Cargando información...</p>
            </div>
            <div id="detail-content" style="display:none;padding:0 20px 20px;"></div>
        </div>

        <!-- Overlay para cerrar el panel -->
        <div id="detail-overlay" class="detail-overlay"></div>

    </div>
    <!-- ═══ FIN CUERPO PRINCIPAL ══════════════════════════════════════════ -->

    <!-- ═══ LOADING OVERLAY ═══════════════════════════════════════════════ -->
    <div id="loading">
        <div class="loading-card">
            <div class="spinner"></div>
            <p>Cargando lecherías…</p>
        </div>
    </div>

    <!-- ═══ APP JAVASCRIPT (MVC) ══════════════════════════════════════════ -->
    <script src="public/assets/app1.js"></script>

</body>
</html>