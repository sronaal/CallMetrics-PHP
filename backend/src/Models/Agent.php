<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Modelo de agentes (operadores) — filtrado por tenant.
 * Representa operadores telefónicos que atienden llamadas.
 */
class Agent extends BaseModel
{
    protected static string $table = 'agentes';
    protected static bool $tenantScoped = true;

    /**
     * Paginación con búsqueda por nombre de agente.
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

    /**
     * Actualizar estadísticas del agente después de atender una llamada.
     *
     * Incrementa el contador de llamadas atendidas y el tiempo total en segundos.
     */
    public static function updateStats(int $agentId, int $callDuration): void
    {
        $db = Database::getInstance();
        $db->execute(
            "UPDATE agentes SET llamadas_atendidas = llamadas_atendidas + 1,
             tiempo_total_llamadas = tiempo_total_llamadas + :duration
             WHERE id = :id",
            [':id' => $agentId, ':duration' => $callDuration]
        );
    }
}
