<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Modelo de reglas de alerta — filtrado por tenant.
 * Configuración de umbrales y condiciones para notificaciones.
 */
class AlertRule extends BaseModel
{
    protected static string $table = 'reglas_alerta';
    protected static bool $tenantScoped = true;

    /**
     * Obtener todas las reglas activas de un tenant específico.
     */
    public static function findActive(int $tenantId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM reglas_alerta WHERE tenant_id = :tenant_id AND activo = 1",
            [':tenant_id' => $tenantId]
        );
    }

    /**
     * Paginación con búsqueda por nombre de regla.
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
