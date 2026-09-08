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

$title = 'Centrales PBX';
$activeNav = 'pbx';
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css'];
$extraJs = [BASE_URL . 'assets/js/pbx.js'];

ob_start();

/* ---------------- Datos ---------------- */
$page = max(1, (int)($_GET['page'] ?? 1));
$search = $_GET['q'] ?? $_GET['search'] ?? '';
$size = 10;

// Obtener datos de la API
$response = api_get_pbx($page - 1, $size, $search);
$pbxs = $response['data'] ?? [];
$total = $response['meta']['total'] ?? count($pbxs);
$totalPages = $response['meta']['totalPages'] ?? max(1, (int)ceil($total / $size));
$basePaginacion = BASE_URL . 'pbx.php?' . http_build_query(array_filter(['q' => $search])) . '&';
?>

<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0">Centrales PBX</h2>
    <span class="text-muted"><?= $total ?> central<?= $total === 1 ? '' : 'es' ?> de conmutación</span>
</div>

<!-- Barra de herramientas -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <form class="d-flex gap-2" method="get" action="<?= BASE_URL ?>pbx.php">
        <input class="form-control form-control-sm" style="width:240px" type="text" name="q"
               value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por nombre o host...">
        <button class="btn btn-primary btn-sm" type="submit">Buscar</button>
    </form>
    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#pbxCreateModal">
        <i class="bi bi-plus-lg me-1"></i>Registrar PBX
    </button>
</div>

<!-- Tabla -->
<div class="dashboard-card p-0">
    <div class="dash-table-responsive">
        <table class="dash-table">
            <?= cm_render_table_headers(['Nombre', 'Host', 'Puerto', 'Estado', 'Versión', 'Uptime', 'Acciones']) ?>
            <tbody id="pbxTableRows">
                <?php if (!empty($pbxs)): ?>
                    <?php foreach ($pbxs as $p): ?>
                        <tr data-pbx-id="<?= (int) ($p['id'] ?? 0) ?>">
                            <td class="cell-dest fw-semibold"><?= htmlspecialchars($p['nombre'] ?? '') ?></td>
                            <td class="cell-origin"><?= htmlspecialchars($p['ip_address'] ?? '') ?></td>
                            <td class="cell-pbx"><?= (int) ($p['puerto_ami'] ?? 0) ?></td>
                            <td><?= cm_render_badge($p['estado'] ?? 'offline') ?></td>
                            <td class="cell-origin"><?= htmlspecialchars($p['version'] ?? '—') ?></td>
                            <td class="cell-duration live"><?= ($p['uptime'] ?? 0) > 0 ? htmlspecialchars(cm_format_duration($p['uptime'])) : '—' ?></td>
                            <td>
                                <div class="cm-row-actions">
                                    <a class="cm-row-action" href="<?= BASE_URL ?>pbx-detalle.php?id=<?= (int) ($p['id'] ?? 0) ?>"
                                       title="Ver detalle"><i class="bi bi-eye"></i></a>
                                    <button class="cm-row-action danger" type="button" title="Eliminar"
                                            data-bs-toggle="modal" data-bs-target="#pbxDeleteModal"
                                            data-pbx-id="<?= (int) ($p['id'] ?? 0) ?>"
                                            data-pbx-name="<?= htmlspecialchars($p['nombre'] ?? '', ENT_QUOTES) ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="pbxNoRows"><td colspan="7" class="text-center text-muted py-4">No hay centrales PBX que coincidan con la búsqueda.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    <?= cm_render_pagination($page, $totalPages, $basePaginacion) ?>
</div>

<!-- Modal: Registrar PBX -->
<div class="modal fade" id="pbxCreateModal" tabindex="-1" aria-labelledby="pbxCreateTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pbxCreateTitle">Registrar Servidor PBX</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="pbxCreateForm" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="pbxNombre">Nombre del servidor <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="pbxNombre" name="nombre" required
                               placeholder="PBX Principal" minlength="2" maxlength="100">
                        <div class="invalid-feedback" id="pbxNombreError">El nombre es obligatorio (mínimo 2 caracteres).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pbxHost">Dirección IP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="pbxHost" name="ip_address" required
                               placeholder="192.168.1.10">
                        <div class="invalid-feedback" id="pbxHostError">La dirección IP es obligatoria.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="pbxPuerto">Puerto AMI</label>
                        <input type="number" class="form-control" id="pbxPuerto" name="puerto_ami"
                               placeholder="5038" min="1" max="65535" value="5038">
                        <div class="invalid-feedback" id="pbxPuertoError">El puerto debe estar entre 1 y 65535.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="pbxToken">Token de Agente <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="pbxToken" name="token_agente" required
                               placeholder="token-seguro-unico">
                        <div class="invalid-feedback">El token es obligatorio para autenticar el agente.</div>
                    </div>
                    <p class="cm-reveal-hint mb-0 mt-3">
                        IP, puerto, tipo y versión se completan cuando el agente envía el primer heartbeat.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Datos generados del agente de monitoreo (post alta) -->
<div class="modal fade" id="pbxRevealModal" tabindex="-1" aria-labelledby="pbxRevealTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pbxRevealTitle">PBX Registrado — Configurar Agente de Monitoreo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">ID del PBX</label>
                    <div class="cm-reveal-code">
                        <code id="revealPbxId">—</code>
                        <button type="button" class="cm-reveal-copy" data-copy="revealPbxId" title="Copiar ID"><i class="bi bi-copy"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Token de Acceso (<code>TOKEN_REGISTRO</code>)</label>
                    <div class="cm-reveal-code">
                        <code id="revealToken" class="text-info">—</code>
                        <button type="button" class="cm-reveal-copy" data-copy="revealToken" title="Copiar Token"><i class="bi bi-copy"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">ID del Agente (<code>AGENT_ID</code>)</label>
                    <div class="cm-reveal-code">
                        <code id="revealAgente" class="text-success">—</code>
                        <button type="button" class="cm-reveal-copy" data-copy="revealAgente" title="Copiar AGENT_ID"><i class="bi bi-copy"></i></button>
                    </div>
                </div>
                <p class="cm-reveal-hint mb-0">
                    Copie estos valores al archivo <code>.env</code> del agente en la PBX. El
                    <code>AGENT_ID</code> y <code>TOKEN_REGISTRO</code> son únicos y solo se muestran ahora.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="copiarTodo">
                    <i class="bi bi-copy me-1"></i>Copiar Todo
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Confirmar eliminación -->
<div class="modal fade" id="pbxDeleteModal" tabindex="-1" aria-labelledby="pbxDeleteTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pbxDeleteTitle">Eliminar Servidor PBX</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">¿Está seguro de eliminar <strong id="pbxDeleteName">—</strong>?</p>
                <p class="cm-reveal-hint mb-0">Esta acción no se puede deshacer. Los agentes asociados quedarán huérfanos.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="pbxDeleteConfirm">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<script>
/* CRUD de PBX conectado al backend API. */
(function () {
    'use strict';
    var API_BASE = '<?= Config::API_PROXY_URL ?>';
    var TOKEN = '<?= Session::token() ?>';
    var deletePbxId = null;

    function apiRequest(method, path, data) {
        var xhr = new XMLHttpRequest();
        xhr.open(method, API_BASE + path, false);
        xhr.setRequestHeader('Content-Type', 'application/json');
        if (TOKEN) xhr.setRequestHeader('Authorization', 'Bearer ' + TOKEN);
        if (data) xhr.send(JSON.stringify(data));
        else xhr.send();
        return JSON.parse(xhr.responseText);
    }

    /* Crear PBX */
    var createForm = document.getElementById('pbxCreateForm');
    if (createForm) {
        createForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var nombre = document.getElementById('pbxNombre').value.trim();
            var ip = document.getElementById('pbxHost').value.trim();
            var puerto = parseInt(document.getElementById('pbxPuerto').value) || 5038;
            var token = document.getElementById('pbxToken').value.trim();
            if (!nombre || !ip || !token) { return; }

            var result = apiRequest('POST', '/pbx', { nombre: nombre, ip_address: ip, puerto_ami: puerto, token_agente: token });
            if (result.success !== false && result.data) {
                /* Mostrar modal de reveal con los datos generados */
                document.getElementById('revealPbxId').textContent = result.data.id || '—';
                document.getElementById('revealToken').textContent = result.data.token_registro || '—';
                document.getElementById('revealAgente').textContent = result.data.agent_id || '—';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('pbxRevealModal')).show();
            } else {
                alert(result.message || 'Error al registrar PBX');
            }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('pbxCreateModal')).hide();
            createForm.reset();
        });
    }

    /* Eliminar PBX → captura el ID */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-bs-target="#pbxDeleteModal"]');
        if (btn && btn.getAttribute('data-pbx-id')) {
            deletePbxId = btn.getAttribute('data-pbx-id');
            document.getElementById('pbxDeleteName').textContent = btn.getAttribute('data-pbx-name') || '—';
        }
    });

    /* Confirmar eliminación */
    var confirmBtn = document.getElementById('pbxDeleteConfirm');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            if (!deletePbxId) { return; }
            var result = apiRequest('DELETE', '/pbx/' + deletePbxId);
            if (result.success !== false) {
                window.location.reload();
            } else {
                alert(result.message || 'Error al eliminar');
            }
        });
    }

    /* Copiar todo (reveal modal) */
    var copiarBtn = document.getElementById('copiarTodo');
    if (copiarBtn) {
        copiarBtn.addEventListener('click', function () {
            var id = document.getElementById('revealPbxId').textContent;
            var token = document.getElementById('revealToken').textContent;
            var agente = document.getElementById('revealAgente').textContent;
            var text = 'PBX_ID=' + id + '\nTOKEN_REGISTRO=' + token + '\nAGENT_ID=' + agente;
            navigator.clipboard.writeText(text).then(function () {
                copiarBtn.innerHTML = '<i class="bi bi-check me-1"></i>Copiado';
                setTimeout(function () { copiarBtn.innerHTML = '<i class="bi bi-copy me-1"></i>Copiar Todo'; }, 2000);
            });
        });
    }
})();
</script>

<?php
$content = ob_get_clean();
require SRC_PATH . '/layout/app.php';
