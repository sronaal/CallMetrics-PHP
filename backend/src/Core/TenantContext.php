<?php
declare(strict_types=1);

namespace CallMetrics\Core;

/**
 * Clase TenantContext
 *
 * Almacena el contexto del tenant del usuario autenticado para la solicitud actual.
 * Implementa el patrón de almacenamiento estático para mantener el estado del tenant,
 * usuario y rol durante el ciclo de vida de una solicitud HTTP. Establecido por
 * AuthMiddleware después de la validación JWT.
 *
 * @description Proporciona métodos para obtener y manipular el contexto del tenant
 *              actual, incluyendo resolución de tenant para operaciones de lectura
 *              y escritura. Soporta multi-tenancy con SUPER_ADMIN como rol especial.
 * @package CallMetrics\Core
 */
class TenantContext
{
    /** @var ?int ID del tenant del usuario autenticado */
    private static ?int $tenantId = null;

    /** @var ?int ID del usuario autenticado */
    private static ?int $userId = null;

    /** @var ?string Rol del usuario autenticado */
    private static ?string $role = null;

    /**
     * Establece el contexto del tenant para la solicitud actual.
     *
     * @description Asigna el tenant_id, user_id y role del usuario autenticado.
     *              Este método es llamado por AuthMiddleware después de validar
     *              el token JWT.
     *
     * @param int $tenantId ID del tenant al que pertenece el usuario
     * @param int $userId ID del usuario autenticado
     * @param string $role Rol del usuario (SUPER_ADMIN, ADMIN_TENANT, USER)
     * @return void
     */
    public static function set(int $tenantId, int $userId, string $role): void
    {
        self::$tenantId = $tenantId;
        self::$userId = $userId;
        self::$role = $role;
    }

    /**
     * Obtiene el tenant_id del contexto actual.
     *
     * @description Retorna el ID del tenant del usuario autenticado actualmente.
     *
     * @return ?int ID del tenant o null si no hay contexto establecido
     */
    public static function get(): ?int
    {
        return self::$tenantId;
    }

    /**
     * Obtiene el user_id del contexto actual.
     *
     * @description Retorna el ID del usuario autenticado actualmente.
     *
     * @return ?int ID del usuario o null si no hay contexto establecido
     */
    public static function getUserId(): ?int
    {
        return self::$userId;
    }

    /**
     * Obtiene el rol del usuario del contexto actual.
     *
     * @description Retorna el rol del usuario autenticado actualmente.
     *
     * @return ?string Rol del usuario o null si no hay contexto establecido
     */
    public static function getRole(): ?string
    {
        return self::$role;
    }

    /**
     * Verifica si el usuario actual es SUPER_ADMIN.
     *
     * @description Compara el rol actual con 'SUPER_ADMIN' para determinar
     *              si el usuario tiene privilegios de super administrador.
     *
     * @return bool true si el usuario es SUPER_ADMIN, false de lo contrario
     */
    public static function isSuperAdmin(): bool
    {
        return self::$role === 'SUPER_ADMIN';
    }

    /**
     * Verifica si el usuario actual tiene rol de administrador.
     *
     * @description Retorna true para los roles SUPER_ADMIN y ADMIN_TENANT.
     *              Útil para verificar permisos de administración en general.
     *
     * @return bool true si el usuario es SUPER_ADMIN o ADMIN_TENANT
     */
    public static function isAdmin(): bool
    {
        return in_array(self::$role, ['SUPER_ADMIN', 'ADMIN_TENANT'], true);
    }

    /**
     * Limpia el contexto del tenant actual.
     *
     * @description Restablece todos los valores del contexto a null. Utilizado
     *              al finalizar la solicitud o en pruebas.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$tenantId = null;
        self::$userId = null;
        self::$role = null;
    }

    /**
     * Resuelve el tenant_id efectivo para la solicitud actual.
     *
     * @description Implementa la lógica de multi-tenancy para operaciones de lectura:
     *              - Para usuarios normales: retorna el tenant_id del JWT
     *              - Para SUPER_ADMIN (tid=0): permite especificar tenant_id en
     *                body o query param. Si no se especifica, retorna null
     *                (queries globales permitidas)
     *
     * @param Request $request Solicitud actual con datos del body y query
     * @return int|null tenant_id resuelto, o null si no se pudo resolver
     */
    public static function resolveTenantId(Request $request): ?int
    {
        $jwtTid = self::$tenantId;

        // Para usuarios normales, siempre usar el JWT
        if ($jwtTid !== null && $jwtTid > 0) {
            return $jwtTid;
        }

        // Para SUPER_ADMIN (tid=0): buscar en body o query params
        if (self::isSuperAdmin()) {
            // Buscar en body JSON primero
            $body = $request->body();
            $bodyTenant = $body['tenant_id'] ?? null;
            if ($bodyTenant !== null && $bodyTenant !== '') {
                return (int) $bodyTenant;
            }

            // Buscar en query params
            $query = $request->query();
            $queryTenant = $query['tenant_id'] ?? null;
            if ($queryTenant !== null && $queryTenant !== '') {
                return (int) $queryTenant;
            }

            // SUPER_ADMIN sin tenant_id específico → null (requiere explícito para writes)
            return null;
        }

        // Otros roles sin tid válido
        return null;
    }

    /**
     * Resuelve tenant para operaciones de escritura (create).
     *
     * @description Similar a resolveTenantId pero más estricto para escrituras.
     *              Para SUPER_ADMIN: requiere tenant_id explícito y mayor a 0.
     *              Para otros roles: retorna el tenant_id del JWT.
     *
     * @param Request $request Solicitud actual con datos del body y query
     * @return int|null tenant_id resuelto, o null si no se pudo resolver
     */
    public static function resolveTenantIdForWrite(Request $request): ?int
    {
        $jwtTid = self::$tenantId;

        if ($jwtTid !== null && $jwtTid > 0) {
            return $jwtTid;
        }

        if (self::isSuperAdmin()) {
            $body = $request->body();
            $bodyTenant = $body['tenant_id'] ?? null;
            if ($bodyTenant !== null && $bodyTenant !== '' && (int) $bodyTenant > 0) {
                return (int) $bodyTenant;
            }

            $query = $request->query();
            $queryTenant = $query['tenant_id'] ?? null;
            if ($queryTenant !== null && $queryTenant !== '' && (int) $queryTenant > 0) {
                return (int) $queryTenant;
            }

            // SUPER_ADMIN sin tenant_id explícito → null (no puede crear sin saber en qué tenant)
            return null;
        }

        return null;
    }
}
