<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Clase Agent
 *
 * Modelo de agentes (operadores telefónicos) — filtrado por tenant.
 * Representa operadores que atienden llamadas en las colas de atención.
 * Incluye métodos para paginación con búsqueda y actualización de estadísticas.
 *
 * @description Modelo multi-tenant para gestión de agentes. Permite búsquedas
 *              por nombre y actualización de estadísticas después de atender llamadas.
 * @package CallMetrics\Models
 */
class Agent extends BaseModel
{
    /** @var string Nombre de la tabla en la base de datos */
    protected static string $table = 'agentes';

    /** @var bool Filtrado por tenant habilitado */
    protected static bool $tenantScoped = true;

    /**
     * Paginación con búsqueda por nombre de agente.
     *
     * @description Sobrescribe el método paginate() del BaseModel para buscar
     *              específicamente en la columna 'nombre' del agente.
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

    /**
     * Actualiza estadísticas del agente después de atender una llamada.
     *
     * @description Incrementa el contador de llamadas atendidas y el tiempo total
     *              en segundos. Utilizado por el sistema para mantener métricas
     *              actualizadas de cada agente.
     *
     * @param int $agentId ID del agente a actualizar
     * @param int $callDuration Duración de la llamada en segundos
     * @return void
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
