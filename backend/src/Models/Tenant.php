<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Clase Tenant
 *
 * Modelo de tenant (empresa) — datos globales, NO filtrado por tenant.
 * Utilizado por SUPER_ADMIN y operaciones a nivel de sistema para acceder
 * y gestionar registros de tenants. La tabla es 'empresas'.
 *
 * @description Modelo con filtrado deshabilitado ($tenantScope = false) ya que
 *              representa datos globales del sistema que SUPER_ADMIN necesita
 *              acceder sin restricciones de tenant.
 * @package CallMetrics\Models
 */
class Tenant extends BaseModel
{
    /** @var string Nombre de la tabla en la base de datos */
    protected static string $table        = 'empresas';

    /** @var bool Filtrado por tenant deshabilitado — datos globales */
    protected static bool   $tenantScoped = false; // Datos globales

    /**
     * Busca un tenant por NIT (Número de Identificación Tributaria).
     *
     * @description Utiliza el método findBy() del BaseModel para buscar un tenant
     *              por su número de identificación tributaria único.
     *
     * @param string $nit Número de Identificación Tributaria a buscar
     * @return ?array Datos del tenant como array asociativo o null si no existe
     */
    public static function findByNit(string $nit): ?array
    {
        return parent::findBy('nit', $nit);
    }

    /**
     * Verifica si un NIT ya existe, excluyendo opcionalmente un ID dado.
     *
     * @description Consulta la tabla para verificar la unicidad del NIT. Opcionalmente
     *              excluye un ID específico, útil durante actualizaciones.
     *
     * @param string $nit NIT a verificar
     * @param int|null $excludeId ID a excluir de la verificación (opcional)
     * @return bool true si el NIT ya existe, false de lo contrario
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
     * Cuenta cuántos usuarios pertenecen a un tenant específico.
     *
     * @description Ejecuta un COUNT sobre la tabla usuarios filtrando por tenant_id.
     *              Útil para mostrar estadísticas del tenant en el dashboard.
     *
     * @param int $tenantId ID del tenant cuyos usuarios se contarán
     * @return int Número total de usuarios en el tenant
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
