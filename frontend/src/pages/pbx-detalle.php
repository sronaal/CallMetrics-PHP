<?php
require_once __DIR__ . '/../config.php';
require_once SRC_PATH . '/data/mock.php';
require_once SRC_PATH . '/components/components.php';

$title = 'Detalle PBX';
$activeNav = 'pbx';
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css'];
$extraJs = [];

ob_start();

/* ---------------- Datos ---------------- */
$pbxId = max(1, (int) ($_GET['id'] ?? 1));
$pbxs = cm_pbx();
$pbx = null;
foreach ($pbxs as $p) {
    if ((int) $p['id'] === $pbxId) { $pbx = $p; break; }
}

if ($pbx === null) {
    echo '<div class="dashboard-card p-4">';
    echo '<h2 class="h4 mb-2">PBX no encontrado</h2>';
    echo '<p class="text-muted mb-3">El servidor solicitado no existe o fue eliminado.</p>';
    echo '<a class="btn btn-primary btn-sm" href="' . BASE_URL . 'pbx.php"><i class="bi bi-arrow-left me-1"></i>Volver a Centrales PBX</a>';
    echo '</div>';
    $content = ob_get_clean();
    require SRC_PATH . '/layout/app.php';
    return;
}

/* Heartbeats del PBX (últimos, descendente por timestamp) */
$heartbeats = array_values(array_filter(cm_heartbeats(), fn($h) => (int) $h['pbx_id'] === $pbxId));
usort($heartbeats, fn($a, $b) => strcmp((string) $b['timestamp'], (string) $a['timestamp']));
$totalHb = count($heartbeats);
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 15;
$pages = (int) ceil($totalHb / $per);
$hbRows = array_slice($heartbeats, ($page - 1) * $per, $per);
$basePaginacion = BASE_URL . 'pbx-detalle.php?id=' . $pbxId . '&';

/* Llamadas del día (suma de reportes) */
$llamadasDia = 0;
foreach (cm_reportes_diarios() as $r) { $llamadasDia += (int) $r['llamadas']; }

$estadoTxt = match ($pbx['estado']) { 'online' => 'En línea', 'degraded' => 'Degradado', default => 'Sin conexión' };
$versionTxt = $pbx['version'] !== '' ? $pbx['version'] : 'Sin datos';

$stats = [
    ['label' => 'Uptime', 'value' => cm_format_duration($pbx['uptime']), 'icon' => 'bi-arrow-up-short', 'clase' => ''],
    ['label' => 'Llamadas hoy', 'value' => number_format($llamadasDia), 'icon' => 'bi-telephone', 'clase' => ''],
    ['label' => 'CPU', 'value' => '—', 'icon' => 'bi-cpu', 'clase' => 'text-muted'],
    ['label' => 'RAM', 'value' => '—', 'icon' => 'bi-memory', 'clase' => 'text-muted'],
];
$ultimo = $hbRows[0] ?? null;
if ($ultimo) {
    $stats[2]['value'] = (int) $ultimo['cpu'] . '%';
    $stats[3]['value'] = (int) $ultimo['ram'] . '%';
}
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
                <dt class="col-4 text-muted">Host</dt><dd class="col-8 mb-2 text-end"><?= htmlspecialchars($pbx['host']) ?></dd>
                <dt class="col-4 text-muted">Puerto</dt><dd class="col-8 mb-2 text-end"><?= (int) $pbx['puerto'] ?></dd>
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