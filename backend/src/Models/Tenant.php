<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Modelo de tenant (empresa) — datos globales, NO filtrado por tenant.
 *
 * SUPER_ADMIN y las operaciones a nivel de sistema usan este modelo
 * para acceder y gestionar registros de tenants.
 */
class Tenant extends BaseModel
{
    protected static string $table        = 'empresas';
    protected static bool   $tenantScoped = false; // Datos globales

    /**
     * Buscar un tenant por NIT.
     */
    public static function findByNit(string $nit): ?array
    {
        return parent::findBy('nit', $nit);
    }

    /**
     * Verificar si un NIT ya existe, excluyendo opcionalmente un ID dado.
     */
    public static function nitExists(string $nit, ?int $excludeId = null): bool
    {
        $db     = Database::getInstance();
        $sql    = "SELECT COUNT(*) as total FROM empresas WHERE nit = :nit";
        $params = [':nit' => $nit];

        if ($excludeId !== null) {
            $sql         .= " AND id != :id";
            $params[':id'] = $excludeId;
        }

        return (int) $db->fetchOne($sql, $params)['total'] > 0;
    }

    /**
     * Contar cuántos usuarios pertenecen a un tenant específico.
     */
    public static function countUsers(int $tenantId): int
    {
        $db = Database::getInstance();
        $result = $db->fetchOne(
            "SELECT COUNT(*) as total FROM usuarios WHERE tenant_id = :tid",
            [':tid' => $tenantId]
        );
        return (int) $result['total'];
    }
}
