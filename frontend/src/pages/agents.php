<?php
require_once __DIR__ . '/../config.php';
require_once SRC_PATH . '/data/mock.php';
require_once SRC_PATH . '/components/components.php';

$title = 'Agentes';
$activeNav = 'agentes';
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css'];
$extraJs = [];

ob_start();

/* ---------------- Datos ---------------- */
$agentes = cm_agentes();
$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $agentes = array_filter($agentes, function ($a) use ($q) {
        return stripos((string) $a['nombre'], $q) !== false
            || stripos((string) $a['extension'], $q) !== false;
    });
}
$agentes = array_values($agentes);
usort($agentes, fn($a, $b) => strcasecmp((string) $a['nombre'], (string) $b['nombre']));

$total = count($agentes);
$conectados = count(array_filter($agentes, fn($a) => in_array($a['estado'], ['active', 'on-call', 'break', 'ringing'], true)));

$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 10;
$pages = (int) ceil($total / $per);
$rows = array_slice($agentes, ($page - 1) * $per, $per);
$basePaginacion = BASE_URL . 'agents.php?q=' . rawurlencode($q) . '&';
?>

<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0">Agentes</h2>
    <span class="text-muted"><?= $conectados ?> conectados de <?= $total ?> en total</span>
</div>

<!-- Barra de herramientas -->
<form class="d-flex gap-2 mb-3" method="get" action="<?= BASE_URL ?>agents.php">
    <input class="form-control form-control-sm" style="width:240px" type="text" name="q"
           value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por nombre o extensión...">
    <button class="btn btn-primary btn-sm" type="submit">Buscar</button>
</form>

<!-- Tabla -->
<div class="dashboard-card p-0">
    <div class="dash-table-responsive">
        <table class="dash-table">
            <?= cm_render_table_headers(['Agente', 'Extensión', 'Estado', 'Última Actividad', 'Último Heartbeat']) ?>
            <tbody>
                <?php if ($rows): ?>
                    <?php foreach ($rows as $a): ?>
                        <tr>
                            <td class="cell-dest fw-semibold">
                                <span class="cm-avatar"><?= htmlspecialchars(cm_initials($a['nombre'])) ?></span>
                                <?= htmlspecialchars($a['nombre']) ?>
                            </td>
                            <td class="cell-origin"><?= htmlspecialchars($a['extension']) ?></td>
                            <td><?= cm_render_badge($a['estado']) ?></td>
                            <td class="cell-duration live"><?= date('d/m/Y, H:i', (int) $a['ultima_actividad']) ?></td>
                            <td class="cell-origin"><?= date('d/m/Y, H:i', (int) $a['heartbeat']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay agentes que coincidan con la búsqueda.</td></tr>
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