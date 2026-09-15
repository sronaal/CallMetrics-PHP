<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/ApiClient.php';
require_once __DIR__ . '/../core/AuthMiddleware.php';
require_once __DIR__ . '/../core/ApiClientHelpers.php';

AuthMiddleware::check();
Session::touch();

$extraCss = [BASE_URL . 'assets/css/dashboard.css'];
$extraJs = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    BASE_URL . 'assets/js/ws-client.js',
    BASE_URL . 'assets/js/dashboard.js',
];

// Obtener datos del dashboard desde la API
$dashboardResponse = api_get_dashboard();
$stats = $dashboardResponse['data'] ?? [];

$title = 'Dashboard';
$activeNav = 'dashboard';
ob_start();

/* ---------------- Data ---------------- */
function cm_duration($seconds)
{
    return intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
}

$kpiList = [
    ['label' => 'Llamadas activas', 'icon' => 'bi-telephone-inbound', 'iconColor' => '#10b981', 'iconBg' => 'rgba(16,185,129,0.14)', 'value' => (string)($stats['llamadasActivas'] ?? 247), 'subtitle' => 'llamadas activas ahora', 'trend' => '+4.2%', 'trendClass' => 'up'],
    ['label' => 'Tasa ASR 24h', 'icon' => 'bi-bullseye', 'iconColor' => '#4f6ef7', 'iconBg' => 'rgba(79,110,247,0.14)', 'value' => ($stats['tasaASR'] ?? '83.4') . '%', 'subtitle' => 'tasa de respuesta en 24h', 'trend' => '+1.8%', 'trendClass' => 'up'],
    ['label' => 'ACD promedio', 'icon' => 'bi-clock-history', 'iconColor' => '#f59e0b', 'iconBg' => 'rgba(245,158,11,0.14)', 'value' => $stats['acdPromedio'] ?? '4:28', 'subtitle' => 'duración promedio', 'trend' => '-12s', 'trendClass' => 'down'],
    ['label' => 'Agentes', 'icon' => 'bi-people', 'iconColor' => '#4f6ef7', 'iconBg' => 'rgba(79,110,247,0.14)', 'value' => ($stats['agentesActivos'] ?? '89') . '/' . ($stats['agentesTotal'] ?? '120'), 'subtitle' => 'conectados / total', 'trend' => 'estable', 'trendClass' => 'neutral'],
];

// Fetch active alerts from API
$alertsResponse = api_get_alertas(0, 50);
$alertsData = $alertsResponse['data'] ?? [];
$alertsList = [];
foreach ($alertsData as $alert) {
    $sev = strtolower($alert['severidad'] ?? 'info');
    $icons = ['critical' => 'bi-exclamation-octagon-fill', 'high' => 'bi-exclamation-triangle-fill', 'medium' => 'bi-exclamation-triangle-fill', 'low' => 'bi-info-circle'];
    $alertsList[] = [
        'severity' => $sev,
        'icon' => $icons[$sev] ?? 'bi-info-circle',
        'message' => ($alert['nombre'] ?? 'Alerta') . ($alert['umbral'] ? ' — umbral: ' . $alert['umbral'] : ''),
        'time' => '',
        'pbx' => '',
        'source' => $alert['metrica'] ?? 'System',
    ];
}
$alertsCount = count($alertsList);
$criticalCount = count(array_filter($alertsList, fn($a) => $a['severity'] === 'critical'));
$highCount = count(array_filter($alertsList, fn($a) => $a['severity'] === 'high'));

// Fetch active calls from API (llamadas_cdr with fin_llamada IS NULL)
$callsResponse = api_get_llamadas(0, 50);
$callsData = $callsResponse['data'] ?? [];
$callsList = [];
foreach ($callsData as $call) {
    if (!empty($call['fin_llamada'])) continue;
    $callsList[] = [
        'origin' => $call['numero_origen'] ?? '',
        'dest' => $call['numero_destino'] ?? '',
        'duration' => (int)($call['duracion'] ?? 0),
        'status' => 'Activa',
        'agent' => null,
        'pbx' => $call['pbx_nombre'] ?? '',
    ];
}

$statusMeta = [
    'Activa' => ['class' => 'activa', 'icon' => 'bi-telephone-inbound', 'live' => true],
    'En espera' => ['class' => 'espera', 'icon' => 'bi-clock', 'live' => true],
    'Grabando' => ['class' => 'grabando', 'icon' => 'bi-mic', 'live' => true],
    'Transferida' => ['class' => 'transferida', 'icon' => 'bi-arrow-left-right', 'live' => false],
];

function cm_agent_initials($name)
{
    $parts = explode(' ', (string) $name);
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
    }
    return $initials !== '' ? $initials : '—';
}
?>

<!-- Tenant ID para WebSocket (oculto) -->
<?php $sessionUser = Session::user(); ?>
<input type="hidden" id="cmTenantId" value="<?= (int) ($sessionUser['tenantId'] ?? 0) ?>" />

<!-- Status indicator del WebSocket -->
<div class="d-flex justify-content-end mb-2">
    <span id="wsStatusIndicator" class="text-muted small">
        <i class="bi bi-circle-fill text-secondary me-1"></i>Conectando...
    </span>
</div>

<!-- KPI row -->
<div class="row g-4 mb-4">
    <?php foreach ($kpiList as $kpi): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="dashboard-card">
                <div class="kpi-label">
                    <span class="kpi-icon" style="background:<?= htmlspecialchars($kpi['iconBg']) ?>">
                        <i class="bi <?= htmlspecialchars($kpi['icon']) ?>" style="color:<?= htmlspecialchars($kpi['iconColor']) ?>"></i>
                    </span>
                    <?= htmlspecialchars($kpi['label']) ?>
                </div>
                <div class="kpi-value" style="color:<?= htmlspecialchars($kpi['iconColor']) ?>"><?= htmlspecialchars($kpi['value']) ?></div>
                <div class="kpi-subtitle"><?= htmlspecialchars($kpi['subtitle']) ?></div>
                <div class="kpi-trend <?= htmlspecialchars($kpi['trendClass']) ?>"><?= htmlspecialchars($kpi['trend']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Charts row -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="dashboard-card h-100">
            <div class="dash-card-header">
                <div>
                    <h2 class="dash-card-title">Concurrencia de Llamadas</h2>
                    <p class="dash-card-subtitle">Últimas 24 horas · Todos los PBX</p>
                </div>
                <div class="d-flex gap-4">
                    <div class="chart-stat-chip">PICO<strong style="color:#4f6ef7">247</strong></div>
                    <div class="chart-stat-chip">PROMEDIO<strong style="color:#94a3b8">109</strong></div>
                </div>
            </div>
            <div class="chart-box">
                <canvas id="concurrencyChart" aria-label="Gráfico de concurrencia de llamadas"></canvas>
            </div>
            <div class="chart-legend">
                <span class="chart-legend-item"><span class="chart-legend-swatch" style="background:#4f6ef7"></span>Llamadas activas</span>
                <span class="chart-legend-item"><span class="chart-legend-swatch dashed"></span>Promedio (109)</span>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="dashboard-card h-100">
            <div class="dash-card-header">
                <div>
                    <h2 class="dash-card-title">Estado de Colas</h2>
                    <p class="dash-card-subtitle">Atendidas vs. En espera</p>
                </div>
            </div>
            <div class="chart-box">
                <canvas id="queueChart" aria-label="Gráfico de estado de colas"></canvas>
            </div>
            <div class="chart-legend">
                <span class="chart-legend-item"><span class="chart-legend-swatch" style="background:#4f6ef7"></span>Atendidas</span>
                <span class="chart-legend-item"><span class="chart-legend-swatch" style="background:#f59e0b"></span>En espera</span>
            </div>
            <div class="queue-callout">
                <span class="queue-callout-dot"></span>
                Cola Cobranza: saturación al 87%
            </div>
        </div>
    </div>
</div>

<!-- Alerts + Live calls -->
<div class="row g-4">
    <div class="col-lg-5">
        <div class="dashboard-card h-100">
            <div class="dash-card-header">
                <div>
                    <h2 class="dash-card-title">Alertas Activas</h2>
                    <p class="dash-card-subtitle"><?= $alertsCount ?> sin resolver</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($criticalCount > 0): ?>
                        <span class="severity-chip critical"><?= $criticalCount ?> crítica<?= $criticalCount !== 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                    <?php if ($highCount > 0): ?>
                        <span class="severity-chip high"><?= $highCount ?> alta<?= $highCount !== 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($alertsList): ?>
            <ul class="alerts-list">
                <?php foreach ($alertsList as $alert): ?>
                    <li class="alert-row sev-<?= htmlspecialchars($alert['severity']) ?>">
                        <i class="bi <?= htmlspecialchars($alert['icon']) ?> alert-row-icon <?= htmlspecialchars($alert['severity']) ?>"></i>
                        <div>
                            <div class="alert-row-msg"><?= htmlspecialchars($alert['message']) ?></div>
                            <div class="alert-row-meta">
                                <?php if ($alert['time']): ?>
                                    <span class="alert-row-time"><?= htmlspecialchars($alert['time']) ?></span>
                                <?php endif; ?>
                                <?php if ($alert['pbx']): ?>
                                    <span class="alert-pbx-chip"><?= htmlspecialchars($alert['pbx']) ?></span>
                                <?php endif; ?>
                                <span class="alert-row-source"><?= htmlspecialchars($alert['source']) ?></span>
                            </div>
                        </div>
                        <button class="alert-row-dismiss" type="button" aria-label="Descartar alerta"><i class="bi bi-x"></i></button>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-check-circle me-1"></i>No hay alertas activas
            </div>
            <?php endif; ?>

            <div class="alerts-footer">
                <a href="#">Ver historial completo →</a>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="dashboard-card h-100">
            <div class="dash-card-header">
                <div>
                    <h2 class="dash-card-title">Llamadas en Tiempo Real</h2>
                    <p class="dash-card-subtitle"><?= count($callsList) ?> llamada<?= count($callsList) !== 1 ? 's' : '' ?> activa<?= count($callsList) !== 1 ? 's' : '' ?></p>
                </div>
                <div class="dash-live-badge">
                    <span class="dash-live-dot"></span>
                    En vivo
                </div>
            </div>

            <div class="dash-table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th scope="col">Origen</th>
                            <th scope="col">Destino</th>
                            <th scope="col">Duración</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Agente</th>
                            <th scope="col">PBX</th>
                        </tr>
                    </thead>
                    <tbody id="liveCallsRows">
                        <?php if ($callsList): ?>
                        <?php foreach ($callsList as $call): ?>
                            <?php $meta = $statusMeta[$call['status']] ?? $statusMeta['Activa']; ?>
                            <tr>
                                <td class="cell-origin"><?= htmlspecialchars($call['origin']) ?></td>
                                <td class="cell-dest"><?= htmlspecialchars($call['dest']) ?></td>
                                <td class="cell-duration <?= $meta['live'] ? 'live' : 'finished' ?>" data-duration="<?= (int) $call['duration'] ?>" data-state="<?= htmlspecialchars($call['status']) ?>"><?= cm_duration($call['duration']) ?></td>
                                <td>
                                <span class="status-pill <?= htmlspecialchars($meta['class']) ?>">
                                        <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i>
                                        <?= htmlspecialchars($call['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($call['agent']): ?>
                                        <div class="cell-agent">
                                            <span class="cell-agent-avatar"><?= htmlspecialchars(cm_agent_initials($call['agent'])) ?></span>
                                            <span class="cell-agent-name"><?= htmlspecialchars($call['agent']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="cell-agent-empty">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="cell-pbx"><?= htmlspecialchars($call['pbx']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay llamadas en tiempo real</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require SRC_PATH . '/layout/app.php';
?>
