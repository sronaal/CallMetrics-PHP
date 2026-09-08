<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Modelo de registros de llamadas CDR — filtrado por tenant.
 * Almacena Call Detail Records procesados desde Asterisk.
 */
class CallRecord extends BaseModel
{
    protected static string $table = 'llamadas_cdr';
    protected static bool $tenantScoped = true;

    /**
     * Paginación filtrada por fecha, estado y servidor PBX.
     *
     * @param string|null $fechaInicio Fecha inicio en formato Y-m-d H:i:s
     * @param string|null $fechaFin    Fecha fin en formato Y-m-d H:i:s
     * @param string|null $estado      Estado de la llamada (ANSWERED, NOANSWER, etc.)
     * @param int|null    $pbxId       ID del servidor PBX
     * @return array{data: array, total: int}
     */
    public static function paginateFiltered(
        int $page = 0,
        int $size = 10,
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        ?string $estado = null,
        ?int $pbxId = null
    ): array {
        $db = Database::getInstance();
        $params = [];
        $wheres = [];

        static::applyTenantFilter($wheres, $params);

        if ($fechaInicio) {
            $wheres[] = 'inicio_llamada >= :fecha_inicio';
            $params[':fecha_inicio'] = $fechaInicio;
        }
        if ($fechaFin) {
            $wheres[] = 'inicio_llamada <= :fecha_fin';
            $params[':fecha_fin'] = $fechaFin;
        }
        if ($estado) {
            $wheres[] = 'estado = :estado';
            $params[':estado'] = $estado;
        }
        if ($pbxId) {
            $wheres[] = 'pbx_id = :pbx_id';
            $params[':pbx_id'] = $pbxId;
        }

        $whereClause = !empty($wheres) ? ' WHERE ' . implode(' AND ', $wheres) : '';

        $countSql = "SELECT COUNT(*) as total FROM llamadas_cdr" . $whereClause;
        $total = (int) $db->fetchOne($countSql, $params)['total'];

        $offset = $page * $size;
        $dataSql = "SELECT * FROM llamadas_cdr" . $whereClause . " ORDER BY inicio_llamada DESC LIMIT $size OFFSET $offset";
        $data = $db->fetchAll($dataSql, $params);

        return ['data' => $data, 'total' => $total];
    }

    /**
     * Obtener estadísticas del día actual para un tenant.
     *
     * Retorna: total, contestadas, perdidas, duracion_promedio
     */
    public static function getStatsToday(int $tenantId): array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN estado = 'ANSWERED' THEN 1 ELSE 0 END) as contestadas,
                    SUM(CASE WHEN estado != 'ANSWERED' THEN 1 ELSE 0 END) as perdidas,
                    AVG(CASE WHEN estado = 'ANSWERED' THEN duracion ELSE NULL END) as duracion_promedio
             FROM llamadas_cdr
             WHERE tenant_id = :tenant_id AND DATE(inicio_llamada) = CURDATE()",
            [':tenant_id' => $tenantId]
        );
    }

    /**
     * Obtener estadísticas globales del día actual (sin filtro de tenant).
     * Para uso de SUPER_ADMIN.
     */
    public static function getStatsGlobal(): array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN estado = 'ANSWERED' THEN 1 ELSE 0 END) as contestadas,
                    SUM(CASE WHEN estado != 'ANSWERED' THEN 1 ELSE 0 END) as perdidas,
                    AVG(CASE WHEN estado = 'ANSWERED' THEN duracion ELSE NULL END) as duracion_promedio
             FROM llamadas_cdr
             WHERE DATE(inicio_llamada) = CURDATE()"
        );
    }
}
