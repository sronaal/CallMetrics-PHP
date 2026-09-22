<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\{Database, TenantContext};

/**
 * Clase BaseModel
 *
 * Clase base abstracta Active Record con filtrado automático multi-tenant.
 * Proporciona métodos CRUD genéricos que las clases hijas heredan y pueden
 * sobrescribir. Cada modelo define su propia tabla y comportamiento de filtrado.
 *
 * @description Implementa el patrón Active Record con soporte multi-tenant.
 *              Cada método de consulta (excepto findBy) agrega WHERE tenant_id = :tenant_id
 *              cuando static::$tenantScoped es true Y el usuario actual NO es SUPER_ADMIN.
 * @package CallMetrics\Models
 */
abstract class BaseModel
{
    /** @var string Nombre de la tabla en la base de datos — DEBE ser sobreescrito por las clases hijas */
    protected static string $table = '';

    /** @var bool Cuando es true, las consultas se filtran automáticamente por el tenant actual */
    protected static bool $tenantScoped = true;

    // ---------------------------------------------------------------
    // Métodos de lectura
    // ---------------------------------------------------------------

    /**
     * Obtiene todas las filas que coincidan con condiciones opcionales.
     *
     * @description Ejecuta un SELECT con filtros opcionales, ordenamiento y límite.
     *              Agrega automáticamente el filtro de tenant cuando $tenantScoped = true
     *              Y el usuario actual NO es SUPER_ADMIN.
     *
     * @param array $conditions Condiciones WHERE como clave => valor (ej: ['activo' => 1])
     * @param string $orderBy Cláusula ORDER BY (por defecto 'id DESC')
     * @param int $limit Número máximo de registros a retornar (por defecto 100)
     * @return array Lista de filas como arrays asociativos
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
     * Cuenta filas que coincidan con condiciones opcionales.
     *
     * @description Ejecuta un COUNT(*) con filtros opcionales. Aplica filtro de
     *              tenant automáticamente cuando corresponde.
     *
     * @param array $conditions Condiciones WHERE como clave => valor
     * @return int Número total de filas que coinciden
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
     * Busca una sola fila por clave primaria.
     *
     * @description Ejecuta un SELECT por ID con filtro de tenant automático.
     *              Retorna null si no se encuentra el registro.
     *
     * @param int $id ID del registro a buscar
     * @return ?array Fila como array asociativo o null si no existe
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
     * Busca una fila por el valor de una columna arbitraria — SIN filtro de tenant.
     *
     * @description Diseñado para búsquedas entre tenants (ej. User::findByEmail).
     *              NO aplica filtrado multi-tenant, permitiendo búsquedas globales.
     *
     * @param string $column Nombre de la columna a buscar
     * @param mixed $value Valor a buscar en la columna
     * @return ?array Fila como array asociativo o null si no existe
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
     * Inserta una nueva fila y retorna su ID autoincremental.
     *
     * @description Ejecuta un INSERT con los datos proporcionados. Genera los
     *              placeholders automáticamente a partir de las claves del array.
     *
     * @param array $data Datos a insertar como clave => valor (columna => valor)
     * @return int ID del registro insertado
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
     * Actualiza una fila existente por ID, respetando el filtro de tenant cuando está habilitado.
     *
     * @description Ejecuta un UPDATE con los datos proporcionados. Aplica filtro de
     *              tenant si está habilitado y el usuario NO es SUPER_ADMIN.
     *
     * @param int $id ID del registro a actualizar
     * @param array $data Datos a actualizar como clave => valor
     * @return int Número de filas afectadas (0 o 1)
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
     * Elimina una fila por ID, respetando el filtro de tenant cuando está habilitado.
     *
     * @description Ejecuta un DELETE con filtro de tenant automático. Retorna el
     *              número de filas afectadas (0 o 1).
     *
     * @param int $id ID del registro a eliminar
     * @return int Número de filas afectadas (0 o 1)
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
     * @description Ejecuta una consulta paginada con soporte para búsqueda de texto
     *              en columnas específicas. Retorna los datos de la página actual
     *              y el total de registros para calcular la paginación.
     *
     * @param int $page Número de página (0-indexed, por defecto 0)
     * @param int $size Tamaño de página (por defecto 10)
     * @param array $conditions Condiciones WHERE como clave => valor
     * @param string $search Texto de búsqueda para filtrar por columnas específicas
     * @param array $searchColumns Columnas donde aplicar la búsqueda de texto
     * @return array{data: array, total: int} Datos de la página y total de registros
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

        // Página de datos - Usar parámetros para LIMIT/OFFSET (Prevenir SQL Injection)
        $offset  = $page * $size;
        $dataSql = "SELECT * FROM " . static::$table
                 . $whereClause
                 . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        
        // Agregar parámetros de límite y desplazamiento
        $params[':limit'] = (int)$size;
        $params[':offset'] = (int)$offset;
        
        $data = $db->fetchAll($dataSql, $params);

        return ['data' => $data, 'total' => $total];
    }

    // ---------------------------------------------------------------
    // Métodos auxiliares internos
    // ---------------------------------------------------------------

    /**
     * Agrega la cláusula WHERE del tenant cuando aplique.
     *
     * @description Modifica $wheres y $params in place. Agrega la condición
     *              tenant_id = :tenant_id solo cuando:
     *              - $tenantScoped es true
     *              - El usuario actual NO es SUPER_ADMIN
     *              - Existe un tenant_id válido en el contexto
     *
     * @param array &$wheres Array de cláusulas WHERE (modificado por referencia)
     * @param array &$params Array de parámetros (modificado por referencia)
     * @return void
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
