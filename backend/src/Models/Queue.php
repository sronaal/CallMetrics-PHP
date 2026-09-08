<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Modelo de colas de atención — filtrado por tenant.
 * Representa queues de llamadas con estrategia de distribución.
 */
class Queue extends BaseModel
{
    protected static string $table = 'colas';
    protected static bool $tenantScoped = true;

    /**
     * Contar agentes asignados a una cola específica.
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
     * @return array{data: array, total: int}
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
