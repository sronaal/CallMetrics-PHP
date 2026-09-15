<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/ApiClient.php';

Session::start();

// Logout: destruir sesion antes de cualquier check
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    Session::logout();
    header('Location: ' . BASE_URL . 'auth.php');
    exit;
}

// Si ya esta autenticado, redirigir
if (Session::isAuthenticated()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

// Generar CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF token
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad invalido';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Ingresa tu correo y contraseña';
        } else {
            $client = ApiClient::getInstance();
            $response = $client->post('/auth/login', [
                'email' => $email,
                'password' => $password
            ]);

            if ($response['success'] && isset($response['data'])) {
                Session::login(
                    $response['data']['accessToken'],
                    $response['data']['refreshToken'],
                    $response['data']['user']
                );
                header('Location: ' . BASE_URL . 'dashboard.php');
                exit;
            } else {
                $error = $response['message'] ?? 'Credenciales incorrectas';
            }
        }
    }
}

$title = 'Iniciar Sesión';
ob_start();
?>

<div class="auth-brand">
    <span class="auth-brand-box"><i class="bi bi-telephone-fill"></i></span>
    <span>
        <span class="auth-brand-name">CallMetric</span>
        <span class="auth-brand-pro">PRO</span>
    </span>
</div>

<h1 class="font-display text-center" style="font-weight:700;font-size:22px;color:#e2e8f0;margin-bottom:6px">Iniciar Sesión</h1>
<p class="text-center" style="font-size:13px;color:#7a8ba6;margin-bottom:28px">Accede a tu panel de observabilidad PBX</p>

<?php if ($error): ?>
    <div class="alert alert-danger" style="font-size:13px;padding:10px;margin-bottom:16px">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form action="" method="post">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="mb-3">
        <label for="email" class="form-label">Correo electrónico</label>
        <input type="email" class="form-control" id="email" name="email" placeholder="nombre@empresa.com" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="mb-4">
        <label for="password" class="form-label">Contraseña</label>
        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-gradient w-100" style="padding:12px;font-size:15px;font-weight:700">Entrar al panel</button>
</form>

<div class="text-center mt-4">
    <a href="<?= BASE_URL ?>index.php" class="text-decoration-none" style="font-size:13px;color:#94a3b8">← Volver al inicio</a>
</div>

<?php
$content = ob_get_clean();
require SRC_PATH . '/layout/auth.php';
?>
