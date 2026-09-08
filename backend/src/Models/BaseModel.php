<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\{Database, TenantContext};

/**
 * Clase base abstracta Active Record con filtrado automático multi-tenant.
 *
 * Cada método de consulta (excepto findBy) agrega WHERE tenant_id = :tenant_id
 * cuando static::$tenantScoped es true Y el usuario actual NO es SUPER_ADMIN.
 */
abstract class BaseModel
{
    /** @var string Nombre de tabla — DEBE ser sobreescrito por las clases hijas. */
    protected static string $table = '';

    /** @var bool Cuando es true, las consultas se filtran automáticamente por el tenant actual. */
    protected static bool $tenantScoped = true;

    // ---------------------------------------------------------------
    // Métodos de lectura
    // ---------------------------------------------------------------

    /**
     * Obtener todas las filas que coincidan con condiciones opcionales.
     *
     * Agrega automáticamente el filtro de tenant cuando $tenantScoped = true
     * Y el usuario actual NO es SUPER_ADMIN.
     */
    public static function findAll(array $conditions = [], string $orderBy = 'id DESC', int $limit = 100): array
    {
        $db     = Database::getInstance();
        $params = [];
        $wheres = [];

        static::applyTenantFilter($wheres, $params);

        foreach ($conditions as $column => $value) {
            $wheres[]          = "$column = :$column";
            $params[":$column"] = $value;
        }

        $sql = "SELECT * FROM " . static::$table;
        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(' AND ', $wheres);
        }
        $sql .= " ORDER BY $orderBy LIMIT " . (int) $limit;

        return $db->fetchAll($sql, $params);
    }

    /**
     * Contar filas que coincidan con condiciones opcionales.
     */
    public static function count(array $conditions = []): int
    {
        $db     = Database::getInstance();
        $params = [];
        $wheres = [];

        static::applyTenantFilter($wheres, $params);

        foreach ($conditions as $column => $value) {
            $wheres[]          = "$column = :$column";
            $params[":$column"] = $value;
        }

        $sql = "SELECT COUNT(*) as total FROM " . static::$table;
        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(' AND ', $wheres);
        }

        return (int) $db->fetchOne($sql, $params)['total'];
    }

    /**
     * Buscar una sola fila por clave primaria.
     */
    public static function find(int $id): ?array
    {
        $db     = Database::getInstance();
        $params = [':id' => $id];
        $wheres = ["id = :id"];

        static::applyTenantFilter($wheres, $params);

        $sql = "SELECT * FROM " . static::$table . " WHERE " . implode(' AND ', $wheres) . " LIMIT 1";

        return $db->fetchOne($sql, $params);
    }

    /**
     * Buscar una fila por el valor de una columna arbitraria — SIN filtro de tenant.
     *
     * Diseñado para búsquedas entre tenants (ej. User::findByEmail).
     */
    public static function findBy(string $column, mixed $value): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM " . static::$table . " WHERE $column = :value LIMIT 1";
        return $db->fetchOne($sql, [':value' => $value]);
    }

    // ---------------------------------------------------------------
    // Métodos de escritura
    // ---------------------------------------------------------------

    /**
     * Insertar una nueva fila y retornar su ID autoincremental.
     */
    public static function create(array $data): int
    {
        $db          = Database::getInstance();
        $columns     = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO " . static::$table . " ($columns) VALUES ($placeholders)";
        return $db->insert($sql, $data);
    }

    /**
     * Actualizar una fila existente por ID, respetando el filtro de tenant cuando está habilitado.
     *
     * Retorna el número de filas afectadas (0 o 1).
     */
    public static function update(int $id, array $data): int
    {
        $db     = Database::getInstance();
        $sets   = [];
        $params = [':id' => $id];

        foreach ($data as $column => $value) {
            $sets[]            = "$column = :$column";
            $params[":$column"] = $value;
        }

        $conditions = ["id = :id"];

        if (static::$tenantScoped && !TenantContext::isSuperAdmin()) {
            $tid = TenantContext::get();
            if ($tid !== null) {
                $conditions[]         = "tenant_id = :tenant_id";
                $params[':tenant_id'] = $tid;
            }
        }

        $sql = "UPDATE " . static::$table
             . " SET " . implode(', ', $sets)
             . " WHERE " . implode(' AND ', $conditions);

        return $db->execute($sql, $params);
    }

    /**
     * Eliminar una fila por ID, respetando el filtro de tenant cuando está habilitado.
     *
     * Retorna el número de filas afectadas (0 o 1).
     */
    public static function delete(int $id): int
    {
        $db     = Database::getInstance();
        $params = [':id' => $id];
        $wheres = ["id = :id"];

        static::applyTenantFilter($wheres, $params);

        $sql = "DELETE FROM " . static::$table . " WHERE " . implode(' AND ', $wheres);

        return $db->execute($sql, $params);
    }

    // ---------------------------------------------------------------
    // Paginación
    // ---------------------------------------------------------------

    /**
     * Lista paginada con condiciones opcionales y búsqueda de texto.
     *
     * @return array{data: array, total: int}
     */
    public static function paginate(
        int    $page  = 0,
        int    $size  = 10,
        array  $conditions = [],
        string $search     = '',
        array  $searchColumns = []
    ): array {
        $db     = Database::getInstance();
        $params = [];
        $wheres = [];

        static::applyTenantFilter($wheres, $params);

        // Búsqueda de texto en columnas específicas
        if ($search !== '' && !empty($searchColumns)) {
            $searchClauses = [];
            foreach ($searchColumns as $col) {
                $searchClauses[] = "$col LIKE :search";
            }
            $wheres[]       = '(' . implode(' OR ', $searchClauses) . ')';
            $params[':search'] = "%$search%";
        }

        foreach ($conditions as $column => $value) {
            $wheres[]          = "$column = :$column";
            $params[":$column"] = $value;
        }

        $whereClause = !empty($wheres) ? ' WHERE ' . implode(' AND ', $wheres) : '';

        // Conteo total
        $countSql = "SELECT COUNT(*) as total FROM " . static::$table . $whereClause;
        $total    = (int) $db->fetchOne($countSql, $params)['total'];

        // Página de datos
        $offset  = $page * $size;
        $dataSql = "SELECT * FROM " . static::$table
                 . $whereClause
                 . " ORDER BY id DESC LIMIT $size OFFSET $offset";
        $data = $db->fetchAll($dataSql, $params);

        return ['data' => $data, 'total' => $total];
    }

    // ---------------------------------------------------------------
    // Métodos auxiliares internos
    // ---------------------------------------------------------------

    /**
     * Agregar la cláusula WHERE del tenant cuando aplique.
     *
     * Modifica $wheres y $params in place.
     */
    protected static function applyTenantFilter(array &$wheres, array &$params): void
    {
        if (!static::$tenantScoped || TenantContext::isSuperAdmin()) {
            return;
        }

        $tid = TenantContext::get();
        if ($tid !== null) {
            $wheres[]          = "tenant_id = :tenant_id";
            $params[':tenant_id'] = $tid;
        }
    }
}
