<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Clase Event
 *
 * Modelo de eventos del sistema — filtrado por tenant. Almacena eventos AMI
 * (Asterisk Manager Interface), CEL (Channel Event Logging), monitoreo de
 * salud y eventos del sistema.
 *
 * @description Modelo multi-tenant para gestión de eventos. Soporta filtros por
 *              tipo de evento, nombre del evento, servidor PBX y rango de fechas.
 * @package CallMetrics\Models
 */
class Event extends BaseModel
{
    /** @var string Nombre de la tabla en la base de datos */
    protected static string $table = 'eventos';

    /** @var bool Filtrado por tenant habilitado */
    protected static bool $tenantScoped = true;

    /**
     * Paginación filtrada por tipo, nombre de evento, PBX y rango de fechas.
     *
     * @description Ejecuta una consulta paginada con múltiples filtros opcionales:
     *              tipo de evento (AMI, CEL, HEALTH, SYSTEM), nombre del evento
     *              (búsqueda parcial), servidor PBX y rango de fechas.
     *
     * @param int $page Número de página (0-indexed, por defecto 0)
     * @param int $size Tamaño de página (por defecto 10)
     * @param string|null $tipo Tipo de evento (AMI, CEL, HEALTH, SYSTEM)
     * @param string|null $evento Nombre del evento (búsqueda parcial con LIKE)
     * @param int|null $pbxId ID del servidor PBX
     * @param string|null $fechaInicio Fecha inicio en formato Y-m-d H:i:s
     * @param string|null $fechaFin Fecha fin en formato Y-m-d H:i:s
     * @return array{data: array, total: int} Datos de la página y total de registros
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
