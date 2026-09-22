<?php
declare(strict_types=1);

namespace CallMetrics\Core;

use PDO;
use PDOException;

/**
 * Clase Database
 *
 * Proporciona acceso singleton a la conexión PDO de MySQL. Implementa el patrón
 * Singleton para garantizar una única conexión a la base de datos en toda la
 * aplicación. Ofrece métodos de conveniencia para ejecutar consultas comunes
 * como SELECT, INSERT, UPDATE y DELETE.
 *
 * @package CallMetrics\Core
 */
class Database
{
    /** @var Database|null Instancia singleton de la clase */
    private static ?Database $instance = null;

    /** @var PDO Conexión PDO subyacente */
    private PDO $pdo;

    /**
     * Constructor privado.
     *
     * @description Crea la conexión PDO a MySQL utilizando la configuración
     *              definida en config/database.php. Configura el modo de error
     *              a excepciones y el modo de obtención a array asociativo.
     *              Los prepares emulados están deshabilitados para mayor seguridad.
     */
    private function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        $this->pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /**
     * Obtiene la instancia singleton de Database.
     *
     * @description Retorna la única instancia de Database, creándola si es necesario.
     *              Este patrón garantiza una sola conexión a la base de datos.
     *
     * @return self Instancia singleton de Database
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retorna la instancia PDO subyacente para uso directo.
     *
     * @description Permite acceso directo a la conexión PDO cuando se necesitan
     *              operaciones avanzadas no cubiertas por los métodos de conveniencia.
     *
     * @return PDO Instancia PDO de la conexión actual
     */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Ejecuta un SELECT y retorna todas las filas coincidentes.
     *
     * @description Prepara y ejecuta una consulta SQL SELECT retornando todas las
     *              filas del resultado como arrays asociativos.
     *
     * @param string $sql Consulta SQL con marcadores de posición
     * @param array $params Parámetros vinculados a la consulta preparada
     * @return array Lista de filas como arrays asociativos
     */
    public function fetchAll(string $sql, array $params = []): array
    {   
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ejecuta un SELECT y retorna una sola fila, o null si no hay coincidencia.
     *
     * @description Prepara y ejecuta una consulta SQL SELECT retornando solo la
     *              primera fila del resultado. Retorna null si no hay resultados.
     *
     * @param string $sql Consulta SQL con marcadores de posición
     * @param array $params Parámetros vinculados a la consulta preparada
     * @return ?array Fila como array asociativo o null si no hay resultados
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Ejecuta un INSERT y retorna el último ID insertado.
     *
     * @description Prepara y ejecuta una consulta SQL INSERT, retornando el ID
     *              generado automáticamente por la base de datos.
     *
     * @param string $sql Consulta SQL INSERT con marcadores de posición
     * @param array $params Parámetros vinculados a la consulta preparada
     * @return int ID del último registro insertado
     */
    public function insert(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Ejecuta un UPDATE/DELETE y retorna el número de filas afectadas.
     *
     * @description Prepara y ejecuta una consulta SQL UPDATE o DELETE, retornando
     *              la cantidad de filas modificadas o eliminadas.
     *
     * @param string $sql Consulta SQL UPDATE o DELETE con marcadores de posición
     * @param array $params Parámetros vinculados a la consulta preparada
     * @return int Número de filas afectadas por la consulta
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Ejecuta un COUNT y retorna el resultado como entero.
     *
     * @description Prepara y ejecuta una consulta SQL COUNT, retornando el
     *              resultado como un entero. Útil para obtener el total de
     *              registros que cumplen una condición.
     *
     * @param string $sql Consulta SQL COUNT con marcadores de posición
     * @param array $params Parámetros vinculados a la consulta preparada
     * @return int Resultado del COUNT como entero
     */
    public function count(string $sql, array $params = []): int
    {
        $result = $this->fetchOne($sql, $params);
        return (int) reset($result);
    }
}
