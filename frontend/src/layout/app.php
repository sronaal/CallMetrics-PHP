<?php
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/ApiClient.php';
require_once __DIR__ . '/../core/AuthMiddleware.php';

// AuthMiddleware::check() ya fue llamado por la pagina (dashboard.php, pbx.php, etc.)
// No llamarlo de nuevo aqui porque refreshIfNeeded() usa refresh tokens de uso unico —
// llamarlo dos veces causa que el segundo intente usar un token ya rotado/revocado.
if (!Session::isAuthenticated()) {
    header('Location: ' . BASE_URL . 'auth.php');
    exit;
}
Session::touch();

require SRC_PATH . '/layout/head.php';

require_once SRC_PATH . '/data/mock.php';
require_once SRC_PATH . '/components/components.php';

$rol = cm_rol();
$esAdmin = cm_es_admin();
$activeNav = $activeNav ?? 'dashboard';
$ccLayout = $ccLayout ?? !$esAdmin;
$usuario = cm_usuario_actual();
$iniciales = cm_initials($usuario['nombre']);

$sidebarNavLabel = $ccLayout ? 'Call Center' : 'Navegación';
$sidebarItems = $ccLayout ? [
    ['key' => 'cc-dashboard', 'label' => 'Dashboard CC', 'href' => 'callcenter/dashboard.php', 'icon' => 'bi-speedometer2'],
    ['key' => 'colas',        'label' => 'Colas',        'href' => 'callcenter/colas.php',     'icon' => 'bi-list-ul'],
    ['key' => 'cc-agentes',   'label' => 'Agentes CC',   'href' => 'callcenter/agentes.php',   'icon' => 'bi-people'],
    ['key' => 'cdr',          'label' => 'Llamadas (CDR)', 'href' => 'callcenter/cdr.php',     'icon' => 'bi-journal-text'],
] : [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php',       'icon' => 'bi-speedometer2'],
    ['key' => 'pbx',       'label' => 'PBX',       'href' => 'pbx.php',             'icon' => 'bi-server'],
    ['key' => 'agents',    'label' => 'Agentes',   'href' => 'agents.php',          'icon' => 'bi-people'],
    ['key' => 'events',    'label' => 'Eventos',   'href' => 'asterisk-events.php', 'icon' => 'bi-activity'],
    ['key' => 'users',     'label' => 'Usuarios',  'href' => 'users.php',           'icon' => 'bi-person-badge'],
    ['key' => 'empresas',  'label' => 'Empresas',  'href' => 'empresas.php',        'icon' => 'bi-buildings'],
];
?>

<div class="dashboard-shell">

    <!-- Sidebar -->
    <aside class="dashboard-sidebar" id="appSidebar">
        <a class="sidebar-brand" href="<?= BASE_URL ?>dashboard.php">
            <span class="sidebar-brand-box"><i class="bi bi-telephone-fill"></i></span>
            <span>
                <span class="sidebar-brand-name">CallMetric</span>
                <span class="sidebar-brand-pro">PRO</span>
            </span>
        </a>

        <nav class="sidebar-nav">
            <div class="sidebar-nav-label"><?= htmlspecialchars($sidebarNavLabel) ?></div>

            <?php foreach ($sidebarItems as $item): ?>
                <a class="sidebar-item <?= $activeNav === $item['key'] ? 'active' : '' ?>"
                   href="<?= BASE_URL . htmlspecialchars($item['href']) ?>">
                    <i class="bi <?= htmlspecialchars($item['icon']) ?> sidebar-item-icon"></i>
                    <span class="sidebar-item-text"><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <button class="sidebar-collapse-btn" id="sidebarToggle" type="button" aria-label="Colapsar menú lateral">
            <i class="bi bi-chevron-left" id="sidebarChevron"></i>
        </button>

        <div class="sidebar-user">
            <span class="sidebar-user-avatar"><?= htmlspecialchars($iniciales) ?></span>
            <span>
                <span class="sidebar-user-name d-block"><?= htmlspecialchars($usuario['nombre']) ?></span>
                <span class="sidebar-user-role d-block"><?= htmlspecialchars(cm_t($usuario['rol'])) ?></span>
            </span>
        </div>
    </aside>

    <!-- Body -->
    <div class="dashboard-body">
        <header class="dashboard-topbar">
            <h1 class="topbar-title"><?= htmlspecialchars($title ?? APP_NAME) ?></h1>

            <div class="topbar-pbx">
                <span class="topbar-pbx-dot"></span>
                Conectado
            </div>

            <a class="topbar-profile" href="<?= BASE_URL ?>auth.php" title="Cerrar sesión" style="text-decoration:none">
                <span class="topbar-profile-avatar"><?= htmlspecialchars($iniciales) ?></span>
                <span class="topbar-profile-name"><?= htmlspecialchars($usuario['nombre']) ?></span>
                <i class="bi bi-box-arrow-right" style="font-size:13px;color:#7a8ba6"></i>
            </a>
        </header>

        <main class="dashboard-main">
<?= $content ?>
        </main>
    </div>
</div>

<?php
require SRC_PATH . '/layout/footer.php';
?>
