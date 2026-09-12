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

$alertsList = [
    ['severity' => 'critical', 'icon' => 'bi-exclamation-octagon-fill', 'message' => 'Cola Ventas saturada — 31 llamadas en espera', 'time' => 'Hace 2 min', 'pbx' => 'PBX-1', 'source' => 'Queue Monitor'],
    ['severity' => 'high', 'icon' => 'bi-exclamation-triangle-fill', 'message' => 'CPU PBX 2 al 91% — umbral superado (límite: 80%)', 'time' => 'Hace 5 min', 'pbx' => 'PBX-2', 'source' => 'System Health'],
    ['severity' => 'medium', 'icon' => 'bi-exclamation-triangle-fill', 'message' => 'Latencia SIP elevada: 340ms promedio (normal <150ms)', 'time' => 'Hace 12 min', 'pbx' => 'PBX-1', 'source' => 'SIP Monitor'],
    ['severity' => 'high', 'icon' => 'bi-exclamation-triangle-fill', 'message' => 'Agente "Pedro G." sin respuesta — 5 llamadas perdidas', 'time' => 'Hace 18 min', 'pbx' => 'PBX-3', 'source' => 'Agent Monitor'],
    ['severity' => 'low', 'icon' => 'bi-info-circle', 'message' => 'Nuevo tronco SIP registrado: trunk-mx-01', 'time' => 'Hace 34 min', 'pbx' => 'PBX-2', 'source' => 'SIP Registry'],
];

$callsList = [
    ['origin' => '+34 612 345 678', 'dest' => 'Cola Ventas', 'duration' => 272, 'status' => 'Activa', 'agent' => 'Carlos M.', 'pbx' => 'PBX-1'],
    ['origin' => '+1 555 234 5678', 'dest' => 'Cola Soporte', 'duration' => 767, 'status' => 'En espera', 'agent' => null, 'pbx' => 'PBX-2'],
    ['origin' => '+34 654 789 012', 'dest' => 'Ext. 1042', 'duration' => 135, 'status' => 'Activa', 'agent' => 'Ana R.', 'pbx' => 'PBX-1'],
    ['origin' => '+44 7700 123456', 'dest' => 'Cola Ventas', 'duration' => 45, 'status' => 'Grabando', 'agent' => 'Miguel F.', 'pbx' => 'PBX-1'],
    ['origin' => '+34 699 876 543', 'dest' => 'Cola Postventa', 'duration' => 438, 'status' => 'Transferida', 'agent' => 'Laura P.', 'pbx' => 'PBX-3'],
    ['origin' => '+52 55 1234 5678', 'dest' => 'Cola Cobranza', 'duration' => 89, 'status' => 'Activa', 'agent' => 'Roberto K.', 'pbx' => 'PBX-2'],
    ['origin' => '+34 611 222 333', 'dest' => 'Ext. 2015', 'duration' => 512, 'status' => 'Activa', 'agent' => 'Sandra V.', 'pbx' => 'PBX-1'],
    ['origin' => '+1 800 555 0199', 'dest' => 'Cola Soporte', 'duration' => 34, 'status' => 'En espera', 'agent' => null, 'pbx' => 'PBX-2'],
    ['origin' => '+34 678 901 234', 'dest' => 'Cola Ventas', 'duration' => 198, 'status' => 'Grabando', 'agent' => 'Elena B.', 'pbx' => 'PBX-3'],
    ['origin' => '+49 30 1234567', 'dest' => 'Ext. 3087', 'duration' => 65, 'status' => 'Activa', 'agent' => 'Pablo T.', 'pbx' => 'PBX-1'],
];

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
                    <p class="dash-card-subtitle">5 sin resolver</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="severity-chip critical">1 crítica</span>
                    <span class="severity-chip high">2 altas</span>
                </div>
            </div>

            <ul class="alerts-list">
                <?php foreach ($alertsList as $alert): ?>
                    <li class="alert-row sev-<?= htmlspecialchars($alert['severity']) ?>">
                        <i class="bi <?= htmlspecialchars($alert['icon']) ?> alert-row-icon <?= htmlspecialchars($alert['severity']) ?>"></i>
                        <div>
                            <div class="alert-row-msg"><?= htmlspecialchars($alert['message']) ?></div>
                            <div class="alert-row-meta">
                                <span class="alert-row-time"><?= htmlspecialchars($alert['time']) ?></span>
                                <span class="alert-pbx-chip"><?= htmlspecialchars($alert['pbx']) ?></span>
                                <span class="alert-row-source"><?= htmlspecialchars($alert['source']) ?></span>
                            </div>
                        </div>
                        <button class="alert-row-dismiss" type="button" aria-label="Descartar alerta"><i class="bi bi-x"></i></button>
                    </li>
                <?php endforeach; ?>
            </ul>

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
                    <p class="dash-card-subtitle">10 llamadas activas</p>
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
                        <?php foreach ($callsList as $call): ?>
                            <?php $meta = $statusMeta[$call['status']]; ?>
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
