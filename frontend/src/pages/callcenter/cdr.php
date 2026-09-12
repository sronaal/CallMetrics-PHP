<?php
require_once __DIR__ . '/../../config.php';
require_once SRC_PATH . '/data/mock.php';
require_once SRC_PATH . '/core/ApiClient.php';
require_once SRC_PATH . '/components/components.php';

$title = 'Registro de Llamadas (CDR)';
$activeNav = 'cdr';
$ccLayout = true;
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css', BASE_URL . 'assets/css/callcenter.css'];
$extraJs = [BASE_URL . 'assets/js/ws-client.js', BASE_URL . 'assets/js/cdr.js'];

ob_start();

/* ---- Data source: API with mock fallback ---- */
$client = ApiClient::getInstance();
$desde = trim((string) ($_GET['desde'] ?? ''));
$hasta = trim((string) ($_GET['hasta'] ?? ''));

$apiParams = ['page' => 0, 'size' => 1000];
if ($desde !== '') $apiParams['fecha_inicio'] = $desde . ' 00:00:00';
if ($hasta !== '') $apiParams['fecha_fin'] = $hasta . ' 23:59:59';

$apiCdr = $client->get('/llamadas', $apiParams);
$useApi = !empty($apiCdr['success']) && isset($apiCdr['data']) && is_array($apiCdr['data']);

$cdr = [];
$colas = [];
$agentesCc = [];
$reportes = cm_reportes_diarios();

if ($useApi) {
    /* Fetch agents, colas, extensions for name resolution */
    $apiAgentes = $client->get('/agentes', ['page' => 0, 'size' => 1000]);
    $apiColasResult = $client->get('/colas', ['page' => 0, 'size' => 100]);
    $apiExt = $client->get('/extensiones', ['page' => 0, 'size' => 100]);

    $mapaExt = [];
    if (!empty($apiExt['success']) && isset($apiExt['data'])) {
        foreach ($apiExt['data'] as $e) { $mapaExt[$e['id']] = $e['numero']; }
    }

    $mapaExtAgente = [];
    $estadoMapAgent = ['DISPONIBLE' => 'active', 'EN_LLAMADA' => 'on-call', 'OCUPADO' => 'break', 'DESCONECTADO' => 'offline'];
    if (!empty($apiAgentes['success']) && isset($apiAgentes['data'])) {
        foreach ($apiAgentes['data'] as $a) {
            $extNum = $mapaExt[$a['extension_id']] ?? (string) ($a['extension_id'] ?? '');
            if ($extNum) {
                $mapaExtAgente[$extNum] = ['nombre' => $a['nombre'], 'cola_id' => $a['cola_id'] ?? null];
            }
            $agentesCc[] = [
                'id' => $a['id'], 'nombre' => $a['nombre'], 'extension' => $extNum,
                'cola_id' => $a['cola_id'] ?? null,
                'estado' => $estadoMapAgent[$a['estado']] ?? strtolower($a['estado'] ?? 'offline'),
            ];
        }
    }

    $mapaColas = [];
    if (!empty($apiColasResult['success']) && isset($apiColasResult['data'])) {
        foreach ($apiColasResult['data'] as $c) {
            $mapaColas[$c['id']] = $c['nombre'];
            $estadoMapCola = ['ACTIVA' => 'active', 'PAUSADA' => 'paused', 'INACTIVA' => 'inactive'];
            $colas[] = [
                'id' => $c['id'], 'nombre' => $c['nombre'],
                'en_espera' => $c['llamadas_enespera'] ?? 0,
                'nivel_servicio_pct' => 0, 'llamadas_hora' => 0,
                'estado' => $estadoMapCola[$c['estado']] ?? strtolower($c['estado'] ?? 'inactive'),
                'espera_max' => 0,
            ];
        }
    }

    $estadoMapCdr = ['ANSWERED' => 'answered', 'NOANSWER' => 'abandoned', 'BUSY' => 'busy', 'FAILED' => 'failed', 'CANCELLED' => 'cancelled'];
    foreach ($apiCdr['data'] as $r) {
        $extOrig = $r['extension_origen'] ?? '';
        $agenteNombre = '—';
        $colaNombre = '—';
        if ($extOrig && isset($mapaExtAgente[$extOrig])) {
            $agenteNombre = $mapaExtAgente[$extOrig]['nombre'];
            $colaId = $mapaExtAgente[$extOrig]['cola_id'];
            if ($colaId && isset($mapaColas[$colaId])) { $colaNombre = $mapaColas[$colaId]; }
        }
        $cdr[] = [
            'id' => $r['id'],
            'fecha' => strtotime($r['inicio_llamada'] ?? 'now'),
            'origen' => $r['numero_origen'] ?? '',
            'destino' => $r['numero_destino'] ?? '',
            'cola' => $colaNombre,
            'agente' => $agenteNombre,
            'duracion' => $r['duracion'] ?? 0,
            'resultado' => $estadoMapCdr[$r['estado']] ?? strtolower($r['estado'] ?? 'unknown'),
        ];
    }
} else {
    $cdr = cm_cdr();
    $colas = cm_colas_cc();
    $agentesCc = cm_agentes_cc();
    if ($desde !== '') {
        $tsDesde = strtotime($desde);
        if ($tsDesde) { $cdr = array_values(array_filter($cdr, fn($c) => (int) $c['fecha'] >= $tsDesde)); }
    }
    if ($hasta !== '') {
        $tsHasta = strtotime($hasta . ' 23:59:59');
        if ($tsHasta) { $cdr = array_values(array_filter($cdr, fn($c) => (int) $c['fecha'] <= $tsHasta)); }
    }
}

/* Métricas del header */
$totalLlamadas = count($cdr);
$answered = count(array_filter($cdr, fn($c) => $c['resultado'] === 'answered'));
$efectividad = $totalLlamadas > 0 ? round($answered / $totalLlamadas * 100) : 0;

/* Tab Llamadas: paginación */
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 10;
$pages = (int) ceil($totalLlamadas / $per);
$cdrRows = array_slice($cdr, ($page - 1) * $per, $per);
$basePaginacion = BASE_URL . 'callcenter/cdr.php?desde=' . rawurlencode($desde) . '&hasta=' . rawurlencode($hasta) . '&';

/* Tab Colas: agregación por cola (atendidas/abandonadas/total/SLA/espera prom) */
$resumenColas = [];
foreach ($colas as $c) {
    $atendidas = count(array_filter($cdr, fn($x) => $x['cola'] === $c['nombre'] && $x['resultado'] === 'answered'));
    $abandonadas = count(array_filter($cdr, fn($x) => $x['cola'] === $c['nombre'] && $x['resultado'] === 'abandoned'));
    $resumenColas[] = [
        'nombre' => $c['nombre'], 'atendidas' => $atendidas, 'abandonadas' => $abandonadas,
        'sla_pct' => $c['nivel_servicio_pct'], 'espera_prom' => $c['espera_max'],
    ];
}

/* Tab Agentes: agregación por agente */
$resumenAgentes = [];
foreach ($agentesCc as $a) {
    $agenteCdr = array_filter($cdr, fn($x) => $x['agente'] === substr($a['nombre'], 0, strpos($a['nombre'], ' ')));
    $agenteCdr = array_values($agenteCdr);
    $atendidas = count(array_filter($agenteCdr, fn($x) => $x['resultado'] === 'answered'));
    $totalAg = count($agenteCdr);
    $sumDur = array_sum(array_map(fn($x) => (int) $x['duracion'], $agenteCdr));
    $resumenAgentes[] = [
        'nombre' => $a['nombre'], 'atendidas' => $atendidas, 'total' => $totalAg,
        'aht' => $atendidas > 0 ? (int) round($sumDur / $atendidas) : 0,
        'efectividad' => $totalAg > 0 ? round($atendidas / $totalAg * 100) : 0,
    ];
}
usort($resumenAgentes, fn($a, $b) => $b['atendidas'] <=> $a['atendidas']);

/* Tab Global: reportes diarios */
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
    <?= cm_render_stat_card('Colas', count($resumenColas), '#a78bfa', 'bi-list-ul') ?>
    <?= cm_render_stat_card('Agentes', count($agentesCc), '#10b981', 'bi-people') ?>
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
                    <?php if ($cdrRows): ?>
                        <?php foreach ($cdrRows as $c): ?>
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