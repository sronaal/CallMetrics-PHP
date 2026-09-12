<?php
require_once __DIR__ . '/../../config.php';
require_once SRC_PATH . '/data/mock.php';
require_once SRC_PATH . '/core/ApiClient.php';
require_once SRC_PATH . '/components/components.php';

$title = 'Colas';
$activeNav = 'colas';
$ccLayout = true;
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css', BASE_URL . 'assets/css/callcenter.css'];
$extraJs = [BASE_URL . 'assets/js/ws-client.js', BASE_URL . 'assets/js/cc.js'];

ob_start();

/* ---- Data source: API with mock fallback ---- */
$client = ApiClient::getInstance();
$apiColas = $client->get('/colas', ['page' => 0, 'size' => 1000]);
$useApi = !empty($apiColas['success']) && isset($apiColas['data']) && is_array($apiColas['data']);

$colas = [];

if ($useApi) {
    /* Map DB estado → frontend estado */
    $estadoMap = ['ACTIVA' => 'active', 'PAUSADA' => 'paused', 'INACTIVA' => 'inactive'];
    foreach ($apiColas['data'] as $c) {
        $colas[] = [
            'id' => $c['id'], 'nombre' => $c['nombre'],
            'en_espera' => $c['llamadas_enespera'] ?? 0,
            'nivel_servicio_pct' => 0,
            'llamadas_hora' => 0,
            'estado' => $estadoMap[$c['estado']] ?? strtolower($c['estado'] ?? 'inactive'),
            'espera_max' => 0,
        ];
    }
} else {
    $colas = cm_colas_cc();
}

$total = count($colas);
$activas = count(array_filter($colas, fn($q) => in_array($q['estado'], ['active', 'overflow'], true)));

/* Filtro de búsqueda por nombre */
$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $colas = array_values(array_filter($colas, fn($q2) => stripos((string) $q2['nombre'], $q) !== false));
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 10;
$pages = (int) ceil(count($colas) / $per);
$rows = array_slice(array_values($colas), ($page - 1) * $per, $per);
$basePaginacion = BASE_URL . 'callcenter/colas.php?q=' . rawurlencode($q) . '&';
?>

<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0"><?= htmlspecialchars($title) ?></h2>
    <span class="text-muted"><?= $activas ?> activas de <?= $total ?> en total</span>
</div>

<!-- Barra de herramientas -->
<form class="d-flex gap-2 mb-3" method="get" action="<?= BASE_URL ?>callcenter/colas.php">
    <input class="form-control form-control-sm" style="width:240px" type="text" name="q"
           value="<?= htmlspecialchars($q) ?>" placeholder="Buscar cola...">
    <button class="btn btn-primary btn-sm" type="submit">Buscar</button>
</form>

<!-- Tabla de colas -->
<div class="dashboard-card p-0">
    <div class="dash-table-responsive">
        <table class="dash-table">
            <?= cm_render_table_headers(['Cola', 'En Espera', 'Llamadas/hora', 'Nivel SLA', 'Espera Máx', 'Estado']) ?>
            <tbody id="ccQueueRows">
                <?php if ($rows): ?>
                    <?php foreach ($rows as $c): ?>
                        <tr data-row>
                            <td class="cell-dest fw-semibold"><?= htmlspecialchars($c['nombre']) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars((string) $c['en_espera']) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars((string) $c['llamadas_hora']) ?></td>
                            <td class="cell-duration live <?= 'cm-sla-' . cm_sla_color($c['nivel_servicio_pct']) ?>"><?= htmlspecialchars(cm_format_number($c['nivel_servicio_pct'])) ?>%</td>
                            <td class="cell-duration live <?= cm_espera_class($c['espera_max']) ?>"><?= htmlspecialchars(cm_format_duration($c['espera_max'])) ?></td>
                            <td><?= cm_render_badge($c['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="ccQueueNoRows"><td colspan="6" class="text-center text-muted py-4">No hay colas que coincidan con la búsqueda.</td></tr>
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