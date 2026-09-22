<?php
declare(strict_types=1);

/**
 * Motor de evaluación de alertas para el sistema CallMetric Pro.
 *
 * Evalúa reglas activas contra métricas actuales y dispara alertas
 * cuando se superan los umbrales configurados. Soporta los siguientes
 * tipos de regla:
 *   - LLAMADAS_PERDIDAS: cantidad de llamadas no contestadas en la última hora
 *   - CPU: uso de CPU reportado por el agente de monitoreo
 *   - RAM: uso de memoria reportado por el agente de monitoreo
 *   - COLA_SATURADA: tiempo promedio de espera en colas de atención
 *
 * @package CallMetrics\Services
 */

namespace CallMetrics\Services;

use CallMetrics\Core\Database;

/**
 * Motor de evaluación de alertas.
 *
 * Evalúa reglas activas contra métricas actuales y dispara alertas
 * cuando se superan los umbrales configurados.
 */
class AlertEngine
{
    /**
     * Instancia de conexión a la base de datos.
     *
     * @var Database
     */
    private Database $db;

    /**
     * Constructor. Inicializa la conexión a la base de datos.
     *
     * @description Obtiene la instancia singleton de Database para consultas.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Evaluar todas las reglas activas para un tenant.
     *
     * @description Recupera todas las reglas activas de la tabla reglas_alerta
     *              para el tenant indicado, evalúa cada una y devuelve las que
     *              se han disparado (umbral superado).
     *
     * @param int $tenantId Identificador único del tenant.
     *
     * @return array<int, array{id: int, regla: string, valor: float, nivel: string, mensaje: string}>
     *         Lista de alertas disparadas. Cada elemento contiene el ID de la
     *         alerta creada, el nombre de la regla, el valor actual, el nivel
     *         (WARNING o CRITICAL) y un mensaje descriptivo.
     */
    public function evaluate(int $tenantId): array
    {
        $rules = $this->db->fetchAll(
            "SELECT * FROM reglas_alerta WHERE tenant_id = :tenant_id AND activo = 1",
            [':tenant_id' => $tenantId]
        );

        $triggered = [];
        foreach ($rules as $rule) {
            $result = $this->evaluateRule($rule);
            if ($result) {
                $triggered[] = $result;
            }
        }

        return $triggered;
    }

    /**
     * Evaluar una regla individual contra su métrica correspondiente.
     *
     * @description Determina el tipo de regla, obtiene el valor actual de la
     *              métrica, compara contra el umbral usando la condición definida
     *              y crea una alerta si se supera.
     *
     * @param array{id: int, tipo: string, tenant_id: int, umbral: float, condicion: string, nombre: string, unidad: string|null} $rule
     *        Regla a evaluar. Debe contener tipo, tenant_id, umbral, condicion,
     *        nombre y opcionalmente unidad.
     *
     * @return array{id: int, regla: string, valor: float, nivel: string, mensaje: string}|null
     *         Alerta creada si el umbral se superó, null si no.
     */
    private function evaluateRule(array $rule): ?array
    {
        $value = match ($rule['tipo']) {
            'LLAMADAS_PERDIDAS' => $this->getMissedCalls($rule['tenant_id']),
            'CPU' => $this->getLatestMetric($rule['tenant_id'], 'cpu_usage'),
            'RAM' => $this->getLatestMetric($rule['tenant_id'], 'memory_usage'),
            'COLA_SATURADA' => $this->getQueueWait($rule['tenant_id']),
            default => null
        };

        if ($value === null) return null;

        $threshold = (float) $rule['umbral'];
        if ($this->compare($value, $rule['condicion'], $threshold)) {
            return $this->createAlert($rule, $value);
        }

        return null;
    }

    /**
     * Obtener cantidad de llamadas perdidas en la última hora.
     *
     * @description Consulta la tabla llamadas_cdr para contar llamadas cuyo estado
     *              NO es 'ANSWERED' en la última hora para el tenant dado.
     *
     * @param int $tenantId Identificador del tenant.
     *
     * @return float|null Cantidad de llamadas perdidas, o null si la consulta falla.
     */
    private function getMissedCalls(int $tenantId): ?float
    {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as total FROM llamadas_cdr
             WHERE tenant_id = :tenant_id
             AND estado != 'ANSWERED'
             AND inicio_llamada > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [':tenant_id' => $tenantId]
        );
        return (float) ($result['total'] ?? 0);
    }

    /**
     * Obtener la última métrica de un tipo específico.
     *
     * @description Recupera el evento más reciente de tipo HEALTH con nombre
     *              'metrics' y extrae la métrica solicitada del JSON almacenado.
     *
     * @param int    $tenantId Identificador del tenant.
     * @param string $metric   Nombre de la métrica a extraer (ej: 'cpu_usage', 'memory_usage').
     *
     * @return float|null Valor de la métrica, o null si no existe datos o la métrica no está presente.
     */
    private function getLatestMetric(int $tenantId, string $metric): ?float
    {
        $result = $this->db->fetchOne(
            "SELECT contenido FROM eventos
             WHERE tenant_id = :tenant_id AND tipo = 'HEALTH' AND evento = 'metrics'
             ORDER BY created_at DESC LIMIT 1",
            [':tenant_id' => $tenantId]
        );

        if (!$result) return null;
        $data = json_decode($result['contenido'], true);
        return isset($data[$metric]) ? (float) $data[$metric] : null;
    }

    /**
     * Obtener tiempo promedio de espera en colas.
     *
     * @description Calcula el promedio de llamadas_enespera de todas las colas
     *              activas del tenant.
     *
     * @param int $tenantId Identificador del tenant.
     *
     * @return float|null Tiempo promedio de espera, o 0 si no hay colas activas.
     */
    private function getQueueWait(int $tenantId): ?float
    {
        $result = $this->db->fetchOne(
            "SELECT AVG(llamadas_enespera) as avg_wait FROM colas
             WHERE tenant_id = :tenant_id AND activo = 1",
            [':tenant_id' => $tenantId]
        );
        return (float) ($result['avg_wait'] ?? 0);
    }

    /**
     * Comparar valor contra umbral con la condición indicada.
     *
     * @description Evalúa la condición lógica entre el valor actual y el umbral.
     *              Soporta MAYOR, MENOR e IGUAL (con tolerancia de 0.001 para
     *              comparación de punto flotante).
     *
     * @param float  $value    Valor actual de la métrica.
     * @param string $condition Condición a evaluar: 'MAYOR', 'MENOR' o 'IGUAL'.
     * @param float  $threshold Umbral configurado en la regla.
     *
     * @return bool true si la condición se cumple, false en caso contrario.
     */
    private function compare(float $value, string $condition, float $threshold): bool
    {
        return match ($condition) {
            'MAYOR' => $value > $threshold,
            'MENOR' => $value < $threshold,
            'IGUAL' => abs($value - $threshold) < 0.001,
            default => false
        };
    }

    /**
     * Crear registro de alerta en el historial.
     *
     * @description Inserta un registro en la tabla historial_alertas con el nivel
     *              determinado automáticamente: CRITICAL si el valor supera el umbral
     *              en un 50%, WARNING de lo contrario.
     *
     * @param array{tenant_id: int, id: int, nombre: string, condicion: string, umbral: float, unidad: string|null} $rule
     *        Regla que se disparó.
     * @param float $value Valor actual que disparó la alerta.
     *
     * @return array{id: int, regla: string, valor: float, nivel: string, mensaje: string}
     *         Alerta creada con su ID, nombre de regla, valor, nivel y mensaje.
     */
    private function createAlert(array $rule, float $value): array
    {
        $mensaje = sprintf(
            "%s: %.2f %s (Umbral: %s %s)",
            $rule['nombre'],
            $value,
            $rule['unidad'] ?? '',
            $rule['condicion'],
            $rule['umbral']
        );

        // Nivel: CRITICAL si supera el umbral en un 50%, WARNING de lo contrario
        $nivel = $value > ($rule['umbral'] * 1.5) ? 'CRITICAL' : 'WARNING';

        $id = $this->db->insert(
            "INSERT INTO historial_alertas (tenant_id, regla_id, valor_actual, mensaje, nivel)
             VALUES (:tenant_id, :regla_id, :valor, :mensaje, :nivel)",
            [
                ':tenant_id' => $rule['tenant_id'],
                ':regla_id' => $rule['id'],
                ':valor' => $value,
                ':mensaje' => $mensaje,
                ':nivel' => $nivel
            ]
        );

        return [
            'id' => $id,
            'regla' => $rule['nombre'],
            'valor' => $value,
            'nivel' => $nivel,
            'mensaje' => $mensaje
        ];
    }
}
