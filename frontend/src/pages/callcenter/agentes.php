<?php
require_once __DIR__ . '/../../config.php';
require_once SRC_PATH . '/data/mock.php';
require_once SRC_PATH . '/components/components.php';

$title = 'Agentes Call Center';
$activeNav = 'cc-agentes';
$ccLayout = true;
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css', BASE_URL . 'assets/css/callcenter.css'];
$extraJs = [BASE_URL . 'assets/js/cc.js'];

ob_start();

$agentes = cm_agentes_cc();
$colas = cm_colas_cc();
$mapaColas = [];
foreach ($colas as $col) {
    $mapaColas[$col['id']] = $col['nombre'];
}
$total = count($agentes);
$disponibles = count(array_filter($agentes, fn($a) => $a['estado'] === 'active'));

/* Filtro por nombre/extensión */
$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $agentes = array_values(array_filter($agentes, fn($a) => stripos((string) $a['nombre'], $q) !== false
        || stripos((string) $a['extension'], $q) !== false));
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 10;
$pages = (int) ceil(count($agentes) / $per);
$rows = array_slice(array_values($agentes), ($page - 1) * $per, $per);
$basePaginacion = BASE_URL . 'callcenter/agentes.php?q=' . rawurlencode($q) . '&';
?>

<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0"><?= htmlspecialchars($title) ?></h2>
    <span class="text-muted"><?= $disponibles ?> disponibles de <?= $total ?> en total</span>
</div>

<!-- Barra de herramientas -->
<form class="d-flex gap-2 mb-3" method="get" action="<?= BASE_URL ?>callcenter/agentes.php">
    <input class="form-control form-control-sm" style="width:240px" type="text" name="q"
           value="<?= htmlspecialchars($q) ?>" placeholder="Buscar agente o extensión...">
    <button class="btn btn-primary btn-sm" type="submit">Buscar</button>
</form>

<!-- Tabla de agentes CC -->
<div class="dashboard-card p-0">
    <div class="dash-table-responsive">
        <table class="dash-table">
            <?= cm_render_table_headers(['Agente', 'Extensión', 'Cola', 'Estado', 'Tiempo en Estado', 'Llamadas Hoy', 'Espera Máx']) ?>
            <tbody id="ccAgentRows">
                <?php if ($rows): ?>
                    <?php foreach ($rows as $a): ?>
                        <tr data-row>
                            <td class="cell-dest fw-semibold">
                                <span class="cm-avatar"><?= htmlspecialchars(cm_initials($a['nombre'])) ?></span>
                                <?= htmlspecialchars($a['nombre']) ?>
                            </td>
                            <td class="cell-origin"><?= htmlspecialchars($a['extension']) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars($mapaColas[$a['cola_id']] ?? '—') ?></td>
                            <td><?= cm_render_badge($a['estado']) ?></td>
                            <td class="cell-duration live"><?= htmlspecialchars(cm_format_duration($a['tiempo_estado'])) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars((string) $a['llamadas_hoy']) ?></td>
                            <td class="cell-duration live <?= cm_espera_class($a['espera_max']) ?>"><?= htmlspecialchars(cm_format_duration($a['espera_max'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="ccAgentNoRows"><td colspan="7" class="text-center text-muted py-4">No hay agentes que coincidan con la búsqueda.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    <?= cm_render_pagination($page, $pages, $basePaginacion) ?>
</div>

<?php
$content = ob_get_clean();
require SRC_PATH . '/layout/app.php';