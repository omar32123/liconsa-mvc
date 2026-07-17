/**
 * app.js — Mapa Lecherías BIENESTAR · Oaxaca
 *
 * Arquitectura MVC (cliente):
 *   MapModel      → estado y datos
 *   MapView       → DOM y Google Maps
 *   MapController → orquestación, eventos
 *
 * Mejoras de performance:
 *   - Fetch único al arrancar; filtrado 100 % en cliente
 *   - Markers creados una sola vez; visibilidad por .map = null/map
 *   - Debounce en búsqueda (200 ms)
 *   - infoWindow único compartido (no uno por marker)
 */

/* ═══════════════════════════════════════════════════════════════════
   MODEL — estado y datos
═══════════════════════════════════════════════════════════════════ */

const MapModel = (() => {
    
    const ROUTE_PALETTE = [
        '#FF6B6B','#4ECDC4','#45B7D1','#FFA07A','#98D8C8',
        '#6C63FF','#F7DC6F','#BB8FCE','#85C1E2','#F8B88B',
        '#52B788','#FFB6B9','#8B9DC3','#DDA15E','#BC6C25',
    ];

    let _data      = [];          // Todos los registros de la API
    const _routeColors  = {};     // idRuta → color
    const _routeNames   = {};     // idRuta → nombre
    const _routeVisible = {};     // idRuta → bool
    const _tipoVisible  = { liquid: true, polvo: true };
    let _allVisible = true;
    let _searchQuery = '';

    return {
        // ── Carga de datos ─────────────────────────────────────────
        async fetchData() {
            const res = await fetch('api.php');
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            if (json.error) throw new Error(json.error);
            _data = json.data ?? [];
            this.buildRouteIndex();
            return json;
        },

        buildRouteIndex() {
            const ids = [...new Set(_data.map(d => d.id_ruta))].sort();
            ids.forEach((id, i) => {
                _routeColors[id]  = ROUTE_PALETTE[i % ROUTE_PALETTE.length];
                _routeVisible[id] = true;
                _routeNames[id]   = _data.find(d => d.id_ruta === id)?.nombre_ruta ?? `Ruta ${id}`;
            });
        },

        // ── Datos ──────────────────────────────────────────────────
        getData()         { return _data; },
        getRouteColor(id) { return _routeColors[id] ?? '#888'; },
        getRouteName(id)  { return _routeNames[id]  ?? `Ruta ${id}`; },
        getRouteIds()     { return Object.keys(_routeColors); },

        // ── Filtros ────────────────────────────────────────────────
        isRouteVisible(id) { return _routeVisible[id] !== false; },
        isTipoVisible(t)   { return _tipoVisible[t]; },
        getSearch()        { return _searchQuery; },

        setSearch(q)       { _searchQuery = q.toLowerCase().trim(); },

        toggleRoute(id) {
            _routeVisible[id] = !_routeVisible[id];
            return _routeVisible[id];
        },

        toggleAllRoutes() {
            _allVisible = !_allVisible;
            Object.keys(_routeVisible).forEach(id => { _routeVisible[id] = _allVisible; });
            return _allVisible;
        },

        toggleTipo(tipo) {
            _tipoVisible[tipo] = !_tipoVisible[tipo];
            return _tipoVisible[tipo];
        },

        // ── Visibilidad de un registro según filtros activos ───────
        isVisible(d) {
            const routeOk = this.isRouteVisible(d.id_ruta);
            const tipoOk  = (d.tipo_venta === 'LIQUIDA' && _tipoVisible.liquid)
                         || (d.tipo_venta !== 'LIQUIDA' && _tipoVisible.polvo);
            const q = _searchQuery;
            const searchOk = !q
                || d.nombre.toLowerCase().includes(q)
                || (d.nombre_ruta ?? '').toLowerCase().includes(q)
                || (d.calle       ?? '').toLowerCase().includes(q)
                || (d.colonia     ?? '').toLowerCase().includes(q);

            return routeOk && tipoOk && searchOk;
        },

        // ── Stats ──────────────────────────────────────────────────
        getStats() {
            return {
                total  : _data.length,
                rutas  : Object.keys(_routeColors).length,
                liquid : _data.filter(d => d.tipo_venta === 'LIQUIDA').length,
                polvo  : _data.filter(d => d.tipo_venta !== 'LIQUIDA').length,
            };
        },
    };
})();


/* ═══════════════════════════════════════════════════════════════════
   VIEW — DOM + Google Maps
═══════════════════════════════════════════════════════════════════ */
const MapView = (() => {

    let _map           = null;
    let _markers       = [];    // Array de { marker, data }
    let _infoWindow    = null;  // InfoWindow para popup rápido
    let _mapReady      = false;
    let _panelVisible  = false;
    let _currentMarkerId = null;

    // ── Helpers DOM ────────────────────────────────────────────────
    const $  = id => document.getElementById(id);
    const esc = s => String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

    return {
        // ── Mapa ───────────────────────────────────────────────────
        initMap(onReady) {
            const waitForGmaps = () => {
                if (typeof google !== 'undefined' && google.maps && google.maps.Map) {
                    _map = new google.maps.Map($('map'), {
                        zoom: 9,
                        center: { lat: 17.0627, lng: -96.7236 },
                        mapId: 'LICONSA_MAP',
                        zoomControl: true,
                        mapTypeControl: false,
                        streetViewControl: false,
                        fullscreenControl: true,
                        styles: [{ featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] }],
                    });
                    _infoWindow = new google.maps.InfoWindow({ maxWidth: 280 });
                    _mapReady = true;
                    onReady();
                } else {
                    setTimeout(waitForGmaps, 100);
                }
            };
            waitForGmaps();
        },

        // ── Crear markers ──────────────────────────────────────────
        buildMarkers(data, getColor) {
            _markers.forEach(({ marker }) => marker.map = null);
            _markers = [];

            data.forEach(d => {
                const lat = parseFloat(d.latitud);
                const lng = parseFloat(d.longitud);
                if (isNaN(lat) || isNaN(lng)) return;

                const color = getColor(d.id_ruta);
                const forma = d.tipo_venta === 'LIQUIDA' ? 'drop' : 'cube';

                const marker = new google.maps.marker.AdvancedMarkerElement({
                    position: { lat, lng },
                    map: _map,
                    content: this._createMarkerElement(color, forma),
                    title: d.nombre,
                });

                // ✅ MODIFICADO: Ahora abre el panel lateral en lugar de infoWindow
                marker.addListener('click', () => {
                     // Mostrar el popup pequeño
                    _infoWindow.setContent(this._buildPopupContent(d, color));
                    _infoWindow.open({ anchor: marker, map: _map });
            
                    //  También abrir el panel lateral con detalles
                    const id = d.id_lecheria;
                    const lat = parseFloat(d.latitud);
                    const lng = parseFloat(d.longitud);
                    this.mostrarDetallePuntoVenta(id, lat, lng);
                });

                _markers.push({ marker, data: d });
            });
        },

        // ── Mostrar panel lateral con detalles ─────────────────────
        // ── Mostrar panel lateral con detalles ─────────────────────
        async mostrarDetallePuntoVenta(id, lat, lng) {
            const panel = $('detail-panel');
            const content = $('detail-content');
            const nombre = $('detail-nombre');
            const loading = $('detail-loading');
            const overlay = $('detail-overlay');

            //console.log('Datos recibidos:', data);
    
            // Mostrar loading
            loading.style.display = 'block';
            content.style.display = 'none';
            panel.classList.add('open');
            if (overlay) overlay.classList.add('show');
                _panelVisible = true;
                _currentMarkerId = id;
    
            // Actualizar nombre
            const markerData = _markers.find(m => m.data.id_lecheria == id);
            if (markerData) {
                nombre.textContent = markerData.data.nombre || 'Punto de Venta';
            }
    
            try {
                //  IMPORTANTE: Usar 'detalle' no 'detail'
                const response = await fetch(`api.php?detalle=${id}`);
                const result = await response.json();
        
                // Verificar si hay error
                if (result.error) {
                    throw new Error(result.error);
                }
        
                //  Los datos vienen en result.data
                const data = result.data;
                console.log('Datos recibidos:', data); // Para depurar
                
        
                // Renderizar el panel con los datos
                this._renderDetallePanel(data, lat, lng);
        
            } catch (error) {
                console.error('Error cargando detalles:', error);
                content.innerHTML = `
                    <div style="text-align:center;padding:20px;color:#dc3545;">
                        <div style="font-size:40px;margin-bottom:10px;">⚠️</div>
                        <p>Error al cargar los detalles</p>
                        <small style="color:#6c757d;">${esc(error.message)}</small>
                    </div>
                `;
            } finally {
                loading.style.display = 'none';
                content.style.display = 'block';
            }
        },
        // ── Popup InfoWindow (pequeño) ────────────────────────────────
        _buildPopupContent(d, color) {
            const esLiquida = d.tipo_venta === 'LIQUIDA';
            const calle     = (d.calle   ?? '').trim();
            const colonia   = (d.colonia ?? '').trim();
            let dir = '';
            if (calle && colonia) dir = `${calle}, Col. ${colonia}`;
            else if (calle)       dir = calle;
            else if (colonia)     dir = `Col. ${colonia}`;

            const dirRow = dir ? `<div class="popup-row">🏠 <span>${esc(dir)}</span></div>` : '';
    
            const lat = parseFloat(d.latitud);
            const lng = parseFloat(d.longitud);
            const mapsUrl = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}&travelmode=driving`;
            const mapsDirectUrl = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;

            return `<div class="popup-body">
                <div class="popup-row">🆔 ID: <span>${d.id_lecheria}</span></div>
                <div class="popup-name">${esc(d.nombre)}</div>
                <div class="popup-row">🗺️ <span style="color:${color};font-weight:600">${esc(d.nombre_ruta)}</span></div>
                ${dirRow}
                <div class="popup-row">📍 <span>${lat.toFixed(5)}, ${lng.toFixed(5)}</span></div>
                <div><span class="popup-badge ${esLiquida ? 'badge-liquid' : 'badge-polvo'}">${esLiquida ? '💧 Líquida' : '📦 Polvo'}</span></div>
                
            </div>`;
        },

        // ── Renderizar panel de detalles ──────────────────────────
        _renderDetallePanel(data, lat, lng) {
            // Verifica que data no sea undefined
            if (!data) {
                console.error('Error: data es undefined');
                return;
            }
    
            const content = $('detail-content');
    
            const esLiquida = data.tipo_venta === 'LIQUIDA';
            const mapsUrl = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
            const directionsUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`;
    
            const html = `
                <div style="padding:0;">
                    <!-- Badge de tipo -->
                    <div style="margin-bottom:15px;">
                        <span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;background:${esLiquida ? '#e3f2fd' : '#f3e5f5'};color:${esLiquida ? '#1976d2' : '#7b1fa2'}">
                            ${esLiquida ? '💧 LÍQUIDA' : '📦 POLVO'}
                        </span>
                        <span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;background:#e8f5e9;color:#2e7d32;margin-left:8px;">
                            Ruta ${data.id_ruta || 'Sin ruta'}
                        </span>
                    </div>
            
                    <!-- ID de Lechería (nuevo) -->
                    <div style="font-size:13px;color:#6c757d;margin-bottom:15px;">
                        ID: <strong style="color:#333;">${data.id_lecheria}</strong>
                    </div>
            
                    <!-- Estadísticas principales -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:15px;">
                        <div style="background:#f8f9fa;padding:10px;border-radius:8px;text-align:center;">
                            <div style="font-size:10px;color:#6c757d;text-transform:uppercase;letter-spacing:0.5px;">Beneficiarios</div>
                            <div style="font-size:22px;font-weight:bold;color:#007bff;">${data.total_beneficiarios || 0}</div>
                        </div>
                        <div style="background:#f8f9fa;padding:10px;border-radius:8px;text-align:center;">
                            <div style="font-size:10px;color:#6c757d;text-transform:uppercase;letter-spacing:0.5px;">Familias</div>
                            <div style="font-size:22px;font-weight:bold;color:#28a745;">${data.total_familias || 0}</div>
                        </div>
                        <div style="background:#f8f9fa;padding:10px;border-radius:8px;text-align:center;">
                            <div style="font-size:10px;color:#6c757d;text-transform:uppercase;letter-spacing:0.5px;">Niños (6m-12a)</div>
                            <div style="font-size:22px;font-weight:bold;color:#17a2b8;">${data.ninos || 0}</div>
                        </div>
                        <div style="background:#f8f9fa;padding:10px;border-radius:8px;text-align:center;">
                            <div style="font-size:10px;color:#6c757d;text-transform:uppercase;letter-spacing:0.5px;">Adultos Mayores 60+</div>
                            <div style="font-size:22px;font-weight:bold;color:#dc3545;">${data.adultos_mayores || 0}</div>
                        </div>
                    </div>
            
                    <!-- Desglose detallado de tipos de beneficiarios -->
                    <div style="background:#f8f9fa;border-radius:8px;padding:12px;margin-bottom:15px;">
                        <div style="font-size:11px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">📊 Desglose por tipo</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 15px;font-size:13px;">
                            <div style="display:flex;justify-content:space-between;padding:2px 0;">
                                <span style="color:#6c757d;">👶 Madres Gestación</span>
                                <span style="font-weight:600;">${data.madres_gestacion || 0}</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:2px 0;">
                                <span style="color:#6c757d;">🤱 Madres Lactancia</span>
                                <span style="font-weight:600;">${data.madres_lactancia || 0}</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:2px 0;">
                                <span style="color:#6c757d;">🫀 Enf. Crónicas/Discapacidad</span>
                                <span style="font-weight:600;">${data.enfermedades_cronicas || 0}</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:2px 0;">
                                <span style="color:#6c757d;">🧑 Jovenes 13-17</span>
                                <span style="font-weight:600;">${data.adolescentes || 0}</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:2px 0;">
                                <span style="color:#6c757d;">👩 Mujeres 45-59</span>
                                <span style="font-weight:600;">${data.mujeres_45_59 || 0}</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:2px 0;">
                                <span style="color:#6c757d;">📊 Nivel Marginación</span>
                                <span style="font-weight:600;">${data.nivel_marginacion_texto || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
            
                    <!-- Información adicional -->
                    <div style="border-top:1px solid #e9ecef;padding-top:15px;">
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f3f5;">
                            <span style="color:#6c757d;font-size:14px;">👤 Promotor</span>
                            <span style="font-weight:500;font-size:11px;">${esc(data.promotor || 'No asignado')}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f3f5;">
                            <span style="color:#6c757d;font-size:14px;">🏢 Concesionario</span>
                            <span style="font-weight:500;font-size:11px;">${esc(data.concesionario || 'No asignado')}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f3f5;">
                            <span style="color:#6c757d;font-size:14px;">🏪 Almacén</span>
                            <span style="font-weight:500;font-size:14px;">${esc(data.almacen)}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f3f5;">
                        <span style="color:#6c757d;font-size:14px;">📋 Tipo de Lechería</span>
                            <span style="font-weight:500;font-size:14px;">${esc(data.tipo_lecheria || 'No especificado')}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f3f5;">
                            <span style="color:#6c757d;font-size:14px;">🚚 Ruta de Distribución</span>
                            <span style="font-weight:500;font-size:14px;">${esc(data.ruta_distribucion || 'No asignada')}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f3f5;">
                            <span style="color:#6c757d;font-size:14px;">📍 Ubicación</span>
                            <span style="font-weight:500;font-size:14px;text-align:right;max-width:60%;">
                                ${data.calle ? esc(data.calle) : ''}${data.calle && data.colonia ? ', ' : ''}${data.colonia ? esc(data.colonia) : ''}
                            </span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f3f5;">
                            <span style="color:#6c757d;font-size:14px;">📍 Referencia</span>
                            <span style="font-weight:500;font-size:14px;text-align:right;max-width:60%;">${esc(data.referencia || 'N/A')}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f3f5;">
                            <span style="color:#6c757d;font-size:14px;">📮 Código Postal</span>
                            <span style="font-weight:500;font-size:14px;">${esc(data.codigo_postal || 'N/A')}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f3f5;">
                            <span style="color:#6c757d;font-size:14px;">🕐 Horario</span>
                            <span style="font-weight:500;font-size:14px;">${esc(data.horario_atencion)}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;">
                            <span style="color:#6c757d;font-size:14px;">📞 Teléfono</span>
                            <span style="font-weight:500;font-size:14px;">${esc(data.telefono)}</span>
                        </div>
                    </div>
            
                    <!-- Botones de acción -->
                    <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
                        <a href="${mapsUrl}" target="_blank" style="flex:1;background:#4285f4;color:white;padding:10px 15px;border-radius:6px;text-decoration:none;text-align:center;font-weight:500;font-size:14px;display:flex;align-items:center;justify-content:center;gap:6px;">
                            🗺️ Ver en Maps
                        </a>
                        <a href="${directionsUrl}" target="_blank" style="flex:1;background:#34a853;color:white;padding:10px 15px;border-radius:6px;text-decoration:none;text-align:center;font-weight:500;font-size:14px;display:flex;align-items:center;justify-content:center;gap:6px;">
                            🧭 Cómo llegar
                        </a>
                    </div>
            
                    <!-- Fuente de datos -->
                    <div style="margin-top:12px;font-size:10px;color:#6c757d;text-align:center;border-top:1px solid #e9ecef;padding-top:10px;">
                        Datos actualizados ${new Date().toLocaleString()}
                    </div>
                </div>
            `;
    
            content.innerHTML = html;
        },

        // ── Cerrar panel lateral ────────────────────────────────────
        cerrarPanelDetalle() {
            const panel = $('detail-panel');
            const overlay = $('detail-overlay');
            panel.classList.remove('open');
            if (overlay) overlay.classList.remove('show');
            _panelVisible = false;
            _currentMarkerId = null;
        },

        // ── Aplicar visibilidad ─────────────────────────────────────
        applyVisibility(isVisible) {
            _markers.forEach(({ marker, data }) => {
                marker.map = isVisible(data) ? _map : null;
            });
        },

        // ── Sidebar: lista de rutas ────────────────────────────────
        renderRoutes(routeIds, getName, getColor, isVisible, countFn) {
            const list = $('routes-list');
            list.innerHTML = '';

            const sorted = [...routeIds].sort((a, b) =>
                getName(a).localeCompare(getName(b))
            );

            sorted.forEach(id => {
                const el = document.createElement('div');
                el.className = 'route-item' + (isVisible(id) ? '' : ' hidden-route');
                el.id = `route-item-${id}`;
                el.innerHTML = `
                    <div class="route-swatch" style="background:${getColor(id)}"></div>
                    <div class="route-name" title="${esc(getName(id))}">${esc(getName(id))}</div>
                    <div class="route-count">${countFn(id)}</div>
                    <div class="route-eye">👁</div>
                `;
                list.appendChild(el);
            });

            return list;
        },

        setRouteVisible(id, visible) {
            const el = $(`route-item-${id}`);
            if (el) el.classList.toggle('hidden-route', !visible);
        },

        updateStats(stats) {
            $('stat-total').textContent  = stats.total;
            $('stat-rutas').textContent  = stats.rutas;
            $('stat-liquid').textContent = stats.liquid;
            $('stat-polvo').textContent  = stats.polvo;
        },

        setStatus(ok, text) {
            $('status-dot').className = ok ? 'ok' : 'err';
            $('status-text').textContent = text;
        },

        showError(msg) {
            const el = $('error-banner');
            el.innerHTML = `⚠️ ${msg}<br><small>Abre <a href="api.php?debug=1">api.php?debug=1</a> para diagnóstico</small>`;
            el.hidden = false;
        },

        hideLoading() {
            const el = $('loading');
            el.classList.add('fade-out');
            setTimeout(() => el.remove(), 400);
        },

        setTipoActive(tipo, active) {
            const btn = tipo === 'liquid' ? $('btn-liquid') : $('btn-polvo');
            if (!btn) return;
            if (active) btn.classList.add(tipo === 'liquid' ? 'active-liquid' : 'active-polvo');
            else        btn.classList.remove(tipo === 'liquid' ? 'active-liquid' : 'active-polvo');
        },

        toggleSidebar() {
            const sidebar  = $('sidebar');
            const overlay  = $('sidebarOverlay');
            const fab      = $('menuFab');
            const isOpen   = sidebar.classList.toggle('open');
            overlay.classList.toggle('show', isOpen);
            fab.innerHTML  = `<span class="material-icons">${isOpen ? 'close' : 'menu'}</span>`;
        },

        closeSidebar() {
            $('sidebar').classList.remove('open');
            $('sidebarOverlay').classList.remove('show');
            $('menuFab').innerHTML = '<span class="material-icons">menu</span>';
        },

        // ── Marker SVG ─────────────────────────────────────────────
        _createMarkerElement(color, forma) {
            // ... (mantener el mismo código que tenías)
            const div = document.createElement('div');
            Object.assign(div.style, { position: 'relative', width: '28px', height: '28px', cursor: 'pointer' });

            const NS  = 'http://www.w3.org/2000/svg';
            const svg = document.createElementNS(NS, 'svg');
            svg.setAttribute('viewBox', '0 0 28 28');
            svg.setAttribute('width',   '28');
            svg.setAttribute('height',  '28');

            const filter = document.createElementNS(NS, 'filter');
            filter.setAttribute('id', 'sh');
            const fds = document.createElementNS(NS, 'feDropShadow');
            fds.setAttribute('dx', '0'); fds.setAttribute('dy', '1');
            fds.setAttribute('stdDeviation', '1.5'); fds.setAttribute('flood-opacity', '0.3');
            filter.appendChild(fds);
            svg.appendChild(filter);

            const g = document.createElementNS(NS, 'g');
            g.setAttribute('filter', 'url(#sh)');

            const path = document.createElementNS(NS, 'path');
            if (forma === 'drop') {
                path.setAttribute('d', 'M 14 3 Q 14 3 8 9 Q 2 15 2 21 Q 2 26 7 29 Q 14 34 14 34 Q 14 34 21 29 Q 26 26 26 21 Q 26 15 20 9 Z');
            } else {
                path.setAttribute('d', 'M 4 8 L 12 2 L 20 8 L 20 14 L 12 20 L 4 14 Z M 4 8 L 4 14 L 12 20');
            }
            path.setAttribute('fill', color);
            path.setAttribute('stroke', 'white');
            path.setAttribute('stroke-width', '1.5');
            path.setAttribute('stroke-linejoin', 'round');

            g.appendChild(path);
            svg.appendChild(g);
            div.appendChild(svg);
            return div;
        },
    };
})();


/* ═══════════════════════════════════════════════════════════════════
   CONTROLLER — orquestación y eventos
═══════════════════════════════════════════════════════════════════ */
const MapController = (() => {

    let _dataLoaded = false;
    let _mapReady   = false;

    // ── Arranque ───────────────────────────────────────────────────
    function init() {
        // El mapa se inicia en paralelo con la carga de datos
        MapView.initMap(_onMapReady);
        _loadData();
        _bindEvents();
    }

    // ── Cuando el mapa está listo ──────────────────────────────────
    function _onMapReady() {
        _mapReady = true;
        if (_dataLoaded) _renderAll();
    }

    // ── Carga de datos ─────────────────────────────────────────────
    async function _loadData() {
        try {
            const json = await MapModel.fetchData();

            MapView.setStatus(true, `Conectado — ${json.total} lecherías`);
            MapView.updateStats(MapModel.getStats());

            _dataLoaded = true;
            if (_mapReady) _renderAll();

        } catch (err) {
            MapView.setStatus(false, 'Error de conexión');
            MapView.showError(err.message);
            console.error(err);
        } finally {
            MapView.hideLoading();
        }
    }

    // ── Renderizar markers + sidebar (solo 1 vez) ──────────────────
    function _renderAll() {
        MapView.buildMarkers(MapModel.getData(), id => MapModel.getRouteColor(id));

        const routeIds = MapModel.getRouteIds();
        const list = MapView.renderRoutes(
            routeIds,
            id  => MapModel.getRouteName(id),
            id  => MapModel.getRouteColor(id),
            id  => MapModel.isRouteVisible(id),
            id  => MapModel.getData().filter(d => d.id_ruta == id).length
        );

        // Delegar eventos de click en rutas
        list.addEventListener('click', e => {
            const item = e.target.closest('.route-item');
            if (!item) return;
            const id = item.id.replace('route-item-', '');
            const visible = MapModel.toggleRoute(id);
            MapView.setRouteVisible(id, visible);
            MapView.applyVisibility(d => MapModel.isVisible(d));
        });
    }

    // ── Aplicar filtros (visibilidad sin recrear markers) ──────────
    function _applyFilters() {
        MapView.applyVisibility(d => MapModel.isVisible(d));
    }

    // ── Eventos ────────────────────────────────────────────────────
    function _bindEvents() {
        // Búsqueda con debounce
        let searchTimer;
        document.getElementById('search-input').addEventListener('input', e => {
            clearTimeout(searchTimer);
            MapModel.setSearch(e.target.value);
            searchTimer = setTimeout(_applyFilters, 200);
        });

        // Botones tipo
        document.querySelectorAll('.tipo-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tipo = btn.dataset.tipo;
                const active = MapModel.toggleTipo(tipo);
                MapView.setTipoActive(tipo, active);
                _applyFilters();
            });
        });

        // Toggle todas las rutas
        document.getElementById('btn-toggle-all').addEventListener('click', () => {
            const visible = MapModel.toggleAllRoutes();
            MapModel.getRouteIds().forEach(id => MapView.setRouteVisible(id, visible));
            _applyFilters();
        });

        // FAB menú móvil
        document.getElementById('menuFab').addEventListener('click', () => MapView.toggleSidebar());
        document.getElementById('sidebarOverlay').addEventListener('click', () => MapView.closeSidebar());

        // Cerrar sidebar al rotar a escritorio
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) MapView.closeSidebar();
        });
    
        //  NUEVO: Eventos para cerrar el panel de detalles
        const closeBtn = document.getElementById('detail-close');
        const overlay = document.getElementById('detail-overlay');
    
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                MapView.cerrarPanelDetalle();
                if (overlay) overlay.classList.remove('show');
            });
        }
    
        if (overlay) {
            overlay.addEventListener('click', () => {
            MapView.cerrarPanelDetalle();
            overlay.classList.remove('show');
            });
        }
    
        // Cerrar panel con tecla ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                MapView.cerrarPanelDetalle();
                if (overlay) overlay.classList.remove('show');
            }
        });
    }

    return { init };
})();


/* ═══════════════════════════════════════════════════════════════════
   ARRANQUE
═══════════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => MapController.init());
