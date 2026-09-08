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

$title = 'Usuarios';
$activeNav = 'users';
$extraCss = [BASE_URL . 'assets/css/dashboard.css', BASE_URL . 'assets/css/pages.css'];
$extraJs = [];

ob_start();

/* ---------------- Datos ---------------- */
$esSuperAdmin = cm_rol() === 'SUPER_ADMIN';

// Paginacion y busqueda
$page = max(1, (int)($_GET['page'] ?? 1));
$search = $_GET['search'] ?? '';
$size = 10;

// Obtener datos de la API
$response = api_get_usuarios($page - 1, $size, $search);
$usuarios = $response['data'] ?? [];
$total = $response['meta']['total'] ?? count($usuarios);
$totalPages = $response['meta']['totalPages'] ?? max(1, (int)ceil($total / $size));
$basePaginacion = BASE_URL . 'users.php?' . http_build_query(array_filter(['search' => $search])) . '&';

// Obtener empresas para el mapa
$empresasResponse = api_get_empresas(0, 100);
$empresasList = $empresasResponse['data'] ?? [];
$empresasMap = [];
foreach ($empresasList as $emp) {
    $empresasMap[$emp['id']] = $emp['nombre'];
}
$rolesDisponibles = ['SUPER_ADMIN', 'ADMIN_TENANT', 'SUPERVISOR', 'OPERADOR'];
?>

<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0">Usuarios</h2>
    <span class="text-muted"><?= $total ?> usuario<?= $total === 1 ? '' : 's' ?> registrados</span>
</div>

<!-- Barra de herramientas -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex gap-2 align-items-center">
        <span class="text-muted small">Rol actual: <?= htmlspecialchars(cm_t(cm_rol())) ?></span>
        <form method="get" action="<?= BASE_URL ?>users.php" class="d-flex gap-2">
            <input class="form-control form-control-sm" style="width:220px" type="text" name="search"
                   value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por nombre o email...">
            <button class="btn btn-primary btn-sm" type="submit">Buscar</button>
        </form>
    </div>
    <button class="btn btn-primary btn-sm" type="button" id="cmUsersNewBtn" data-bs-toggle="modal" data-bs-target="#cmUsersModal">
        <i class="bi bi-person-plus me-1"></i>Nuevo usuario
    </button>
</div>

<!-- Tabla -->
<div class="dashboard-card p-0">
    <div class="dash-table-responsive">
        <table class="dash-table">
            <?= cm_render_table_headers(['Usuario', 'Nombre', 'Email', 'Rol', 'Empresa', 'Estado', 'Acciones']) ?>
            <tbody id="usersTableRows">
                <?php if (!empty($usuarios)): ?>
                    <?php foreach ($usuarios as $u): ?>
                        <?php
                        $protegido = !$esSuperAdmin && in_array($u['rol'] ?? '', ['SUPER_ADMIN', 'ADMIN_TENANT'], true);
                        $editarTitle = $protegido ? 'No tiene permisos para modificar administradores' : 'Editar usuario';
                        $empresaNombre = isset($empresasMap[$u['empresa_id'] ?? 0]) ? $empresasMap[$u['empresa_id']] : '—';
                        $activo = $u['activo'] ?? ($u['estado'] === 'active');
                        ?>
                        <tr data-user-id="<?= (int) ($u['id'] ?? 0) ?>">
                            <td class="cell-dest fw-semibold">
                                <span class="cm-avatar"><?= htmlspecialchars(cm_initials($u['nombre'] ?? '')) ?></span>
                                <?= htmlspecialchars($u['usuario'] ?? '') ?>
                            </td>
                            <td><?= htmlspecialchars($u['nombre'] ?? '') ?></td>
                            <td class="cell-origin"><?= htmlspecialchars($u['email'] ?? '') ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars(cm_t($u['rol'] ?? '')) ?></td>
                            <td class="cell-pbx"><?= htmlspecialchars($empresaNombre) ?></td>
                            <td><span class="cm-badge cm-badge-<?= $activo ? 'ok' : 'muted' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
                            <td>
                                <div class="cm-row-actions align-items-center">
                                    <button type="button" class="cm-row-action cm-users-edit"
                                            <?= $protegido ? 'disabled' : 'data-bs-toggle="modal" data-bs-target="#cmUsersModal"' ?>
                                            title="<?= htmlspecialchars($editarTitle) ?>"
                                            data-id="<?= (int) ($u['id'] ?? 0) ?>"
                                            data-usuario="<?= htmlspecialchars($u['usuario'] ?? '', ENT_QUOTES) ?>"
                                            data-nombre="<?= htmlspecialchars($u['nombre'] ?? '', ENT_QUOTES) ?>"
                                            data-email="<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>"
                                            data-rol="<?= htmlspecialchars($u['rol'] ?? '', ENT_QUOTES) ?>"
                                            data-empresa="<?= (int) ($u['empresa_id'] ?? 0) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <div class="form-check form-switch cm-user-switch ms-2" title="<?= $protegido ? 'Requiere rol Super Admin' : 'Activar o desactivar usuario' ?>">
                                        <input class="form-check-input cm-users-toggle" type="checkbox" role="switch"
                                               id="userToggle<?= (int) ($u['id'] ?? 0) ?>" <?= $activo ? 'checked' : '' ?>
                                               <?= $protegido ? 'disabled' : '' ?>>
                                        <label class="form-check-label small text-muted" for="userToggle<?= (int) ($u['id'] ?? 0) ?>">Activo</label>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay usuarios registrados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    <?= cm_render_pagination($page, $totalPages, $basePaginacion) ?>
</div>

<!-- Modal: Crear / Editar usuario -->
<div class="modal fade" id="cmUsersModal" tabindex="-1" aria-labelledby="cmUsersTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cmUsersTitle">Nuevo usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="cmUsersForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="cmUser-id" value="">
                    <div class="mb-3">
                        <label class="form-label" for="cmUser-usuario">Usuario <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cmUser-usuario" required minlength="3" maxlength="50"
                               placeholder="ej. jmendoza">
                        <div class="invalid-feedback">El usuario es obligatorio (mínimo 3 caracteres).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cmUser-nombre">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cmUser-nombre" required minlength="2" maxlength="120"
                               placeholder="Nombre y apellido">
                        <div class="invalid-feedback">El nombre es obligatorio.</div>
                    </div>
                        <div class="mb-3">
                            <label class="form-label" for="cmUser-email">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="cmUser-email" required
                                   placeholder="correo@empresa.com">
                            <div class="invalid-feedback">Ingrese un email válido.</div>
                        </div>
                        <div class="mb-3" id="cmUser-password-group">
                            <label class="form-label" for="cmUser-password">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="cmUser-password" minlength="6"
                                   placeholder="Mínimo 6 caracteres">
                            <div class="invalid-feedback">La contraseña debe tener al menos 6 caracteres.</div>
                        </div>
                    <div class="mb-3">
                        <label class="form-label" for="cmUser-rol">Rol <span class="text-danger">*</span></label>
                        <select class="form-select" id="cmUser-rol" required>
                            <?php foreach ($rolesDisponibles as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars(cm_t($r)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="cmUser-empresa">Tenant</label>
                        <select class="form-select" id="cmUser-empresa">
                            <option value="">Sin asignar</option>
                            <?php foreach ($empresasList as $emp): ?>
                                <option value="<?= (int) ($emp['id'] ?? 0) ?>"><?= htmlspecialchars($emp['nombre'] ?? '') ?> (ID: <?= (int) ($emp['id'] ?? 0) ?>)</option>
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
/* CRUD de usuarios conectado al backend API. */
(function () {
    'use strict';
    var modalEl = document.getElementById('cmUsersModal');
    var form = document.getElementById('cmUsersForm');
    var tbody = document.getElementById('usersTableRows');
    var title = document.getElementById('cmUsersTitle');
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
        return '<button type="button" class="cm-row-action cm-users-edit" ' +
            'data-bs-toggle="modal" data-bs-target="#cmUsersModal" title="Editar usuario" ' +
            'data-id="' + esc(data.id) + '" data-usuario="' + esc(data.usuario) + '" ' +
            'data-nombre="' + esc(data.nombre) + '" data-email="' + esc(data.email) + '" ' +
            'data-rol="' + esc(data.rol) + '" data-empresa="' + esc(data.empresa) + '">' +
            '<i class="bi bi-pencil"></i></button>';
    }

    function switchHTML(id, activo) {
        return '<div class="form-check form-switch cm-user-switch ms-2" title="Activar o desactivar usuario">' +
            '<input class="form-check-input cm-users-toggle" type="checkbox" role="switch" ' +
            'id="userToggle' + esc(id) + '"' + (activo ? ' checked' : '') + '>' +
            '<label class="form-check-label small text-muted" for="userToggle' + esc(id) + '">Activo</label></div>';
    }

    /* Botón "Nuevo usuario" → limpia el formulario */
    document.getElementById('cmUsersNewBtn').addEventListener('click', function () {
        form.reset();
        document.getElementById('cmUser-id').value = '';
        title.textContent = 'Nuevo usuario';
    });

    /* Editar → precarga el formulario (delegado: también cubre filas nuevas) */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cm-users-edit');
        if (!btn) { return; }
        title.textContent = 'Editar usuario';
        document.getElementById('cmUser-id').value = btn.getAttribute('data-id') || '';
        document.getElementById('cmUser-usuario').value = btn.getAttribute('data-usuario') || '';
        document.getElementById('cmUser-nombre').value = btn.getAttribute('data-nombre') || '';
        document.getElementById('cmUser-email').value = btn.getAttribute('data-email') || '';
        document.getElementById('cmUser-rol').value = btn.getAttribute('data-rol') || '';
        document.getElementById('cmUser-empresa').value = btn.getAttribute('data-empresa') || '';
        document.getElementById('cmUser-password').value = '';
    });

    /* Guardar: crea o actualiza via API */
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var id = document.getElementById('cmUser-id').value;
        var nombre = document.getElementById('cmUser-nombre').value.trim();
        var email = document.getElementById('cmUser-email').value.trim();
        var password = document.getElementById('cmUser-password').value;
        var rol = document.getElementById('cmUser-rol').value;
        var empresa = document.getElementById('cmUser-empresa').value;
        if (!nombre || !email || !rol) { return; }
        if (!id && password.length < 6) { alert('La contraseña debe tener al menos 6 caracteres.'); return; }

        var payload = {
            nombre: nombre,
            email: email,
            rol: rol,
            tenant_id: empresa ? parseInt(empresa) : null
        };
        if (!id) payload.password = password;

        if (id) {
            var result = apiRequest('PUT', '/usuarios/' + id, payload);
            if (result.success !== false) {
                window.location.reload();
            } else {
                alert(result.message || 'Error al actualizar');
            }
        } else {
            var result = apiRequest('POST', '/usuarios', payload);
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
        var sw = e.target.closest('.cm-users-toggle');
        if (!sw) { return; }
        var id = sw.id.replace('userToggle', '');
        var result = apiRequest('PATCH', '/usuarios/' + id + '/toggle');
        var badge = sw.closest('tr').querySelector('.cm-badge');
        if (badge) {
            if (sw.checked) { badge.className = 'cm-badge cm-badge-ok'; badge.textContent = 'Activo'; }
            else { badge.className = 'cm-badge cm-badge-muted'; badge.textContent = 'Inactivo'; }
        }
    });
})();
</script>

<?php
$content = ob_get_clean();
require SRC_PATH . '/layout/app.php';
