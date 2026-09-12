<?php
require_once __DIR__ . '/../config.php';
require_once SRC_PATH . '/core/Session.php';
require_once SRC_PATH . '/data/mock.php';
require_once SRC_PATH . '/components/components.php';

$title = 'Eventos Asterisk';
$activeNav = 'events';
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css'];
$extraJs = [BASE_URL . 'assets/js/ws-client.js', BASE_URL . 'assets/js/events.js'];

ob_start();

/* ---------------- Datos + filtros ---------------- */
$eventos = cm_eventos();

$fSeveridad = (string) ($_GET['severidad'] ?? '');
$fTipo = (string) ($_GET['tipo'] ?? '');
$fPbx = (string) ($_GET['pbx'] ?? '');

if ($fSeveridad !== '') {
    $eventos = array_filter($eventos, fn($e) => $e['severidad'] === $fSeveridad);
}
if ($fTipo !== '') {
    $eventos = array_filter($eventos, fn($e) => $e['tipo'] === $fTipo);
}
if ($fPbx !== '') {
    $eventos = array_filter($eventos, fn($e) => $e['pbx'] === $fPbx);
}
$eventos = array_values($eventos);
/* Más recientes primero (el stream siempre muestra lo último arriba). */
usort($eventos, fn($a, $b) => (int) $b['timestamp'] <=> (int) $a['timestamp']);

/* Opciones de filtro derivadas del mock (sin duplicar datos). */
$tipos = [];
$pbxDisponibles = [];
foreach (cm_eventos() as $e) {
    $tipos[$e['tipo']] = true;
    $pbxDisponibles[$e['pbx']] = true;
}
ksort($tipos);
ksort($pbxDisponibles);
$severidades = ['info', 'warning', 'error'];

$total = count($eventos);
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 10;
$pages = (int) ceil($total / $per);
$rows = array_slice($eventos, ($page - 1) * $per, $per);
$basePaginacion = BASE_URL . 'asterisk-events.php?severidad=' . rawurlencode($fSeveridad)
    . '&tipo=' . rawurlencode($fTipo) . '&pbx=' . rawurlencode($fPbx) . '&';
?>

<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0">Eventos Asterisk</h2>
    <div class="d-flex align-items-center gap-3">
        <span id="wsStatusIndicator" class="text-muted small">
            <i class="bi bi-circle-fill text-secondary me-1"></i>Conectando...
        </span>
        <span class="text-muted"><?= $total ?> evento<?= $total === 1 ? '' : 's' ?> en el stream</span>
    </div>
</div>

<!-- Tenant ID para WebSocket (oculto) -->
<?php $sessionUser = Session::user(); ?>
<input type="hidden" id="cmTenantId" value="<?= (int) ($sessionUser['tenantId'] ?? 0) ?>" />

<!-- Filtros: severidad / tipo / PBX (recarga servidor; sin JS obligatorio) -->
<form class="row g-2 mb-3" method="get" action="<?= BASE_URL ?>asterisk-events.php">
    <div class="col-auto">
        <select class="form-select form-select-sm" name="severidad" onchange="this.form.submit()">
            <option value="">Severidad: todas</option>
            <?php foreach ($severidades as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>" <?= $fSeveridad === $s ? 'selected' : '' ?>>
                    <?= htmlspecialchars(cm_status_label($s)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <select class="form-select form-select-sm" name="tipo" onchange="this.form.submit()">
            <option value="">Tipo: todos</option>
            <?php foreach ($tipos as $t => $_) : ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= $fTipo === $t ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <select class="form-select form-select-sm" name="pbx" onchange="this.form.submit()">
            <option value="">PBX: todos</option>
            <?php foreach ($pbxDisponibles as $p => $_) : ?>
                <option value="<?= htmlspecialchars($p) ?>" <?= $fPbx === $p ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto ms-auto d-flex align-items-center">
        <button class="btn btn-sm btn-outline-secondary" type="button" id="eventsAutoscrollBtn"
                title="Pausar o reanudar el seguimiento automático del stream">
            <i class="bi bi-play-circle me-1"></i>Autoscroll: Activo
        </button>
    </div>
</form>

<!-- Tabla del stream -->
<div class="dashboard-card p-0">
    <div class="dash-table-responsive" id="eventsScrollContainer">
        <table class="dash-table">
            <?= cm_render_table_headers(['Timestamp', 'Severidad', 'Tipo', 'PBX', 'Evento', 'Payload']) ?>
            <tbody id="eventsTableRows">
                <?php if ($rows): ?>
                    <?php foreach ($rows as $e): ?>
                        <tr class="cm-event-row" data-event-id="<?= (int) $e['id'] ?>"
                            data-payload="<?= htmlspecialchars(json_encode($e['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>">
                            <td class="cell-pbx live"><?= htmlspecialchars(cm_format_date($e['timestamp'])) ?></td>
                            <td><?= cm_render_badge($e['severidad']) ?></td>
                            <td><?= htmlspecialchars($e['tipo']) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars($e['pbx']) ?></td>
                            <td><?= htmlspecialchars($e['evento']) ?></td>
                            <td class="text-end">
                                <button type="button" class="cm-row-action cm-events-toggle" title="Ver payload del evento">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="eventsNoRows">
                        <td colspan="6" class="text-center text-muted py-4">No hay eventos que coincidan con los filtros.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mt-3">
    <?= cm_render_pagination($page, $pages, $basePaginacion) ?>
    <button class="btn btn-sm btn-outline-primary" type="button" id="eventsLoadMore">
        <i class="bi bi-plus-circle me-1"></i>Cargar más
    </button>
</div>

<?php if ($page === 1 && !empty($eventos)): ?>
    <!-- Pool completo de eventos filtrados para "Cargar más" y la
         simulación del stream (consumido por assets/js/events.js). -->
    <script type="application/json" id="cmEventsPool"><?= json_encode($eventos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require SRC_PATH . '/layout/app.php';
