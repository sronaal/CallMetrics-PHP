<?php
declare(strict_types=1);

namespace CallMetrics\Services;

use CallMetrics\Core\Database;

/**
 * Motor de evaluacion de alertas.
 *
 * Evalua reglas activas contra metricas actuales y dispara alertas
 * cuando se superan los umbrales configurados.
 */
class AlertEngine
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Evaluar todas las reglas activas para un tenant.
     *
     * @return array<int, array{id: int, regla: string, valor: float, nivel: string, mensaje: string}>
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
     * Evaluar una regla individual.
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
     * Obtener cantidad de llamadas perdidas en la ultima hora.
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
     * Obtener la ultima metrica de un tipo especifico.
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
     * Comparar valor contra umbral con la condicion indicada.
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
     * Crear registro de alerta en historial.
     *
     * @return array{id: int, regla: string, valor: float, nivel: string, mensaje: string}
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
