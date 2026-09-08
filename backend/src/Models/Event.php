<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Modelo de eventos del sistema — filtrado por tenant.
 * Almacena eventos AMI, CEL, monitoreo de salud y del sistema.
 */
class Event extends BaseModel
{
    protected static string $table = 'eventos';
    protected static bool $tenantScoped = true;

    /**
     * Paginación filtrada por tipo, nombre de evento, PBX y rango de fechas.
     *
     * @param string|null $tipo        Tipo de evento (AMI, CEL, HEALTH, SYSTEM)
     * @param string|null $evento      Nombre del evento (búsqueda parcial)
     * @param int|null    $pbxId       ID del servidor PBX
     * @param string|null $fechaInicio Fecha inicio en formato Y-m-d H:i:s
     * @param string|null $fechaFin    Fecha fin en formato Y-m-d H:i:s
     * @return array{data: array, total: int}
     */
    public static function paginateFiltered(
        int $page = 0,
        int $size = 10,
        ?string $tipo = null,
        ?string $evento = null,
        ?int $pbxId = null,
        ?string $fechaInicio = null,
        ?string $fechaFin = null
    ): array {
        $db = Database::getInstance();
        $params = [];
        $wheres = [];

        static::applyTenantFilter($wheres, $params);

        if ($tipo) {
            $wheres[] = 'tipo = :tipo';
            $params[':tipo'] = $tipo;
        }
        if ($evento) {
            $wheres[] = 'evento LIKE :evento';
            $params[':evento'] = "%$evento%";
        }
        if ($pbxId) {
            $wheres[] = 'pbx_id = :pbx_id';
            $params[':pbx_id'] = $pbxId;
        }
        if ($fechaInicio) {
            $wheres[] = 'created_at >= :fecha_inicio';
            $params[':fecha_inicio'] = $fechaInicio;
        }
        if ($fechaFin) {
            $wheres[] = 'created_at <= :fecha_fin';
            $params[':fecha_fin'] = $fechaFin;
        }

        $whereClause = !empty($wheres) ? ' WHERE ' . implode(' AND ', $wheres) : '';

        $countSql = "SELECT COUNT(*) as total FROM eventos" . $whereClause;
        $total = (int) $db->fetchOne($countSql, $params)['total'];

        $offset = $page * $size;
        $dataSql = "SELECT * FROM eventos" . $whereClause . " ORDER BY created_at DESC LIMIT $size OFFSET $offset";
        $data = $db->fetchAll($dataSql, $params);

        return ['data' => $data, 'total' => $total];
    }
}
