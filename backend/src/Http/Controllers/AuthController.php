<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Config, Database, JwtHelper, Request, Response, TenantContext};
use CallMetrics\Models\{User, Tenant};

/**
 * Controlador de autenticación — login, refresh, logout, me, password, primer-ingreso.
 *
 * Todos los métodos aceptan un objeto Request y responden mediante helpers estáticos de Response.
 * La rotación de tokens siempre revoca el token de refresco anterior antes de guardar el nuevo.
 */
class AuthController extends Controller
{
    // ---------------------------------------------------------------
    // POST /api/auth/login
    // ---------------------------------------------------------------

    /**
     * Autenticar un usuario y retornar el par de tokens de acceso + refresco.
     *
     * Rate limiting: 5 intentos fallidos por email+IP dentro de 15 minutos → 429.
     * En caso de éxito: limpia intentos fallidos, actualiza ultimo_login, almacena hash del refresh.
     */
    public function login(Request $request): void
    {
        $email    = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // --- Validación ---
        if ($email === '' || $password === '') {
            Response::error('Email y password son requeridos', 422);
        }

        // --- Rate limiting ---
        $maxAttempts   = (int) Config::get('LOGIN_MAX_ATTEMPTS', 5);
        $lockoutMinutes = (int) Config::get('LOGIN_LOCKOUT_MINUTES', 15);

        if (User::countLoginAttempts($email, $ip, $lockoutMinutes) >= $maxAttempts) {
            Response::error('Demasiados intentos. Intenta de nuevo mas tarde.', 429);
        }

        // --- Buscar usuario (búsqueda global) ---
        $user = User::findByEmail($email);

        if ($user === null || !User::verifyPassword($password, $user['password_hash'])) {
            User::logLoginAttempt($email, $ip);
            Response::unauthorized('Credenciales invalidas');
        }

        // --- Verificar si está activo ---
        if ((int) $user['activo'] !== 1) {
            Response::forbidden('Usuario desactivado');
        }

        // --- Limpiar intentos antiguos en login exitoso ---
        User::cleanOldAttempts(60);

        // --- Generar tokens ---
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $accessToken  = JwtHelper::generateAccessToken(
            (int) $user['id'],
            $tenantId,
            $user['rol'],
            $user['email']
        );
        $refreshToken = JwtHelper::generateRefreshToken((int) $user['id']);

        // --- Almacenar hash del token de refresco ---
        $db = Database::getInstance();
        $db->insert(
            "INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
             VALUES (:uid, :hash, FROM_UNIXTIME(:exp))",
            [
                ':uid'  => (int) $user['id'],
                ':hash' => JwtHelper::hash($refreshToken),
                ':exp'  => time() + Config::jwtRefreshExpiry(),
            ]
        );

        // --- Actualizar ultimo_login ---
        User::update((int) $user['id'], ['ultimo_login' => date('Y-m-d H:i:s')]);

        // --- Respuesta ---
        Response::ok([
            'accessToken'  => $accessToken,
            'refreshToken' => $refreshToken,
            'user' => [
                'id'       => (int) $user['id'],
                'nombre'   => $user['nombre'],
                'email'    => $user['email'],
                'rol'      => $user['rol'],
                'tenantId' => $tenantId,
                'empresa'  => $this->getEmpresaNombre($tenantId),
            ],
        ], 'Login exitoso');
    }

    // ---------------------------------------------------------------
    // POST /api/auth/refresh
    // ---------------------------------------------------------------

    /**
     * Rotar un token de refresco: revocar el anterior, emitir nuevo par de acceso + refresco.
     *
     * Rotación de un solo uso — el token de refresco anterior no puede reutilizarse.
     */
    public function refresh(Request $request): void
    {
        $refreshTokenStr = (string) $request->input('refreshToken', '');

        if ($refreshTokenStr === '') {
            Response::error('Refresh token requerido', 422);
        }

        // --- Decodificar JWT ---
        try {
            $payload = JwtHelper::decode($refreshTokenStr);
        } catch (\Exception $e) {
            Response::unauthorized('Token invalido o expirado');
        }

        if (($payload->type ?? '') !== 'refresh') {
            Response::unauthorized('Tipo de token invalido');
        }

        $userId    = (int) $payload->sub;
        $tokenHash = JwtHelper::hash($refreshTokenStr);
        $db        = Database::getInstance();

        // --- Verificar que el token exista y no esté revocado ---
        $row = $db->fetchOne(
            "SELECT id, revoked FROM refresh_tokens
             WHERE user_id = :uid AND token_hash = :hash LIMIT 1",
            [':uid' => $userId, ':hash' => $tokenHash]
        );
        if ($row === null || (int) $row['revoked'] === 1) {
            Response::unauthorized('Token revocado o invalido');
        }

        // --- Verificar que el usuario esté activo ---
        $user = User::find($userId);
        if ($user === null || (int) $user['activo'] !== 1) {
            Response::forbidden('Usuario desactivado');
        }

        // --- Revocar token anterior y generar nuevo par (Transacción atómica) ---
        $db->pdo()->beginTransaction();
        try {
            // Revocar token anterior
            $db->execute(
                "UPDATE refresh_tokens SET revoked = 1 WHERE id = :id",
                [':id' => (int) $row['id']]
            );

            // Generar nuevo par
            $tenantId     = (int) ($user['tenant_id'] ?? 0);
            $newAccess    = JwtHelper::generateAccessToken($userId, $tenantId, $user['rol'], $user['email']);
            $newRefresh   = JwtHelper::generateRefreshToken($userId);

            // Almacenar hash del nuevo token de refresco
            $db->insert(
                "INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
                 VALUES (:uid, :hash, FROM_UNIXTIME(:exp))",
                [
                    ':uid'  => $userId,
                    ':hash' => JwtHelper::hash($newRefresh),
                    ':exp'  => time() + Config::jwtRefreshExpiry(),
                ]
            );

            $db->pdo()->commit();

            Response::ok([
                'accessToken'  => $newAccess,
                'refreshToken' => $newRefresh,
            ], 'Token renovado');
        } catch (\Throwable $e) {
            $db->pdo()->rollBack();
            error_log("Error en refresh token: " . $e->getMessage());
            Response::error('Error al renovar token', 500);
        }
    }

    // ---------------------------------------------------------------
    // POST /api/auth/logout
    // ---------------------------------------------------------------

    /**
     * Revocar un token de refresco — el usuario necesitará iniciar sesión nuevamente.
     */
    public function logout(Request $request): void
    {
        $refreshTokenStr = (string) $request->input('refreshToken', '');

        if ($refreshTokenStr === '') {
            Response::error('Refresh token requerido', 422);
        }

        $tokenHash = JwtHelper::hash($refreshTokenStr);
        $userId    = TenantContext::getUserId();

        Database::getInstance()->execute(
            "UPDATE refresh_tokens SET revoked = 1
             WHERE user_id = :uid AND token_hash = :hash AND revoked = 0",
            [':uid' => $userId, ':hash' => $tokenHash]
        );

        Response::ok(null, 'Sesion cerrada');
    }

    // ---------------------------------------------------------------
    // GET /api/auth/me
    // ---------------------------------------------------------------

    /**
     * Retornar los datos del usuario autenticado (sin password_hash)
     * enriquecidos con el nombre de la empresa.
     */
    public function me(Request $request): void
    {
        $userId = TenantContext::getUserId();
        $user   = User::find($userId);

        if ($user === null) {
            Response::notFound('Usuario no encontrado');
        }

        // Eliminar campo sensible
        unset($user['password_hash']);

        // Adjuntar nombre de empresa
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $user['empresa_nombre'] = $this->getEmpresaNombre($tenantId);

        Response::ok($user);
    }

    // ---------------------------------------------------------------
    // PUT /api/auth/password
    // ---------------------------------------------------------------

    /**
     * Cambiar la contraseña del usuario autenticado después de verificar la actual.
     */
    public function changePassword(Request $request): void
    {
        $currentPassword = (string) $request->input('currentPassword', '');
        $newPassword     = (string) $request->input('newPassword', '');

        if ($currentPassword === '' || $newPassword === '') {
            Response::error('Password actual y nuevo password son requeridos', 422);
        }

        if (strlen($newPassword) < 6) {
            Response::error('El nuevo password debe tener al menos 6 caracteres', 422);
        }

        $userId = TenantContext::getUserId();
        $user   = User::find($userId);

        if ($user === null) {
            Response::notFound('Usuario no encontrado');
        }

        if (!User::verifyPassword($currentPassword, $user['password_hash'])) {
            Response::unauthorized('Password actual incorrecto');
        }

        User::updateWithPassword($userId, ['password' => $newPassword]);

        Response::ok(null, 'Password actualizado');
    }

    // ---------------------------------------------------------------
    // PUT /api/auth/primer-ingreso
    // ---------------------------------------------------------------

    /**
     * Completar el flujo de primer ingreso: establecer una nueva contraseña y auto-login.
     *
     * Requiere email + newPassword. Verifica la bandera primer_ingreso,
     * actualiza la contraseña, desactiva la bandera y retorna tokens.
     */
    public function primerIngreso(Request $request): void
    {
        $email       = trim((string) $request->input('email', ''));
        $newPassword = (string) $request->input('newPassword', '');
        $ip          = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($email === '' || $newPassword === '') {
            Response::error('Email y nuevo password son requeridos', 422);
        }

        if (strlen($newPassword) < 6) {
            Response::error('El nuevo password debe tener al menos 6 caracteres', 422);
        }

        // --- Rate limiting (misma lógica que login) ---
        $maxAttempts    = (int) Config::get('LOGIN_MAX_ATTEMPTS', 5);
        $lockoutMinutes = (int) Config::get('LOGIN_LOCKOUT_MINUTES', 15);

        if (User::countLoginAttempts($email, $ip, $lockoutMinutes) >= $maxAttempts) {
            Response::error('Demasiados intentos. Intenta de nuevo mas tarde.', 429);
        }

        $user = User::findByEmail($email);

        if ($user === null) {
            Response::notFound('Usuario no encontrado');
        }

        if ((int) $user['primer_ingreso'] !== 1) {
            Response::error('Este usuario ya completo su primer ingreso', 400);
        }

        // --- Limpiar intentos antiguos en éxito ---
        User::cleanOldAttempts(60);

        // --- Actualizar contraseña y limpiar bandera ---
        User::updateWithPassword((int) $user['id'], [
            'password'        => $newPassword,
            'primer_ingreso'  => 0,
            'ultimo_login'    => date('Y-m-d H:i:s'),
        ]);

        // --- Auto-login: generar tokens ---
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $accessToken  = JwtHelper::generateAccessToken(
            (int) $user['id'],
            $tenantId,
            $user['rol'],
            $user['email']
        );
        $refreshToken = JwtHelper::generateRefreshToken((int) $user['id']);

        // --- Almacenar hash del token de refresco ---
        Database::getInstance()->insert(
            "INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
             VALUES (:uid, :hash, FROM_UNIXTIME(:exp))",
            [
                ':uid'  => (int) $user['id'],
                ':hash' => JwtHelper::hash($refreshToken),
                ':exp'  => time() + Config::jwtRefreshExpiry(),
            ]
        );

        Response::ok([
            'accessToken'  => $accessToken,
            'refreshToken' => $refreshToken,
        ], 'Primer ingreso completado');
    }

    // ---------------------------------------------------------------
    // Métodos auxiliares internos
    // ---------------------------------------------------------------

    /**
     * Obtener el nombre de la empresa para un tenant ID dado.
     * Retorna null cuando tenantId es 0 (ej. SUPER_ADMIN sin tenant).
     */
    private function getEmpresaNombre(int $tenantId): ?string
    {
        if ($tenantId <= 0) {
            return null;
        }

        $tenant = Tenant::find($tenantId);
        return $tenant['nombre'] ?? null;
    }
}
