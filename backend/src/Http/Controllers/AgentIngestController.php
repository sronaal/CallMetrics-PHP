<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, Database};
use CallMetrics\Models\{Pbx, CallRecord, Event, AlertRule};

/**
 * Controlador de ingesta de datos desde el agente Python.
 *
 * Endpoints:
 *   POST /api/agent/heartbeat   — Heartbeat del agente con estado del PBX
 *   POST /api/agent/cdr         — Envio masivo de registros CDR
 *   POST /api/agent/events      — Envio masivo de eventos AMI/CEL
 *   POST /api/agent/metrics     — Metricas de salud del servidor
 *
 * Autenticacion: Token unico por PBX (X-Agent-Token header)
 */
class AgentIngestController extends Controller
{
    /**
     * POST /api/agent/heartbeat
     *
     * El agente envia un heartbeat cada 30 segundos con el estado actual.
     * Actualiza el estado del PBX y el timestamp de ultimo heartbeat.
     *
     * Headers requeridos:
     *   X-Agent-Token: Token unico del agente
     *
     * Body:
     *   {
     *     "pbx_id": 1,
     *     "estado": "ONLINE",
     *     "uptime": 123456,
     *     "load_avg": [1.2, 0.8, 0.5],
     *     "active_channels": 5
     *   }
     */
    public function heartbeat(Request $request): void
    {
        $pbx = $this->authenticateAgent($request);
        if (!$pbx) return;

        $data = $request->body();
        $db = Database::getInstance();

        // Actualizar estado del PBX
        $db->execute(
            "UPDATE pbx SET
                estado = :estado,
                ultimo_heartbeat = NOW(),
                updated_at = NOW()
             WHERE id = :id",
            [
                ':id' => $pbx['id'],
                ':estado' => $data['estado'] ?? 'ONLINE'
            ]
        );

        // Registrar evento de heartbeat
        $db->insert(
            "INSERT INTO eventos (tenant_id, pbx_id, tipo, evento, contenido)
             VALUES (:tenant_id, :pbx_id, 'HEALTH', 'heartbeat', :contenido)",
            [
                ':tenant_id' => $pbx['tenant_id'],
                ':pbx_id' => $pbx['id'],
                ':contenido' => json_encode($data)
            ]
        );

        Response::ok(['received' => true], 'Heartbeat recibido');
    }

    /**
     * POST /api/agent/cdr
     *
     * Recibe un lote de registros CDR desde el agente.
     * Procesa e inserta en la tabla llamadas_cdr.
     *
     * Body:
     *   {
     *     "cdr": [
     *       {
     *         "callid": "abc123",
     *         "extension_origen": "1001",
     *         "extension_destino": "1002",
     *         "numero_origen": "5551234",
     *         "numero_destino": "5555678",
     *         "duracion": 120,
     *         "billable_seconds": 120,
     *         "estado": "ANSWERED",
     *         "inicio_llamada": "2024-01-15 10:30:00",
     *         "fin_llamada": "2024-01-15 10:32:00",
     *         "grabacion_url": "/var/spool/asterisk/monitor/abc123.wav"
     *       }
     *     ]
     *   }
     */
    public function cdr(Request $request): void
    {
        $pbx = $this->authenticateAgent($request);
        if (!$pbx) return;

        $data = $request->body();
        $cdrList = $data['cdr'] ?? [];

        if (empty($cdrList)) {
            Response::error('No se recibieron registros CDR', 422);
            return;
        }

        $db = Database::getInstance();
        $inserted = 0;
        $errors = [];

        foreach ($cdrList as $i => $cdr) {
            // Validar campos requeridos
            if (empty($cdr['callid']) || empty($cdr['inicio_llamada'])) {
                $errors[] = "Registro $i: callid e inicio_llamada son requeridos";
                continue;
            }

            try {
                $db->insert(
                    "INSERT INTO llamadas_cdr (
                        tenant_id, pbx_id, callid, extension_origen, extension_destino,
                        numero_origen, numero_destino, duracion, billable_seconds,
                        estado, inicio_llamada, fin_llamada, grabacion_url
                    ) VALUES (
                        :tenant_id, :pbx_id, :callid, :ext_origen, :ext_dest,
                        :num_origen, :num_dest, :duracion, :billable,
                        :estado, :inicio, :fin, :grabacion
                    )",
                    [
                        ':tenant_id' => $pbx['tenant_id'],
                        ':pbx_id' => $pbx['id'],
                        ':callid' => $cdr['callid'],
                        ':ext_origen' => $cdr['extension_origen'] ?? null,
                        ':ext_dest' => $cdr['extension_destino'] ?? null,
                        ':num_origen' => $cdr['numero_origen'] ?? null,
                        ':num_dest' => $cdr['numero_destino'] ?? null,
                        ':duracion' => $cdr['duracion'] ?? 0,
                        ':billable' => $cdr['billable_seconds'] ?? 0,
                        ':estado' => $cdr['estado'] ?? 'FAILED',
                        ':inicio' => $cdr['inicio_llamada'],
                        ':fin' => $cdr['fin_llamada'] ?? null,
                        ':grabacion' => $cdr['grabacion_url'] ?? null
                    ]
                );
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = "Registro $i: " . $e->getMessage();
            }
        }

        // Verificar alertas de llamadas perdidas
        $this->checkCallAlerts($pbx['tenant_id'], $pbx['id']);

        Response::created([
            'inserted' => $inserted,
            'errors' => $errors
        ], "$inserted registros CDR procesados");
    }

    /**
     * POST /api/agent/events
     *
     * Recibe un lote de eventos AMI/CEL desde el agente.
     *
     * Body:
     *   {
     *     "events": [
     *       {
     *         "tipo": "AMI",
     *         "evento": "Newchannel",
     *         "callid": "abc123",
     *         "contenido": { ... }
     *       }
     *     ]
     *   }
     */
    public function events(Request $request): void
    {
        $pbx = $this->authenticateAgent($request);
        if (!$pbx) return;

        $data = $request->body();
        $eventList = $data['events'] ?? [];

        if (empty($eventList)) {
            Response::error('No se recibieron eventos', 422);
            return;
        }

        $db = Database::getInstance();
        $inserted = 0;

        foreach ($eventList as $event) {
            if (empty($event['tipo']) || empty($event['evento'])) continue;

            $db->insert(
                "INSERT INTO eventos (tenant_id, pbx_id, tipo, evento, callid, contenido)
                 VALUES (:tenant_id, :pbx_id, :tipo, :evento, :callid, :contenido)",
                [
                    ':tenant_id' => $pbx['tenant_id'],
                    ':pbx_id' => $pbx['id'],
                    ':tipo' => $event['tipo'],
                    ':evento' => $event['evento'],
                    ':callid' => $event['callid'] ?? null,
                    ':contenido' => json_encode($event['contenido'] ?? $event)
                ]
            );
            $inserted++;
        }

        Response::created(['inserted' => $inserted], "$inserted eventos procesados");
    }

    /**
     * POST /api/agent/metrics
     *
     * Recibe metricas de salud del servidor PBX.
     *
     * Body:
     *   {
     *     "cpu_usage": 45.2,
     *     "memory_usage": 62.8,
     *     "disk_usage": 78.5,
     *     "active_channels": 5,
     *     "sip_peers_online": 12,
     *     "uptime": 123456
     *   }
     */
    public function metrics(Request $request): void
    {
        $pbx = $this->authenticateAgent($request);
        if (!$pbx) return;

        $data = $request->body();
        $db = Database::getInstance();

        // Registrar metricas como evento de sistema
        $db->insert(
            "INSERT INTO eventos (tenant_id, pbx_id, tipo, evento, contenido)
             VALUES (:tenant_id, :pbx_id, 'HEALTH', 'metrics', :contenido)",
            [
                ':tenant_id' => $pbx['tenant_id'],
                ':pbx_id' => $pbx['id'],
                ':contenido' => json_encode($data)
            ]
        );

        // Verificar alertas de CPU/RAM
        $this->checkServerAlerts($pbx['tenant_id'], $data);

        Response::ok(['received' => true], 'Metricas recibidas');
    }

    /**
     * Autenticar agente usando token unico por PBX.
     */
    private function authenticateAgent(Request $request): ?array
    {
        $token = $request->header('X-Agent-Token');
        if (!$token) {
            Response::unauthorized('Token de agente requerido (X-Agent-Token)');
            return null;
        }

        $pbx = Pbx::findByToken($token);
        if (!$pbx) {
            Response::unauthorized('Token de agente invalido');
            return null;
        }

        if (!$pbx['activo']) {
            Response::forbidden('Agente desactivado');
            return null;
        }

        return $pbx;
    }

    /**
     * Verificar alertas de llamadas perdidas.
     */
    private function checkCallAlerts(int $tenantId, int $pbxId): void
    {
        $db = Database::getInstance();

        // Obtener reglas activas de llamadas perdidas
        $rules = $db->fetchAll(
            "SELECT * FROM reglas_alerta
             WHERE tenant_id = :tenant_id AND tipo = 'LLAMADAS_PERDIDAS' AND activo = 1",
            [':tenant_id' => $tenantId]
        );

        foreach ($rules as $rule) {
            // Contar llamadas perdidas en la ultima hora
            $result = $db->fetchOne(
                "SELECT COUNT(*) as total FROM llamadas_cdr
                 WHERE tenant_id = :tenant_id AND pbx_id = :pbx_id
                 AND estado != 'ANSWERED'
                 AND inicio_llamada > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                [':tenant_id' => $tenantId, ':pbx_id' => $pbxId]
            );

            $total = (int) $result['total'];
            $umbral = (float) $rule['umbral'];

            if ($this->evaluateCondition($total, $rule['condicion'], $umbral)) {
                $this->triggerAlert($rule, $total);
            }
        }
    }

    /**
     * Verificar alertas de CPU/RAM.
     */
    private function checkServerAlerts(int $tenantId, array $metrics): void
    {
        $db = Database::getInstance();

        // Obtener reglas activas de CPU y RAM
        $rules = $db->fetchAll(
            "SELECT * FROM reglas_alerta
             WHERE tenant_id = :tenant_id AND tipo IN ('CPU', 'RAM') AND activo = 1",
            [':tenant_id' => $tenantId]
        );

        foreach ($rules as $rule) {
            $value = 0;
            if ($rule['tipo'] === 'CPU' && isset($metrics['cpu_usage'])) {
                $value = (float) $metrics['cpu_usage'];
            } elseif ($rule['tipo'] === 'RAM' && isset($metrics['memory_usage'])) {
                $value = (float) $metrics['memory_usage'];
            }

            $umbral = (float) $rule['umbral'];
            if ($this->evaluateCondition($value, $rule['condicion'], $umbral)) {
                $this->triggerAlert($rule, $value);
            }
        }
    }

    /**
     * Evaluar condicion de alerta.
     */
    private function evaluateCondition(float $value, string $condition, float $threshold): bool
    {
        return match ($condition) {
            'MAYOR' => $value > $threshold,
            'MENOR' => $value < $threshold,
            'IGUAL' => abs($value - $threshold) < 0.001,
            default => false
        };
    }

    /**
     * Disparar alerta y registrar en historial.
     */
    private function triggerAlert(array $rule, float $actualValue): void
    {
        $db = Database::getInstance();

        $mensaje = sprintf(
            "Alerta: %s - Valor actual: %.2f %s (Umbral: %s %s)",
            $rule['nombre'],
            $actualValue,
            $rule['unidad'] ?? '',
            $rule['condicion'],
            $rule['umbral']
        );

        $db->insert(
            "INSERT INTO historial_alertas (tenant_id, regla_id, valor_actual, mensaje, nivel)
             VALUES (:tenant_id, :regla_id, :valor, :mensaje, :nivel)",
            [
                ':tenant_id' => $rule['tenant_id'],
                ':regla_id' => $rule['id'],
                ':valor' => $actualValue,
                ':mensaje' => $mensaje,
                ':nivel' => $actualValue > ($rule['umbral'] * 1.5) ? 'CRITICAL' : 'WARNING'
            ]
        );
    }
}
