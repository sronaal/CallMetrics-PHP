<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/ApiClient.php';
require_once __DIR__ . '/../core/AuthMiddleware.php';
require_once __DIR__ . '/../core/ApiClientHelpers.php';
require_once SRC_PATH . '/data/mock.php';
require_once SRC_PATH . '/components/components.php';

AuthMiddleware::check();
Session::touch();

$title = 'Eventos Asterisk';
$activeNav = 'events';
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css'];
$extraJs = [BASE_URL . 'assets/js/ws-client.js', BASE_URL . 'assets/js/events.js'];

ob_start();

/* ---------------- Filtros GET ---------------- */
$fSeveridad = (string) ($_GET['severidad'] ?? '');
$fTipo = (string) ($_GET['tipo'] ?? '');
$fPbx = (string) ($_GET['pbx'] ?? '');

/* ---------------- PBX lookup map ---------------- */
$pbxResponse = api_get_pbx(0, 100);
$pbxList = $pbxResponse['data'] ?? [];
$pbxMap = [];
$pbxNameToId = [];
foreach ($pbxList as $p) {
    $id = (int) ($p['id'] ?? 0);
    $nombre = $p['nombre'] ?? ('PBX-' . $id);
    $pbxMap[$id] = $nombre;
    $pbxNameToId[$nombre] = $id;
}

/* ---------------- Fetch eventos from API ---------------- */
$filters = [];
if ($fTipo !== '') {
    $filters['tipo'] = $fTipo;
}
if ($fPbx !== '' && isset($pbxNameToId[$fPbx])) {
    $filters['pbx_id'] = $pbxNameToId[$fPbx];
}

$response = api_get_eventos(0, 100, $filters);
$apiData = $response['data'] ?? [];
$apiTotal = $response['meta']['total'] ?? count($apiData);

/* ---------------- Helpers ---------------- */
/**
 * Derivar severidad a partir de tipo, nombre de evento y contenido.
 * La tabla eventos no tiene columna severidad; se infiere del contexto.
 */
function cm_derive_severity(string $tipo, string $evento, $contenido): string
{
    if (is_array($contenido)) {
        if (isset($contenido['error'])) {
            return 'error';
        }
        if (isset($contenido['reason']) && stripos((string) $contenido['reason'], 'fail') !== false) {
            return 'error';
        }
    }
    $warningEvents = ['Hangup', 'QueueCallerAbandon'];
    if (in_array($evento, $warningEvents, true)) {
        return 'warning';
    }
    if ($tipo === 'SYSTEM') {
        return 'warning';
    }
    return 'info';
}

/* ---------------- Map API → formato de display ---------------- */
$eventos = array_map(function ($ev) use ($pbxMap) {
    $ts = 0;
    if (!empty($ev['created_at'])) {
        $ts = (int) strtotime($ev['created_at']);
    }
    $contenido = $ev['contenido'] ?? [];
    if (is_string($contenido)) {
        $contenido = json_decode($contenido, true) ?? [];
    }
    return [
        'id'         => (int) $ev['id'],
        'timestamp'  => $ts,
        'severidad'  => cm_derive_severity($ev['tipo'] ?? '', $ev['evento'] ?? '', $contenido),
        'tipo'       => $ev['tipo'] ?? '',
        'pbx'        => $pbxMap[(int) ($ev['pbx_id'] ?? 0)] ?? ('PBX-' . ($ev['pbx_id'] ?? '?')),
        'evento'     => $ev['evento'] ?? '',
        'payload'    => $contenido,
    ];
}, $apiData);

/* ---------------- Severity filter (PHP-side, la API no lo soporta) ---------------- */
if ($fSeveridad !== '') {
    $eventos = array_filter($eventos, fn($e) => $e['severidad'] === $fSeveridad);
    $eventos = array_values($eventos);
}

/* ---------------- Orden: más recientes primero ---------------- */
usort($eventos, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

/* ---------------- Paginación ---------------- */
$total = count($eventos);
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 10;
$pages = (int) ceil($total / $per);
$rows = array_slice($eventos, ($page - 1) * $per, $per);

/* ---------------- Opciones de filtro ---------------- */
$tipos = [];
$pbxDisponibles = [];
foreach ($eventos as $e) {
    $tipos[$e['tipo']] = true;
    $pbxDisponibles[$e['pbx']] = true;
}
foreach ($pbxList as $p) {
    $nombre = $p['nombre'] ?? ('PBX-' . $p['id']);
    $pbxDisponibles[$nombre] = true;
}
ksort($tipos);
ksort($pbxDisponibles);
$severidades = ['info', 'warning', 'error'];

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
