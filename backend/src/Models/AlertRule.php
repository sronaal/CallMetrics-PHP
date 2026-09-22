<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Clase AlertRule
 *
 * Modelo de reglas de alerta — filtrado por tenant. Almacena configuración de
 * umbrales y condiciones para notificaciones automáticas. Permite definir
 * reglas como "alertar cuando las llamadas perdidas superen N en M minutos".
 *
 * @description Modelo multi-tenant para gestión de reglas de alerta. Soporta
 *              búsqueda de reglas activas y paginación con búsqueda por nombre.
 * @package CallMetrics\Models
 */
class AlertRule extends BaseModel
{
    /** @var string Nombre de la tabla en la base de datos */
    protected static string $table = 'reglas_alerta';

    /** @var bool Filtrado por tenant habilitado */
    protected static bool $tenantScoped = true;

    /**
     * Obtiene todas las reglas activas de un tenant específico.
     *
     * @description Consulta la tabla reglas_alerta filtrando por tenant_id y
     *              estado activo (activo = 1). Utilizado por el AlertEngine
     *              para evaluar reglas contra métricas actuales.
     *
     * @param int $tenantId ID del tenant cuyas reglas activas se obtendrán
     * @return array Lista de reglas activas como arrays asociativos
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
     * @description Sobrescribe el método paginate() del BaseModel para buscar
     *              específicamente en la columna 'nombre' de la regla.
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
