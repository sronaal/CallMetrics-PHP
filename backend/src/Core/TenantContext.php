<?php
declare(strict_types=1);

namespace CallMetrics\Core;

/**
 * Almacena el contexto del tenant del usuario autenticado para la solicitud actual.
 * Establecido por AuthMiddleware después de la validación JWT.
 */
class TenantContext
{
    private static ?int $tenantId = null;
    private static ?int $userId = null;
    private static ?string $role = null;

    public static function set(int $tenantId, int $userId, string $role): void
    {
        self::$tenantId = $tenantId;
        self::$userId = $userId;
        self::$role = $role;
    }

    public static function get(): ?int
    {
        return self::$tenantId;
    }

    public static function getUserId(): ?int
    {
        return self::$userId;
    }

    public static function getRole(): ?string
    {
        return self::$role;
    }

    public static function isSuperAdmin(): bool
    {
        return self::$role === 'SUPER_ADMIN';
    }

    /**
     * Retorna true para los roles SUPER_ADMIN y ADMIN_TENANT.
     */
    public static function isAdmin(): bool
    {
        return in_array(self::$role, ['SUPER_ADMIN', 'ADMIN_TENANT'], true);
    }

    public static function clear(): void
    {
        self::$tenantId = null;
        self::$userId = null;
        self::$role = null;
    }

    /**
     * Resuelve el tenant_id efectivo para la solicitud actual.
     *
     * - Si el usuario NO es SUPER_ADMIN: retorna el tenant_id del JWT.
     * - Si el usuario ES SUPER_ADMIN (tid=0 en JWT): permite especificar
     *   tenant_id en el body o query param. Si no se especifica, retorna 0
     *   (queries globales).
     *
     * @param Request $request Solicitud actual
     * @return int|null tenant_id, o null si no se pudo resolver
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
     * Para SUPER_ADMIN: requiere tenant_id en body/query.
     * Para otros roles: retorna JWT tenant_id.
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
