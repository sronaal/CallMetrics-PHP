<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/AuthMiddleware.php';
require_once SRC_PATH . '/core/ApiClient.php';
require_once SRC_PATH . '/core/ApiClientHelpers.php';
require_once SRC_PATH . '/components/components.php';

AuthMiddleware::check();
Session::touch();

$title = 'Registro de Llamadas (CDR)';
$activeNav = 'cdr';
$ccLayout = true;
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css', BASE_URL . 'assets/css/callcenter.css'];
$extraJs = [BASE_URL . 'assets/js/ws-client.js', BASE_URL . 'assets/js/cdr.js'];

ob_start();

/* ---- Data source: CDR Report API (agente-collector nested datasets) ---- */
$desde = trim((string) ($_GET['desde'] ?? ''));
$hasta = trim((string) ($_GET['hasta'] ?? ''));

$filters = [];
if ($desde !== '') $filters['fecha_inicio'] = $desde . ' 00:00:00';
if ($hasta !== '') $filters['fecha_fin'] = $hasta . ' 23:59:59';

/* KPI stats from cdr-report/stats */
$apiStats = api_get_cdr_stats();
$statsData = (!empty($apiStats['success']) && isset($apiStats['data'])) ? $apiStats['data'] : [];

/* Tab Llamadas: paginated individual calls */
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 10;
$apiCdrLlamadas = api_get_cdr_llamadas($page - 1, $per, $filters);
$useApi = !empty($apiCdrLlamadas['success']) && isset($apiCdrLlamadas['data']);
$totalFromApi = 0;
if ($useApi && isset($apiCdrLlamadas['meta'])) {
    $totalFromApi = (int) ($apiCdrLlamadas['meta']['total'] ?? 0);
}

$cdr = [];
if ($useApi) {
    $estadoMapCdr = ['ANSWERED' => 'answered', 'NOANSWER' => 'abandoned', 'BUSY' => 'busy',
        'FAILED' => 'failed', 'CANCELLED' => 'cancelled', 'NO ANSWER' => 'abandoned',
        'CONGESTION' => 'busy', 'CHANUNAVAIL' => 'failed', 'ANSWER' => 'answered'];
    foreach ($apiCdrLlamadas['data'] as $r) {
        $cdr[] = [
            'id' => $r['id'],
            'fecha' => strtotime($r['fecha_inicio'] ?? 'now'),
            'origen' => $r['numero_origen'] ?? '',
            'destino' => $r['destino_inicial'] ?? '',
            'cola' => $r['nombre_cola'] ?? '—',
            'agente' => $r['nombre_agente'] ?? '—',
            'duracion' => (int) ($r['tiempo_conversacion'] ?? 0),
            'resultado' => $estadoMapCdr[$r['estado_final']] ?? strtolower($r['estado_final'] ?? 'unknown'),
        ];
    }
}

/* Tab Colas: aggregated queue stats */
$apiColas = api_get_cdr_colas();
$resumenColas = [];
if (!empty($apiColas['success']) && isset($apiColas['data'])) {
    foreach ($apiColas['data'] as $c) {
        $resumenColas[] = [
            'nombre' => $c['numero_cola'] ?? '—',
            'atendidas' => (int) ($c['contestadas'] ?? 0),
            'abandonadas' => (int) ($c['no_contestadas'] ?? 0),
            'sla_pct' => (float) ($c['porcentaje_efectividad'] ?? 0),
            'espera_prom' => (int) ($c['promedio_espera'] ?? 0),
        ];
    }
}

/* Tab Agentes: aggregated agent stats */
$apiAgentesRes = api_get_cdr_agentes();
$resumenAgentes = [];
if (!empty($apiAgentesRes['success']) && isset($apiAgentesRes['data'])) {
    foreach ($apiAgentesRes['data'] as $a) {
        $totalAg = (int) ($a['total_llamadas'] ?? 0);
        $atendidas = (int) ($a['contestadas'] ?? 0);
        $resumenAgentes[] = [
            'nombre' => $a['nombre_agente'] ?? $a['extension_agente'] ?? '—',
            'atendidas' => $atendidas,
            'total' => $totalAg,
            'aht' => (int) ($a['promedio_duracion'] ?? 0),
            'efectividad' => (int) ($a['porcentaje_efectividad'] ?? 0),
        ];
    }
}
usort($resumenAgentes, fn($a, $b) => $b['atendidas'] <=> $a['atendidas']);

/* Tab Global: per-call queue stats */
$apiEstadisticas = api_get_cdr_estadisticas(0, 200, $filters);
$reportes = [];
if (!empty($apiEstadisticas['success']) && isset($apiEstadisticas['data'])) {
    foreach ($apiEstadisticas['data'] as $e) {
        $reportes[] = [
            'fecha' => strtotime($e['fecha_entrada'] ?? 'now'),
            'cola' => $e['numero_cola'] ?? '—',
            'llamadas' => 1,
            'atendidas' => in_array(strtoupper($e['estado_final'] ?? ''), ['ANSWERED','ANSWER']) ? 1 : 0,
            'abandonadas' => in_array(strtoupper($e['estado_final'] ?? ''), ['NOANSWER','NO ANSWER','CANCELLED']) ? 1 : 0,
            'sla_pct' => (int) ($e['duracion_seg'] ?? 0) > 0 ? 100 : 0,
        ];
    }
}

/* Derived metrics for KPI cards */
$totalLlamadas = (int) ($statsData['total_llamadas'] ?? 0);
$answered = (int) ($statsData['contestadas'] ?? 0);
$efectividad = (int) ($statsData['efectividad'] ?? 0);
$totalColas = (int) ($statsData['total_colas'] ?? 0);
$totalAgentes = (int) ($statsData['total_agentes'] ?? 0);

/* Tab Llamadas: pagination */
$pages = $totalFromApi > 0 ? (int) ceil($totalFromApi / $per) : 1;
$basePaginacion = BASE_URL . 'callcenter/cdr.php?desde=' . rawurlencode($desde) . '&hasta=' . rawurlencode($hasta) . '&';
?>

<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0"><?= htmlspecialchars($title) ?></h2>
    <button class="btn btn-primary btn-sm" type="button" id="cdrExportBtn">
        <i class="bi bi-download me-1"></i>Exportar CSV
    </button>
</div>

<!-- Métricas -->
<div class="cm-kpi-row">
    <?= cm_render_stat_card('Llamadas', $totalLlamadas, '#4f6ef7', 'bi-telephone') ?>
    <?= cm_render_stat_card('Colas', $totalColas, '#a78bfa', 'bi-list-ul') ?>
    <?= cm_render_stat_card('Agentes', $totalAgentes, '#10b981', 'bi-people') ?>
    <?= cm_render_stat_card('Efectividad', $efectividad . '%', '#f59e0b', 'bi-bullseye') ?>
</div>

<!-- Filtros de fecha -->
<form class="cm-cdr-filters mb-3" method="get" action="<?= BASE_URL ?>callcenter/cdr.php">
    <label for="cdrDesde">Desde:</label>
    <input class="form-control form-control-sm" type="date" id="cdrDesde" name="desde" value="<?= htmlspecialchars($desde) ?>">
    <label for="cdrHasta">Hasta:</label>
    <input class="form-control form-control-sm" type="date" id="cdrHasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
    <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
    <?php if ($desde !== '' || $hasta !== ''): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>callcenter/cdr.php">Limpiar</a>
    <?php endif; ?>
</form>

<!-- Tabs -->
<div class="dashboard-card p-0">
    <div class="cm-tabs">
        <button class="cm-tab active" type="button" data-target="cdrPanelLlamadas">Llamadas</button>
        <button class="cm-tab" type="button" data-target="cdrPanelColas">Colas</button>
        <button class="cm-tab" type="button" data-target="cdrPanelAgentes">Agentes</button>
        <button class="cm-tab" type="button" data-target="cdrPanelGlobal">Global</button>
    </div>

    <!-- Panel Llamadas -->
    <div class="cm-tab-panel p-3" id="cdrPanelLlamadas">
        <div class="dash-table-responsive">
            <table class="dash-table" id="cdrTable">
                <?= cm_render_table_headers(['Fecha', 'Origen', 'Destino', 'Cola', 'Agente', 'Duración', 'Resultado']) ?>
                <tbody>
                    <?php if ($cdr): ?>
                        <?php foreach ($cdr as $c): ?>
                            <tr>
                                <td class="cell-pbx"><?= htmlspecialchars(cm_format_date($c['fecha'])) ?></td>
                                <td class="cell-origin"><?= htmlspecialchars($c['origen']) ?></td>
                                <td class="cell-pbx"><?= htmlspecialchars($c['destino']) ?></td>
                                <td class="cell-pbx"><?= htmlspecialchars($c['cola']) ?></td>
                                <td><?= $c['agente'] ? htmlspecialchars($c['agente']) : '<span class="text-muted">—</span>' ?></td>
                                <td class="cell-duration live <?= cm_duracion_class($c['duracion']) ?>"><?= htmlspecialchars(cm_format_duration($c['duracion'])) ?></td>
                                <td><?= cm_render_badge($c['resultado']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay registros CDR en el rango seleccionado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <?= cm_render_pagination($page, $pages, $basePaginacion) ?>
        </div>
    </div>

    <!-- Panel Colas -->
    <div class="cm-tab-panel p-3 d-none" id="cdrPanelColas">
        <div class="dash-table-responsive">
            <table class="dash-table">
                <?= cm_render_table_headers(['Cola', 'Atendidas', 'Abandonadas', 'Total', 'SLA', 'T. Espera']) ?>
                <tbody>
                    <?php foreach ($resumenColas as $r): ?>
                        <tr>
                            <td class="cell-dest fw-semibold"><?= htmlspecialchars($r['nombre']) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars((string) $r['atendidas']) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars((string) $r['abandonadas']) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars((string) ($r['atendidas'] + $r['abandonadas'])) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="cm-sla-bar"><div class="cm-sla-bar-fill <?= cm_sla_color($r['sla_pct']) ?>" style="width:<?= min(100, (float) $r['sla_pct']) ?>%"></div></div>
                                    <span class="small"><?= htmlspecialchars(cm_format_number($r['sla_pct'])) ?>%</span>
                                </div>
                            </td>
                            <td class="cell-duration live <?= cm_espera_class($r['espera_prom']) ?>"><?= htmlspecialchars(cm_format_duration($r['espera_prom'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel Agentes -->
    <div class="cm-tab-panel p-3 d-none" id="cdrPanelAgentes">
        <div class="dash-table-responsive">
            <table class="dash-table">
                <?= cm_render_table_headers(['Agente', 'Atendidas', 'Total', 'AHT', 'Efectividad']) ?>
                <tbody>
                    <?php foreach ($resumenAgentes as $r): ?>
                        <tr>
                            <td class="cell-dest fw-semibold"><?= htmlspecialchars($r['nombre']) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars((string) $r['atendidas']) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars((string) $r['total']) ?></td>
                            <td class="cell-duration live"><?= htmlspecialchars(cm_format_duration($r['aht'])) ?></td>
                            <td class="cell-duration live"><?= htmlspecialchars((string) $r['efectividad']) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel Global -->
    <div class="cm-tab-panel p-3 d-none" id="cdrPanelGlobal">
        <div class="dash-table-responsive">
            <table class="dash-table">
                <?= cm_render_table_headers(['Fecha', 'Cola', 'Llamadas', 'Atendidas', 'Abandonadas', 'SLA']) ?>
                <tbody>
                    <?php foreach ($reportes as $r): ?>
                        <tr>
                            <td class="cell-pbx"><?= htmlspecialchars(cm_format_date($r['fecha'])) ?></td>
                            <td class="cell-dest fw-semibold"><?= htmlspecialchars($r['cola']) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars((string) $r['llamadas']) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars((string) $r['atendidas']) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars((string) $r['abandonadas']) ?></td>
                            <td class="cell-duration live <?= 'cm-sla-' . cm_sla_color($r['sla_pct']) ?>"><?= htmlspecialchars(cm_format_number($r['sla_pct'])) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require SRC_PATH . '/layout/app.php';