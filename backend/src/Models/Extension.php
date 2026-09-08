<?php
declare(strict_types=1);

namespace CallMetrics\Models;

/**
 * Modelo de extensiones SIP — filtrado por tenant.
 * Representa usuarios telefónicos internos asociados a un PBX.
 */
class Extension extends BaseModel
{
    protected static string $table = 'extensiones';
    protected static bool $tenantScoped = true;

    /**
     * Paginación con búsqueda por número o nombre de extensión.
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
        return parent::paginate($page, $size, $conditions, $search, ['numero', 'nombre']);
    }
}
