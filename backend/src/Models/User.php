<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Clase User
 *
 * Modelo de usuario para la tabla `usuarios`. Filtrado por tenant por defecto.
 * Maneja autenticación, hashing de contraseñas, control de intentos de login
 * y verificación de unicidad de email por tenant.
 *
 * @description Modelo con métodos especializados para autenticación y gestión
 *              de usuarios. Soporta búsquedas globales (sin filtro de tenant)
 *              para el flujo de login y búsquedas filtradas por tenant para
 *              administración de usuarios.
 * @package CallMetrics\Models
 */
class User extends BaseModel
{
    /** @var string Nombre de la tabla en la base de datos */
    protected static string $table     = 'usuarios';

    /** @var bool Filtrado por tenant habilitado por defecto */
    protected static bool   $tenantScoped = true;

    // ---------------------------------------------------------------
    // Búsquedas globales (sin filtro de tenant)
    // ---------------------------------------------------------------

    /**
     * Busca un usuario por email en TODOS los tenants.
     *
     * @description Búsqueda global sin filtro de tenant, utilizada durante el
     *              flujo de autenticación (login). Permite encontrar usuarios
     *              sin importar a qué tenant pertenezcan.
     *
     * @param string $email Correo electrónico del usuario a buscar
     * @return ?array Datos del usuario como array asociativo o null si no existe
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
     * Crea un usuario, hasheando el campo 'password' con bcrypt (costo 12).
     *
     * @description Recibe un array con los datos del usuario incluyendo 'password'
     *              en texto plano. Convierte el password a un hash bcrypt y almacena
     *              en 'password_hash'. Se espera que $data contenga al menos:
     *              password, tenant_id, nombre, email, rol.
     *
     * @param array $data Datos del usuario a crear (incluye 'password' en texto plano)
     * @return int ID del usuario creado
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
     * Actualiza un usuario; hashea 'password' si se proporciona.
     *
     * @description Similar a createWithPassword pero para actualizaciones.
     *              Si se proporciona 'password', lo hashea antes de actualizar.
     *
     * @param int $id ID del usuario a actualizar
     * @param array $data Datos a actualizar (incluye 'password' opcional en texto plano)
     * @return int Número de filas afectadas (0 o 1)
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
     * Verifica una contraseña en texto plano contra un hash bcrypt.
     *
     * @description Utiliza password_verify() de PHP para comparar la contraseña
     *              proporcionada con el hash almacenado en la base de datos.
     *
     * @param string $password Contraseña en texto plano a verificar
     * @param string $hash Hash bcrypt almacenado en la base de datos
     * @return bool true si la contraseña coincide, false de lo contrario
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // ---------------------------------------------------------------
    // Búsquedas filtradas por tenant
    // ---------------------------------------------------------------

    /**
     * Verifica si un email existe dentro de un tenant específico.
     *
     * @description Utilizado durante la creación/actualización de usuarios para
     *              garantizar la unicidad de email por tenant. Opcionalmente
     *              excluye un ID específico (útil al actualizar).
     *
     * @param string $email Correo electrónico a verificar
     * @param int $tenantId ID del tenant donde buscar
     * @param int|null $excludeId ID a excluir de la búsqueda (opcional)
     * @return bool true si el email ya existe en el tenant, false de lo contrario
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
     * Cuenta intentos de login para el email + IP dados dentro de la ventana de tiempo.
     *
     * @description Consulta la tabla login_attempts para contar cuántos intentos
     *              fallidos ha habido desde una IP específica para un email dado,
     *              dentro de la ventana de tiempo especificada.
     *
     * @param string $email Correo electrónico del usuario
     * @param string $ip Dirección IP del intento
     * @param int $windowMinutes Ventana de tiempo en minutos (por defecto 15)
     * @return int Número de intentos en la ventana de tiempo
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
     * Registra un intento de login en la base de datos.
     *
     * @description Inserta un registro en la tabla login_attempts con el email,
     *              dirección IP y timestamp actual. Utilizado para rate limiting.
     *
     * @param string $email Correo electrónico del usuario
     * @param string $ip Dirección IP del intento
     * @return void
     */
    public static function logLoginAttempt(string $email, string $ip): void
    {
        Database::getInstance()->insert(
            "INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip)",
            [':email' => $email, ':ip' => $ip]
        );
    }

    /**
     * Elimina intentos de login más antiguos que el umbral dado.
     *
     * @description Limpia registros antiguos de la tabla login_attempts para
     *              mantener la tabla optimizada. Útil para tareas de mantenimiento.
     *
     * @param int $olderThanMinutes Eliminar registros más antiguos que estos minutos (por defecto 60)
     * @return void
     */
    public static function cleanOldAttempts(int $olderThanMinutes = 60): void
    {
        Database::getInstance()->execute(
            "DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL :min MINUTE)",
            [':min' => $olderThanMinutes]
        );
    }
}
