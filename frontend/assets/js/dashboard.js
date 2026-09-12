/* ============================================================
   dashboard.js — CallMetric Pro supervisor dashboard
   ============================================================ */
(function () {
    'use strict';

    var MONO_FONT = "'JetBrains Mono', monospace";
    var TOOLTIP_BG = '#1e2a3a';

    function fmtDuration(seconds) {
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        return m + ':' + String(s).padStart(2, '0');
    }

    /* ---- Sidebar collapse toggle ---- */
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

    /* ---- Live calls table: increment durations ---- */
    var rows = document.querySelectorAll('#liveCallsRows tr');
    var rowCells = [];
    rows.forEach(function (row) {
        var cell = row.querySelector('[data-duration]');
        if (!cell) return;
        var state = cell.getAttribute('data-state') || '';
        if (state === 'Transferida') return;
        rowCells.push({
            cell: cell,
            seconds: parseInt(cell.getAttribute('data-duration'), 10) || 0
        });
    });

    if (rowCells.length) {
        setInterval(function () {
            rowCells.forEach(function (item) {
                item.seconds += 1;
                item.cell.textContent = fmtDuration(item.seconds);
            });
        }, 1000);
    }

    /* ---- WebSocket: recibir eventos de llamadas en tiempo real ---- */
    if (typeof window.CallMetricsWS !== 'undefined') {
        var tenantIdEl = document.getElementById('cmTenantId');
        var tenantId = tenantIdEl ? parseInt(tenantIdEl.value, 10) : null;
        var statusEl = document.getElementById('wsStatusIndicator');

        if (tenantId) {
            var ws = new CallMetricsWS({ tenantId: tenantId });

            ws.on('call_event', function (data) {
                var event = data.event || '';
                var eventData = data.data || {};

                if (event === 'call_started') {
                    // Incrementar contador de llamadas activas
                    var kpiCards = document.querySelectorAll('.kpi-card');
                    if (kpiCards.length > 0) {
                        var callCountEl = kpiCards[0].querySelector('.kpi-value');
                        if (callCountEl) {
                            var current = parseInt(callCountEl.textContent, 10) || 0;
                            callCountEl.textContent = current + 1;
                        }
                    }
                } else if (event === 'call_ended') {
                    // Decrementar contador de llamadas activas
                    var kpiCards = document.querySelectorAll('.kpi-card');
                    if (kpiCards.length > 0) {
                        var callCountEl = kpiCards[0].querySelector('.kpi-value');
                        if (callCountEl) {
                            var current = parseInt(callCountEl.textContent, 10) || 0;
                            callCountEl.textContent = Math.max(0, current - 1);
                        }
                    }
                }
            });

            ws.on('pbx_health', function (data) {
                // Actualizar indicador de salud del PBX si existe
                var healthData = data.data || {};
                console.log('[Dashboard] PBX Health:', healthData);
            });

            ws.on('connected', function () {
                if (statusEl) {
                    statusEl.innerHTML = '<i class="bi bi-circle-fill text-success me-1"></i>En tiempo real';
                }
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
        new Chart(queueCanvas, {
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
