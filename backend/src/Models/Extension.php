<?php
declare(strict_types=1);

namespace CallMetrics\Models;

/**
 * Clase Extension
 *
 * Modelo de extensiones SIP — filtrado por tenant. Representa usuarios
 * telefónicos internos asociados a un servidor PBX. Soporta paginación
 * con búsqueda por número o nombre de extensión.
 *
 * @description Modelo multi-tenant para gestión de extensiones SIP. Permite
 *              búsquedas por número de extensión o nombre del titular.
 * @package CallMetrics\Models
 */
class Extension extends BaseModel
{
    /** @var string Nombre de la tabla en la base de datos */
    protected static string $table = 'extensiones';

    /** @var bool Filtrado por tenant habilitado */
    protected static bool $tenantScoped = true;

    /**
     * Paginación con búsqueda por número o nombre de extensión.
     *
     * @description Sobrescribe el método paginate() del BaseModel para buscar
     *              en las columnas 'numero' y 'nombre' de la extensión.
     *
     * @param int $page Número de página (0-indexed, por defecto 0)
     * @param int $size Tamaño de página (por defecto 10)
     * @param array $conditions Condiciones WHERE adicionales
     * @param string $search Texto de búsqueda para filtrar por número o nombre
     * @param array $searchColumns Columnas de búsqueda (ignorado, usa ['numero', 'nombre'])
     * @return array{data: array, total: int} Datos de la página y total de registros
     */
    public static function paginate(
        int $page = 0,
        int $size = 10,
        array $conditions = [],
        string $search = '',
        array $searchColumns = []
    ): array {
        return parent::paginate($page, $size, $conditions, $search, ['numero', 'nombre']);
    }
}
