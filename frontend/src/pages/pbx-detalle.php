<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/ApiClient.php';
require_once __DIR__ . '/../core/ApiClientHelpers.php';
require_once __DIR__ . '/../core/AuthMiddleware.php';
require_once SRC_PATH . '/components/components.php';

AuthMiddleware::check();
Session::touch();

$title = 'Detalle PBX';
$activeNav = 'pbx';
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css'];
$extraJs = [];

ob_start();

/* ---------------- Datos desde API real ---------------- */
$pbxId = max(1, (int) ($_GET['id'] ?? 1));

// Obtener PBX desde el backend
$pbxResponse = api_get_pbx_detail($pbxId);
$pbx = ($pbxResponse['success'] && $pbxResponse['data']) ? $pbxResponse['data'] : null;

if ($pbx === null) {
    echo '<div class="dashboard-card p-4">';
    echo '<h2 class="h4 mb-2">PBX no encontrado</h2>';
    echo '<p class="text-muted mb-3">' . htmlspecialchars($pbxResponse['message'] ?? 'El servidor solicitado no existe o fue eliminado.') . '</p>';
    echo '<a class="btn btn-primary btn-sm" href="' . BASE_URL . 'pbx.php"><i class="bi bi-arrow-left me-1"></i>Volver a Centrales PBX</a>';
    echo '</div>';
    $content = ob_get_clean();
    require SRC_PATH . '/layout/app.php';
    return;
}

// Heartbeats from eventos API (HEALTH type, heartbeat event)
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 15;
$hbResponse = api_get_eventos_pbx($pbxId, $page - 1, $per);
$hbRaw = $hbResponse['data'] ?? [];
$totalHb = $hbResponse['meta']['total'] ?? count($hbRaw);
$pages = max(1, (int) ceil($totalHb / $per));
$basePaginacion = BASE_URL . 'pbx-detalle.php?id=' . $pbxId . '&';

// Transform heartbeat events to match table template expectations
$hbRows = [];
foreach ($hbRaw as $ev) {
    $contenido = $ev['contenido'] ?? [];
    if (is_string($contenido)) $contenido = json_decode($contenido, true);
    if (!is_array($contenido)) continue;

    $sistema = $contenido['sistema'] ?? [];
    $ami = $contenido['conexion_ami'] ?? [];

    $hbRows[] = [
        'timestamp' => strtotime($ev['created_at'] ?? $ev['updated_at'] ?? 'now'),
        'estado'    => ($ami['conectado'] ?? false) ? 'online' : 'offline',
        'latencia'  => 0,
        'perdida_pct' => ($ami['conectado'] ?? false) ? 0 : 100,
        'cpu'       => (int) ($sistema['cpu_porcentaje'] ?? 0),
        'ram'       => (int) ($sistema['ram_porcentaje'] ?? 0),
        'disco'     => (int) ($sistema['disco_porcentaje'] ?? 0),
        'servidor'  => $sistema['hostname'] ?? ($contenido['pbx_host'] ?? '—'),
    ];
}

// Use latest heartbeat for stats
$ultimo = $hbRows[0] ?? null;

$llamadasDia = (int) ($pbx['llamadas_hoy'] ?? 0);
$uptime = (int) ($pbx['uptime'] ?? 0);

$estadoRaw = strtolower($pbx['estado'] ?? 'offline');
$estadoTxt = match ($estadoRaw) { 'online' => 'En línea', 'degraded' => 'Degradado', default => 'Sin conexión' };
$versionTxt = ($pbx['version'] ?? '') !== '' ? $pbx['version'] : 'Sin datos';

$stats = [
    ['label' => 'Uptime', 'value' => cm_format_duration($uptime), 'icon' => 'bi-arrow-up-short', 'clase' => ''],
    ['label' => 'Llamadas hoy', 'value' => number_format($llamadasDia), 'icon' => 'bi-telephone', 'clase' => ''],
    ['label' => 'CPU', 'value' => $ultimo ? ((int) $ultimo['cpu'] . '%') : '—', 'icon' => 'bi-cpu', 'clase' => $ultimo && (int)$ultimo['cpu'] >= 90 ? 'text-danger' : ''],
    ['label' => 'RAM', 'value' => $ultimo ? ((int) $ultimo['ram'] . '%') : '—', 'icon' => 'bi-memory', 'clase' => $ultimo && (int)$ultimo['ram'] >= 90 ? 'text-danger' : ''],
];
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <a class="cm-row-action" href="<?= BASE_URL ?>pbx.php" title="Volver a Centrales PBX"><i class="bi bi-arrow-left"></i></a>
        <h2 class="h4 mb-0"><?= htmlspecialchars($pbx['nombre']) ?></h2>
        <?= cm_render_badge($pbx['estado']) ?>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>pbx.php"><i class="bi bi-list-ul me-1"></i>Centrales PBX</a>
</div>

<!-- Tarjetas de resumen -->
<div class="row g-3 mb-3">
    <?php foreach ($stats as $s): ?>
        <div class="col-6 col-lg-3">
            <?= cm_render_stat_card($s['label'], $s['value'], $s['icon'], $s['clase']) ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <!-- Datos del servidor -->
    <div class="col-lg-4">
        <div class="dashboard-card p-3 h-100">
            <h3 class="h6 mb-3">Datos del Servidor</h3>
            <dl class="row mb-0 small">
                <dt class="col-4 text-muted">Host</dt><dd class="col-8 mb-2 text-end"><?= htmlspecialchars($pbx['ip_address']) ?></dd>
                <dt class="col-4 text-muted">Puerto</dt><dd class="col-8 mb-2 text-end"><?= (int) $pbx['puerto_ami'] ?></dd>
                <dt class="col-4 text-muted">Estado</dt><dd class="col-8 mb-2 text-end"><?= $estadoTxt ?></dd>
                <dt class="col-4 text-muted">Versión</dt><dd class="col-8 mb-2 text-end"><?= htmlspecialchars($versionTxt) ?></dd>
                <dt class="col-4 text-muted">Uptime</dt><dd class="col-8 mb-2 text-end"><?= htmlspecialchars(cm_format_duration($pbx['uptime'])) ?></dd>
            </dl>
        </div>
    </div>
    <!-- Heartbeats -->
    <div class="col-lg-8">
        <div class="dashboard-card p-3 h-100">
            <h3 class="h6 mb-3">Heartbeats <span class="text-muted fw-normal">· <?= $totalHb ?> registros</span></h3>
            <div class="dash-table-responsive">
                <table class="dash-table">
                    <?= cm_render_table_headers(['Hora', 'Estado', 'Latencia', 'Pérdida', 'CPU', 'RAM', 'Disco', 'Servidor']) ?>
                    <tbody>
                        <?php if ($hbRows): ?>
                            <?php foreach ($hbRows as $h): ?>
                                <?php
                                $hbEstado = $h['estado'];
                                $perdida = (float) $h['perdida_pct'];
                                if ($perdida >= 30) { $hbEstado = 'bad'; }
                                elseif ($perdida >= 10 || (int) $h['cpu'] >= 90 || (int) $h['ram'] >= 90) { $hbEstado = 'warn'; }
                                ?>
                                <tr>
                                    <td class="cell-dest"><?= date('H:i:s', (int) $h['timestamp']) ?></td>
                                    <td><?= cm_render_badge($hbEstado) ?></td>
                                    <td class="cell-duration live"><?= (int) $h['latencia'] ?> ms</td>
                                    <td class="cell-origin"><?= number_format($perdida, 1) ?>%</td>
                                    <td class="cell-pbx"><?= (int) $h['cpu'] ?>%</td>
                                    <td class="cell-pbx"><?= (int) $h['ram'] ?>%</td>
                                    <td class="cell-pbx"><?= (int) $h['disco'] ?>%</td>
                                    <td class="cell-origin"><?= htmlspecialchars($h['servidor']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No hay heartbeats registrados para esta central.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-2"><?= cm_render_pagination($page, $pages, $basePaginacion) ?></div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require SRC_PATH . '/layout/app.php';