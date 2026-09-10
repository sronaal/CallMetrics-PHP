<?php
require_once __DIR__ . '/../../config.php';
require_once SRC_PATH . '/data/mock.php';
require_once SRC_PATH . '/components/components.php';

$title = 'Dashboard Call Center';
$activeNav = 'cc-dashboard';
$ccLayout = true;
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css', BASE_URL . 'assets/css/callcenter.css'];
$extraJs = [BASE_URL . 'assets/js/cc.js'];

ob_start();

$colas = cm_colas_cc();
$agentesCc = cm_agentes_cc();
$activas = cm_llamadas_activas();
$enCola = cm_llamadas_cola();

/* ---------------- KPIs ---------------- */
$enColaKpi = count(array_filter($enCola, fn($c) => $c['espera_seg'] ?? 0));
$activasKpi = count(array_filter($activas, fn($c) => $c['estado'] === 'activa'));
$disponibles = count(array_filter($agentesCc, fn($a) => $a['estado'] === 'active'));
$slaPromedio = $colas ? array_sum(array_column($colas, 'nivel_servicio_pct')) / count($colas) : 0;
$slaToken = cm_sla_color($slaPromedio);

/* Colas activas para QueueCards (Activa u overflow), hasta 6 */
$colasActivas = array_values(array_filter($colas, fn($q) => in_array($q['estado'], ['active', 'overflow'], true)));
$colasActivas = array_slice($colasActivas, 0, 6);
?>

<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0"><?= htmlspecialchars($title) ?></h2>
    <span class="text-muted">Actualizado en tiempo real</span>
</div>

<!-- KPIs -->
<div class="cm-kpi-row">
    <?= cm_render_stat_card('En Cola', $enColaKpi, '#f59e0b', 'bi-clock-history') ?>
    <?= cm_render_stat_card('Llamadas Activas', $activasKpi, '#4f6ef7', 'bi-telephone-inbound') ?>
    <?= cm_render_stat_card('Agentes Disponibles', $disponibles, '#10b981', 'bi-person-check') ?>
    <?= cm_render_stat_card('Nivel Servicio', htmlspecialchars(cm_format_number(round($slaPromedio))) . '%', '#a78bfa', 'bi-graph-up') ?>
</div>

<!-- Colas activas -->
<div class="mb-4">
    <h3 class="h6 text-uppercase text-muted mb-3">Colas activas</h3>
    <?php if ($colasActivas): ?>
        <div class="cm-queue-grid">
            <?php foreach ($colasActivas as $cola): ?>
                <?= cm_render_queue_card($cola) ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <?= cm_render_empty_state('Sin colas activas', 'No hay colas en estado Activa u overflow ahora mismo.', null, null, 'bi-list-ul') ?>
    <?php endif; ?>
</div>

<!-- Dos tablas: Llamadas Activas / En Cola -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="dashboard-card p-0">
            <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
                <span class="fw-semibold">Llamadas Activas</span>
                <span class="badge text-bg-success"><?= $activasKpi ?> activas</span>
            </div>
            <div class="p-0">
                <div id="ccActiveScroll" style="max-height:320px;overflow-y:auto">
                    <div class="dash-table-responsive">
                        <table class="dash-table mb-0">
                            <?= cm_render_table_headers(['Origen', 'Destino', 'Duración', 'Agente', 'PBX']) ?>
                            <tbody>
                                <?php if ($activas): ?>
                                    <?php foreach ($activas as $c): ?>
                                        <tr>
                                            <td class="cell-origin"><?= htmlspecialchars($c['origen']) ?></td>
                                            <td class="cell-pbx"><?= htmlspecialchars($c['destino']) ?></td>
                                            <td class="cell-duration live <?= cm_duracion_class($c['duracion']) ?>"><?= htmlspecialchars(cm_format_duration($c['duracion'])) ?></td>
                                            <td><?= $c['agente'] ? htmlspecialchars($c['agente']) : '<span class="text-muted">—</span>' ?></td>
                                            <td class="cell-pbx"><?= htmlspecialchars($c['pbx']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">Sin llamadas activas</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="px-3 py-2 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small" id="ccAutoScrollLabel">Autoscroll</span>
                    <button class="btn btn-sm btn-outline-secondary" type="button" id="ccPauseScroll" title="Pausar / reanudar autoscroll">
                        <i class="bi bi-pause-fill"></i> Pausar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="dashboard-card p-0">
            <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
                <span class="fw-semibold">Llamadas en Cola</span>
                <span class="badge text-bg-warning"><?= $enColaKpi ?> esperando</span>
            </div>
            <div class="p-0">
                <?php
                usort($enCola, fn($a, $b) => ($b['espera_seg'] ?? 0) <=> ($a['espera_seg'] ?? 0));
                ?>
                <div class="dash-table-responsive">
                    <table class="dash-table mb-0">
                        <?= cm_render_table_headers(['Número', 'Cola', 'Espera', 'PBX']) ?>
                        <tbody>
                            <?php if ($enCola): ?>
                                <?php foreach ($enCola as $c): ?>
                                    <tr>
                                        <td class="cell-origin"><?= htmlspecialchars($c['origen']) ?></td>
                                        <td class="cell-pbx"><?= htmlspecialchars($c['cola']) ?></td>
                                        <td class="cell-duration live <?= cm_espera_class($c['espera_seg']) ?>"><?= htmlspecialchars(cm_format_duration($c['espera_seg'])) ?></td>
                                        <td class="cell-pbx"><?= htmlspecialchars($c['pbx']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Sin llamadas en espera</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require SRC_PATH . '/layout/app.php';