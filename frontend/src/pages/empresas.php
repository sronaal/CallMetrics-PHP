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

$title = 'Empresas';
$activeNav = 'empresas';
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css'];
$extraJs = [];

ob_start();

/* SOLO el rol SUPER_ADMIN administra empresas; cualquier otro rol
   ve el EmptyState "Sin permisos" en lugar de la tabla (spec). */
$esSuperAdmin = cm_rol() === 'SUPER_ADMIN';

// Paginacion y busqueda
$page = max(1, (int)($_GET['page'] ?? 1));
$search = $_GET['search'] ?? '';
$size = 10;

// Obtener datos de la API
$response = api_get_empresas($page - 1, $size, $search);
$empresas = $response['data'] ?? [];
$total = $response['meta']['total'] ?? count($empresas);
$totalPages = $response['meta']['totalPages'] ?? max(1, (int)ceil($total / $size));
$basePaginacion = BASE_URL . 'empresas.php?' . http_build_query(array_filter(['search' => $search])) . '&';

$planes = ['Enterprise', 'Pro', 'Trial'];
$estados = ['active', 'trial', 'suspended'];
?>

<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0">Empresas</h2>
    <span class="text-muted">Gestión de organizaciones clientes</span>
</div>

<?php if (!$esSuperAdmin): ?>
    <?= cm_render_empty_state(
        'Sin permisos',
        'Solo el rol Super Admin puede administrar las empresas del sistema.',
        BASE_URL . 'dashboard.php',
        'Volver al dashboard',
        'bi-shield-lock'
    ) ?>
<?php else: ?>

    <!-- Barra de herramientas -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <span class="text-muted small"><?= $total ?> empresa<?= $total === 1 ? '' : 's' ?> activas en la plataforma</span>
        <div class="d-flex gap-2 align-items-center">
            <form method="get" action="<?= BASE_URL ?>empresas.php" class="d-flex gap-2">
                <input class="form-control form-control-sm" style="width:220px" type="text" name="search"
                       value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por nombre o NIT...">
                <button class="btn btn-primary btn-sm" type="submit">Buscar</button>
            </form>
            <button class="btn btn-primary btn-sm" type="button" id="cmEmpresasNewBtn" data-bs-toggle="modal" data-bs-target="#cmEmpresasModal">
                <i class="bi bi-building-add me-1"></i>Nueva empresa
            </button>
        </div>
    </div>

    <!-- Tabla -->
    <div class="dashboard-card p-0">
        <div class="dash-table-responsive">
            <table class="dash-table">
                <?= cm_render_table_headers(['Nombre', 'NIT', 'Plan', 'Estado', 'Acciones']) ?>
                <tbody id="empresasTableRows">
                    <?php if (!empty($empresas)): ?>
                        <?php foreach ($empresas as $emp): ?>
                            <tr data-empresa-id="<?= (int) ($emp['id'] ?? 0) ?>">
                                <td class="cell-dest fw-semibold"><?= htmlspecialchars($emp['nombre'] ?? '') ?></td>
                                <td class="cell-origin"><?= htmlspecialchars($emp['nit'] ?? '') ?></td>
                                <td class="cell-pbx"><?= htmlspecialchars($emp['plan'] ?? '') ?></td>
                                <td><?= cm_render_badge($emp['activo'] ? 'active' : 'inactive') ?></td>
                                <td>
                                    <div class="cm-row-actions align-items-center">
                                        <button type="button" class="cm-row-action cm-empresas-edit"
                                                data-bs-toggle="modal" data-bs-target="#cmEmpresasModal"
                                                title="Editar empresa"
                                                data-id="<?= (int) ($emp['id'] ?? 0) ?>"
                                                data-nombre="<?= htmlspecialchars($emp['nombre'] ?? '', ENT_QUOTES) ?>"
                                                data-nit="<?= htmlspecialchars($emp['nit'] ?? '', ENT_QUOTES) ?>"
                                                data-email="<?= htmlspecialchars($emp['email'] ?? '', ENT_QUOTES) ?>"
                                                data-plan="<?= htmlspecialchars($emp['plan'] ?? '', ENT_QUOTES) ?>"
                                                data-activo="<?= ($emp['activo'] ?? false) ? '1' : '0' ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <div class="form-check form-switch cm-user-switch ms-2" title="Activar o suspender la empresa">
                                            <input class="form-check-input cm-empresas-toggle" type="checkbox" role="switch"
                                                   id="empresaToggle<?= (int) ($emp['id'] ?? 0) ?>" <?= ($emp['activo'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label small text-muted" for="empresaToggle<?= (int) ($emp['id'] ?? 0) ?>">Activa</label>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No hay empresas registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        <?= cm_render_pagination($page, $totalPages, $basePaginacion) ?>
    </div>

    <!-- Modal: Crear / Editar empresa -->
    <div class="modal fade" id="cmEmpresasModal" tabindex="-1" aria-labelledby="cmEmpresasTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cmEmpresasTitle">Nueva empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="cmEmpresasForm" novalidate>
                    <div class="modal-body">
                        <input type="hidden" id="cmEmpresa-id" value="">
                        <div class="mb-3">
                            <label class="form-label" for="cmEmpresa-nombre">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cmEmpresa-nombre" required minlength="3" maxlength="160"
                                   placeholder="Razón social">
                            <div class="invalid-feedback">El nombre es obligatorio (mínimo 3 caracteres).</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="cmEmpresa-nit">NIT</label>
                            <input type="text" class="form-control" id="cmEmpresa-nit" maxlength="20"
                                   placeholder="900123456-7">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="cmEmpresa-email">Email de contacto <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="cmEmpresa-email" required
                                   placeholder="contacto@empresa.com">
                            <div class="invalid-feedback">Ingrese un email válido.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="cmEmpresa-plan">Plan</label>
                            <select class="form-select" id="cmEmpresa-plan">
                                <?php foreach ($planes as $plan): ?>
                                    <option value="<?= htmlspecialchars($plan) ?>"><?= htmlspecialchars($plan) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    /* CRUD de empresas conectado al backend API. */
    (function () {
        'use strict';
        var modalEl = document.getElementById('cmEmpresasModal');
        var form = document.getElementById('cmEmpresasForm');
        var tbody = document.getElementById('empresasTableRows');
        var title = document.getElementById('cmEmpresasTitle');
        var API_BASE = '<?= Config::API_PROXY_URL ?>';
        var TOKEN = '<?= Session::token() ?>';

        function apiRequest(method, path, data) {
            var xhr = new XMLHttpRequest();
            xhr.open(method, API_BASE + path, false);
            xhr.setRequestHeader('Content-Type', 'application/json');
            if (TOKEN) xhr.setRequestHeader('Authorization', 'Bearer ' + TOKEN);
            if (data) xhr.send(JSON.stringify(data));
            else xhr.send();
            return JSON.parse(xhr.responseText);
        }

        function esc(v) {
            var d = document.createElement('div');
            d.textContent = String(v == null ? '' : v);
            return d.innerHTML;
        }

        function editBtnHTML(data) {
            return '<button type="button" class="cm-row-action cm-empresas-edit" ' +
                'data-bs-toggle="modal" data-bs-target="#cmEmpresasModal" title="Editar empresa" ' +
                'data-id="' + esc(data.id) + '" data-nombre="' + esc(data.nombre) + '" ' +
                'data-nit="' + esc(data.nit || '') + '" data-plan="' + esc(data.plan) + '" ' +
                'data-activo="' + esc(data.activo ? '1' : '0') + '">' +
                '<i class="bi bi-pencil"></i></button>';
        }

        function switchHTML(id, activo) {
            return '<div class="form-check form-switch cm-user-switch ms-2" title="Activar o suspender la empresa">' +
                '<input class="form-check-input cm-empresas-toggle" type="checkbox" role="switch" ' +
                'id="empresaToggle' + esc(id) + '"' + (activo ? ' checked' : '') + '>' +
                '<label class="form-check-label small text-muted" for="empresaToggle' + esc(id) + '">Activa</label></div>';
        }

        document.getElementById('cmEmpresasNewBtn').addEventListener('click', function () {
            form.reset();
            document.getElementById('cmEmpresa-id').value = '';
            title.textContent = 'Nueva empresa';
        });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.cm-empresas-edit');
            if (!btn) { return; }
            title.textContent = 'Editar empresa';
            document.getElementById('cmEmpresa-id').value = btn.getAttribute('data-id') || '';
            document.getElementById('cmEmpresa-nombre').value = btn.getAttribute('data-nombre') || '';
            document.getElementById('cmEmpresa-nit').value = btn.getAttribute('data-nit') || '';
            document.getElementById('cmEmpresa-email').value = btn.getAttribute('data-email') || '';
            document.getElementById('cmEmpresa-plan').value = btn.getAttribute('data-plan') || '';
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var id = document.getElementById('cmEmpresa-id').value;
            var nombre = document.getElementById('cmEmpresa-nombre').value.trim();
            var nit = document.getElementById('cmEmpresa-nit').value.trim();
            var email = document.getElementById('cmEmpresa-email').value.trim();
            var plan = document.getElementById('cmEmpresa-plan').value;
            if (!nombre || !email) { return; }

            var payload = { nombre: nombre, nit: nit, email: email, plan: plan };

            if (id) {
                var result = apiRequest('PUT', '/tenants/' + id, payload);
                if (result.success !== false) {
                    window.location.reload();
                } else {
                    alert(result.message || 'Error al actualizar');
                }
            } else {
                var result = apiRequest('POST', '/tenants', payload);
                if (result.success !== false) {
                    window.location.reload();
                } else {
                    alert(result.message || 'Error al crear');
                }
            }
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        });

        /* Toggle activo → envia PATCH al backend */
        document.addEventListener('change', function (e) {
            var sw = e.target.closest('.cm-empresas-toggle');
            if (!sw) { return; }
            var id = sw.id.replace('empresaToggle', '');
            var result = apiRequest('PATCH', '/tenants/' + id + '/toggle');
            var badge = sw.closest('tr').querySelector('.cm-badge');
            if (badge) {
                if (sw.checked) { badge.className = 'cm-badge cm-badge-ok'; badge.textContent = 'Activo'; }
                else { badge.className = 'cm-badge cm-badge-bad'; badge.textContent = 'Suspendida'; }
            }
        });
    })();
    </script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require SRC_PATH . '/layout/app.php';
