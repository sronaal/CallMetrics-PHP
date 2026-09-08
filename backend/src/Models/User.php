<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Modelo de usuario para la tabla `usuarios`.
 *
 * Filtrado por tenant por defecto. Métodos clave:
 *   - findByEmail        — búsqueda global (sin filtro de tenant)
 *   - createWithPassword — hashing bcrypt (costo 12)
 *   - verifyPassword     — envoltorio de password_verify
 *   - seguimiento de intentos de login (conteo / registro / limpieza)
 */
class User extends BaseModel
{
    protected static string $table     = 'usuarios';
    protected static bool   $tenantScoped = true;

    // ---------------------------------------------------------------
    // Búsquedas globales (sin filtro de tenant)
    // ---------------------------------------------------------------

    /**
     * Buscar un usuario por email en TODOS los tenants.
     *
     * Se usa durante la autenticación (flujo de login).
     */
    public static function findByEmail(string $email): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM usuarios WHERE email = :email LIMIT 1",
            [':email' => $email]
        );
    }

    // ---------------------------------------------------------------
    // Crear / actualizar con manejo de contraseña
    // ---------------------------------------------------------------

    /**
     * Crear un usuario, hasheando el campo 'password' con bcrypt (costo 12).
     *
     * Se espera que $data contenga al menos:
     *   - password (texto plano, será hasheado)
     *   - tenant_id, nombre, email, rol
     */
    public static function createWithPassword(array $data): int
    {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash(
                $data['password'],
                PASSWORD_BCRYPT,
                ['cost' => 12]
            );
            unset($data['password']);
        }

        return parent::create($data);
    }

    /**
     * Actualizar un usuario; hashea 'password' si se proporciona.
     */
    public static function updateWithPassword(int $id, array $data): int
    {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash(
                $data['password'],
                PASSWORD_BCRYPT,
                ['cost' => 12]
            );
            unset($data['password']);
        }

        return parent::update($id, $data);
    }

    // ---------------------------------------------------------------
    // Verificación de contraseña
    // ---------------------------------------------------------------

    /**
     * Verificar una contraseña en texto plano contra un hash bcrypt.
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // ---------------------------------------------------------------
    // Búsquedas filtradas por tenant
    // ---------------------------------------------------------------

    /**
     * Verificar si un email existe dentro de un tenant específico.
     *
     * Se usa durante la creación/actualización de usuarios para garantizar
     * la unicidad por tenant.
     */
    public static function emailExistsInTenant(string $email, int $tenantId, ?int $excludeId = null): bool
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as total FROM usuarios WHERE email = :email AND tenant_id = :tenant_id";
        $params = [':email' => $email, ':tenant_id' => $tenantId];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $result = $db->fetchOne($sql, $params);
        return (int) $result['total'] > 0;
    }

    // ---------------------------------------------------------------
    // Control de rate limiting de intentos de login
    // ---------------------------------------------------------------

    /**
     * Contar intentos de login para el email + IP dados dentro de la ventana de tiempo.
     */
    public static function countLoginAttempts(string $email, string $ip, int $windowMinutes = 15): int
    {
        $db     = Database::getInstance();
        $result = $db->fetchOne(
            "SELECT COUNT(*) as total FROM login_attempts
             WHERE email = :email AND ip_address = :ip
               AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)",
            [':email' => $email, ':ip' => $ip, ':minutes' => $windowMinutes]
        );

        return (int) $result['total'];
    }

    /**
     * Registrar un intento de login.
     */
    public static function logLoginAttempt(string $email, string $ip): void
    {
        Database::getInstance()->insert(
            "INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip)",
            [':email' => $email, ':ip' => $ip]
        );
    }

    /**
     * Eliminar intentos de login más antiguos que el umbral dado.
     */
    public static function cleanOldAttempts(int $olderThanMinutes = 60): void
    {
        Database::getInstance()->execute(
            "DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL :min MINUTE)",
            [':min' => $olderThanMinutes]
        );
    }
}
