<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Clase Queue
 *
 * Modelo de colas de atención — filtrado por tenant. Representa queues de
 * llamadas con estrategia de distribución (round-robin, least-recent, etc.).
 * Incluye métodos para contar agentes asignados y paginación con búsqueda.
 *
 * @description Modelo multi-tenant para gestión de colas de atención. Permite
 *              contar agentes por cola y búsquedas por nombre.
 * @package CallMetrics\Models
 */
class Queue extends BaseModel
{
    /** @var string Nombre de la tabla en la base de datos */
    protected static string $table = 'colas';

    /** @var bool Filtrado por tenant habilitado */
    protected static bool $tenantScoped = true;

    /**
     * Cuenta agentes asignados a una cola específica.
     *
     * @description Ejecuta un COUNT sobre la tabla agentes filtrando por cola_id.
     *              Útil para mostrar información de la cola en la interfaz.
     *
     * @param int $queueId ID de la cola
     * @return int Número total de agentes en la cola
     */
    public static function countAgents(int $queueId): int
    {
        $db = Database::getInstance();
        $result = $db->fetchOne(
            "SELECT COUNT(*) as total FROM agentes WHERE cola_id = :cola_id",
            [':cola_id' => $queueId]
        );
        return (int) $result['total'];
    }

    /**
     * Paginación con búsqueda por nombre de cola.
     *
     * @description Sobrescribe el método paginate() del BaseModel para buscar
     *              específicamente en la columna 'nombre' de la cola.
     *
     * @param int $page Número de página (0-indexed, por defecto 0)
     * @param int $size Tamaño de página (por defecto 10)
     * @param array $conditions Condiciones WHERE adicionales
     * @param string $search Texto de búsqueda para filtrar por nombre
     * @param array $searchColumns Columnas de búsqueda (ignorado, usa ['nombre'])
     * @return array{data: array, total: int} Datos de la página y total de registros
     */
    public static function paginate(
        int $page = 0,
        int $size = 10,
        array $conditions = [],
        string $search = '',
        array $searchColumns = []
    ): array {
        return parent::paginate($page, $size, $conditions, $search, ['nombre']);
    }
}
