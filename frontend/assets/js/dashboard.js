/* ============================================================
   dashboard.js — CallMetric Pro supervisor dashboard
   ============================================================ */
(function () {
    'use strict';

    var MONO_FONT = "'JetBrains Mono', monospace";
    var TOOLTIP_BG = '#1e2a3a';
    var API_BASE = '/api';

    /* ============================================================
       Helpers
       ============================================================ */
    function fmtDuration(seconds) {
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        return m + ':' + String(s).padStart(2, '0');
    }

    function getKpiValue(index) {
        var kpiCards = document.querySelectorAll('.kpi-card');
        if (kpiCards.length > index) {
            return kpiCards[index].querySelector('.kpi-value');
        }
        return null;
    }

    function updateKpiValue(index, newValue) {
        var el = getKpiValue(index);
        if (el) el.textContent = newValue;
    }

    function updateLiveCallsSubtitle(count) {
        var subtitle = document.querySelector('#liveCallsRows')
            ? document.querySelector('#liveCallsRows').closest('.dashboard-card')
                .querySelector('.dash-card-subtitle')
            : null;
        if (subtitle) {
            subtitle.textContent = count + ' llamada' + (count !== 1 ? 's' : '') + ' activa' + (count !== 1 ? 's' : '');
        }
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    function buildCallRow(call) {
        var statusMap = {
            'activa':      { cls: 'activa',     icon: 'bi-telephone-inbound', live: true },
            'ringing':     { cls: 'espera',     icon: 'bi-clock',            live: true },
            'en_espera':   { cls: 'espera',     icon: 'bi-clock',            live: true },
            'grabando':    { cls: 'grabando',   icon: 'bi-mic',              live: true },
            'transferida': { cls: 'transferida', icon: 'bi-arrow-left-right', live: false },
            'answered':    { cls: 'activa',     icon: 'bi-telephone-inbound', live: true },
            'abandoned':   { cls: 'espera',     icon: 'bi-clock',            live: false }
        };
        var estado = (call.estado || call.status || 'activa').toLowerCase();
        var meta = statusMap[estado] || statusMap['activa'];

        var agentHtml = '';
        if (call.agente || call.agent) {
            var name = call.agente || call.agent;
            var initials = name.split(' ').map(function(w){ return w.charAt(0); }).join('').toUpperCase().substring(0,2);
            agentHtml = '<div class="cell-agent"><span class="cell-agent-avatar">' + escapeHtml(initials) + '</span><span class="cell-agent-name">' + escapeHtml(name) + '</span></div>';
        } else {
            agentHtml = '<span class="cell-agent-empty">—</span>';
        }

        return '<tr data-callid="' + escapeHtml(call.callid || call.id || '') + '">'
            + '<td class="cell-origin">' + escapeHtml(call.origen || call.origin || '') + '</td>'
            + '<td class="cell-dest">' + escapeHtml(call.destino || call.dest || '') + '</td>'
            + '<td class="cell-duration ' + (meta.live ? 'live' : 'finished') + '" data-duration="' + (call.duracion || call.duration || 0) + '" data-state="' + escapeHtml(estado) + '">' + fmtDuration(call.duracion || call.duration || 0) + '</td>'
            + '<td><span class="status-pill ' + meta.cls + '"><i class="bi ' + meta.icon + '"></i>' + escapeHtml(estado) + '</span></td>'
            + '<td>' + agentHtml + '</td>'
            + '<td><span class="cell-pbx">' + escapeHtml(call.pbx || '') + '</span></td>'
            + '</tr>';
    }

    /* ============================================================
       Sidebar collapse toggle
       ============================================================ */
    var sidebar = document.getElementById('appSidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var sidebarChevron = document.getElementById('sidebarChevron');
    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            var collapsed = sidebar.classList.toggle('collapsed');
            if (sidebarChevron) {
                sidebarChevron.className = collapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-left';
            }
        });
    }

    /* ============================================================
       Live calls table: increment durations
       ============================================================ */
    var rowCells = [];

    function refreshDurationTimers() {
        rowCells = [];
        var rows = document.querySelectorAll('#liveCallsRows tr');
        rows.forEach(function (row) {
            var cell = row.querySelector('[data-duration]');
            if (!cell) return;
            var state = cell.getAttribute('data-state') || '';
            if (state === 'transferida') return;
            rowCells.push({
                cell: cell,
                seconds: parseInt(cell.getAttribute('data-duration'), 10) || 0
            });
        });
    }

    refreshDurationTimers();

    setInterval(function () {
        rowCells.forEach(function (item) {
            item.seconds += 1;
            item.cell.textContent = fmtDuration(item.seconds);
        });
    }, 1000);

    /* ============================================================
       API data refresh — pulls fresh KPI data from backend
       ============================================================ */
    function refreshDashboardData() {
        fetch(API_BASE + '/dashboard/summary')
            .then(function (res) { return res.json(); })
            .then(function (json) {
                var data = json.data || json;
                if (data.llamadasActivas !== undefined) {
                    updateKpiValue(0, String(data.llamadasActivas));
                }
                if (data.tasaASR !== undefined) {
                    updateKpiValue(1, data.tasaASR + '%');
                }
                if (data.acdPromedio !== undefined) {
                    updateKpiValue(2, data.acdPromedio);
                }
                if (data.agentesActivos !== undefined && data.agentesTotal !== undefined) {
                    updateKpiValue(3, data.agentesActivos + '/' + data.agentesTotal);
                }
            })
            .catch(function (err) {
                console.warn('[Dashboard] Error refreshing API data:', err);
            });
    }

    /* ============================================================
       WebSocket integration
       ============================================================ */
    if (typeof window.CallMetricsWS !== 'undefined') {
        var tenantIdEl = document.getElementById('cmTenantId');
        var tenantId = tenantIdEl ? parseInt(tenantIdEl.value, 10) : null;
        var statusEl = document.getElementById('wsStatusIndicator');

        if (tenantId) {
            var ws = new CallMetricsWS({ tenantId: tenantId });

            /* --- call_event: call_started, call_ended, call_ringing, call_answered, call_update --- */
            ws.on('call_event', function (data) {
                var event = data.event || '';
                var eventData = data.data || {};
                var tbody = document.getElementById('liveCallsRows');
                if (!tbody) return;

                if (event === 'call_started' || event === 'call_ringing' || event === 'call_answered' || event === 'call_update') {
                    // Check if call already exists in the table
                    var callId = eventData.callid || eventData.id_unico || '';
                    var existingRow = callId
                        ? tbody.querySelector('tr[data-callid="' + escapeHtml(callId) + '"]')
                        : null;

                    // Map WS event to display state
                    var displayState = 'activa';
                    if (event === 'call_ringing') displayState = 'ringing';
                    else if (event === 'call_answered') displayState = 'activa';

                    var callData = {
                        callid: callId,
                        origen: eventData.caller || eventData.origen || eventData.callerid || '',
                        destino: eventData.dest || eventData.destino || eventData.extension || '',
                        duracion: eventData.dur_sec || eventData.duracion || eventData.duration || 0,
                        estado: displayState,
                        agente: eventData.agent || eventData.agente || eventData.agent_name || null,
                        pbx: eventData.pbx || eventData.pbx_name || ''
                    };

                    if (existingRow) {
                        // Update existing row
                        var durationCell = existingRow.querySelector('[data-duration]');
                        if (durationCell) {
                            durationCell.setAttribute('data-state', displayState);
                            durationCell.className = 'cell-duration live';
                            durationCell.setAttribute('data-duration', String(callData.duracion));
                            durationCell.textContent = fmtDuration(callData.duracion);
                        }
                        var statusCell = existingRow.querySelector('.status-pill');
                        if (statusCell) {
                            statusCell.className = 'status-pill ' + (displayState === 'ringing' ? 'espera' : 'activa');
                        }
                    } else {
                        // Insert new row at the top
                        var temp = document.createElement('div');
                        temp.innerHTML = buildCallRow(callData);
                        var newRow = temp.firstElementChild;
                        tbody.insertBefore(newRow, tbody.firstChild);

                        // Start duration timer for new row
                        var newCell = newRow.querySelector('[data-duration]');
                        if (newCell) {
                            rowCells.push({
                                cell: newCell,
                                seconds: callData.duracion
                            });
                        }
                    }

                    // Increment active calls KPI
                    var kpiEl = getKpiValue(0);
                    if (kpiEl) {
                        var current = parseInt(kpiEl.textContent, 10) || 0;
                        kpiEl.textContent = current + 1;
                    }
                    updateLiveCallsSubtitle(tbody.querySelectorAll('tr').length);

                } else if (event === 'call_ended') {
                    // Remove call from table
                    var callId = eventData.callid || eventData.id_unico || '';
                    if (callId) {
                        var row = tbody.querySelector('tr[data-callid="' + escapeHtml(callId) + '"]');
                        if (row) {
                            // Remove duration timer
                            var durCell = row.querySelector('[data-duration]');
                            if (durCell) {
                                rowCells = rowCells.filter(function (item) {
                                    return item.cell !== durCell;
                                });
                            }
                            row.remove();
                        }
                    }

                    // Decrement active calls KPI
                    var kpiEl = getKpiValue(0);
                    if (kpiEl) {
                        var current = parseInt(kpiEl.textContent, 10) || 0;
                        kpiEl.textContent = Math.max(0, current - 1);
                    }
                    updateLiveCallsSubtitle(tbody.querySelectorAll('tr').length);

                } else if (event === 'queue_update') {
                    // Update queue-related KPIs from call_event queue_update payload
                    var datos = eventData.datos || eventData;
                    if (datos.calls_waiting !== undefined) {
                        // Update queue waiting count in relevant KPI if present
                        console.log('[Dashboard] Queue update:', datos);
                    }
                }
            });

            /* --- queue_update: dedicated queue channel events --- */
            ws.on('queue_update', function (data) {
                var datos = data.data || data;
                console.log('[Dashboard] Queue update:', datos);

                // Update queue chart data if chart exists
                if (window._queueChart && datos.queue_name && datos.calls_waiting !== undefined) {
                    var chart = window._queueChart;
                    var labels = chart.data.labels || [];
                    var idx = labels.indexOf(datos.queue_name);
                    if (idx !== -1) {
                        chart.data.datasets[1].data[idx] = datos.calls_waiting;
                        chart.update('none');
                    }
                }
            });

            /* --- pbx_health: update PBX status indicators --- */
            ws.on('pbx_health', function (data) {
                var healthData = data.data || {};
                var pbxId = data.pbx_id || healthData.pbx_id || null;

                console.log('[Dashboard] PBX Health:', healthData);

                // Update PBX status indicators if elements exist
                if (pbxId) {
                    var statusDot = document.querySelector('[data-pbx-id="' + pbxId + '"] .topbar-pbx-dot');
                    if (statusDot) {
                        var estado = healthData.estado || healthData.status || 'online';
                        statusDot.style.background = estado === 'online' ? 'var(--cm-secondary)' :
                            estado === 'degraded' ? '#f59e0b' : 'var(--cm-danger)';
                        statusDot.style.boxShadow = '0 0 6px ' + statusDot.style.background;
                    }
                }

                // Update CPU/memory if displayed anywhere
                if (healthData.cpu !== undefined) {
                    var cpuEl = document.querySelector('[data-pbx-cpu="' + pbxId + '"]');
                    if (cpuEl) cpuEl.textContent = healthData.cpu + '%';
                }
                if (healthData.ram !== undefined) {
                    var ramEl = document.querySelector('[data-pbx-ram="' + pbxId + '"]');
                    if (ramEl) ramEl.textContent = healthData.ram + '%';
                }
            });

            /* --- agent_status: update agent availability --- */
            ws.on('agent_status', function (data) {
                var agentData = data.data || data;
                console.log('[Dashboard] Agent status:', agentData);
            });

            /* --- Connection status indicators --- */
            ws.on('connected', function () {
                if (statusEl) {
                    statusEl.innerHTML = '<i class="bi bi-circle-fill text-success me-1"></i>En tiempo real';
                }
                // Refresh data on reconnect
                refreshDashboardData();
            });

            ws.on('disconnected', function () {
                if (statusEl) {
                    statusEl.innerHTML = '<i class="bi bi-circle-fill text-danger me-1"></i>Desconectado';
                }
            });

            ws.on('reconnecting', function () {
                if (statusEl) {
                    statusEl.innerHTML = '<i class="bi bi-circle-fill text-warning me-1"></i>Reconectando...';
                }
            });

            ws.connect();
            window._cmWS = ws;

            // Periodic KPI refresh (every 60s as fallback when WS is down)
            setInterval(function () {
                if (!ws.connected) {
                    refreshDashboardData();
                }
            }, 60000);
        }
    }

    /* ---- Chart.js init (only if library present) ---- */
    if (typeof window.Chart === 'undefined') return;

    /* Concurrency chart */
    var concurrencyCanvas = document.getElementById('concurrencyChart');
    if (concurrencyCanvas) {
        var ctx = concurrencyCanvas.getContext('2d');
        var gradient = ctx.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.28)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

        var labels = [];
        for (var h = 0; h < 24; h++) {
            labels.push(String(h).padStart(2, '0') + ':00');
        }

        var data = [12, 7, 5, 4, 6, 9, 23, 67, 134, 198, 231, 247, 189, 156, 212, 238, 225, 198, 142, 89, 56, 34, 21, 15];
        var average = data.map(function () {
            return 109;
        });

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Llamadas activas',
                        data: data,
                        borderColor: '#3b82f6',
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#3b82f6',
                        fill: true,
                        backgroundColor: gradient,
                        tension: 0.35
                    },
                    {
                        label: 'Promedio (109)',
                        data: average,
                        borderColor: 'rgba(148, 163, 184, 0.25)',
                        borderDash: [4, 4],
                        borderWidth: 1.5,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: TOOLTIP_BG,
                        borderColor: 'rgba(255, 255, 255, 0.12)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        padding: 12,
                        displayColors: false,
                        titleFont: { family: MONO_FONT, size: 20, weight: 700, color: '#60a5fa' },
                        bodyFont: { family: MONO_FONT, size: 11, color: '#475569' },
                        footerFont: { family: 'Inter', size: 11, color: '#475569' },
                        callbacks: {
                            title: function (items) {
                                return items.length ? String(items[0].parsed.y) : '';
                            },
                            label: function (item) {
                                return item.label;
                            },
                            footer: function () {
                                return 'llamadas concurrentes';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#374151',
                            font: { family: MONO_FONT, size: 10 },
                            autoSkip: false,
                            maxRotation: 0,
                            callback: function (value) {
                                return value % 3 === 0 ? labels[value] : '';
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)', borderDash: [4, 4] },
                        ticks: { color: '#374151', font: { family: MONO_FONT, size: 10 } }
                    }
                }
            }
        });
    }

    /* Queue chart (grouped bars) */
    var queueCanvas = document.getElementById('queueChart');
    if (queueCanvas) {
        window._queueChart = new Chart(queueCanvas, {
            type: 'bar',
            data: {
                labels: ['Ventas', 'Soporte', 'Postventa', 'Admin', 'Cobranza', 'RRHH'],
                datasets: [
                    {
                        label: 'Atendidas',
                        data: [187, 134, 98, 56, 203, 34],
                        backgroundColor: '#3b82f6',
                        borderRadius: { topLeft: 3, topRight: 3 },
                        maxBarSize: 22
                    },
                    {
                        label: 'En espera',
                        data: [23, 8, 15, 3, 31, 1],
                        backgroundColor: '#f59e0b',
                        borderRadius: { topLeft: 3, topRight: 3 },
                        maxBarSize: 22
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: TOOLTIP_BG,
                        borderColor: 'rgba(255, 255, 255, 0.12)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        padding: 12,
                        displayColors: false,
                        titleFont: { family: MONO_FONT, size: 18, weight: 700, color: '#fbbf24' },
                        bodyFont: { family: 'Inter', size: 11, color: '#cbd5e1' },
                        callbacks: {
                            title: function (items) {
                                return items.length ? String(items[0].parsed.y) : '';
                            },
                            label: function (item) {
                                return item.dataset.label + ' · ' + item.label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#374151', font: { family: 'Inter', size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)', borderDash: [4, 4] },
                        ticks: { color: '#374151', font: { family: MONO_FONT, size: 10 } }
                    }
                }
            }
        });
    }
})();
