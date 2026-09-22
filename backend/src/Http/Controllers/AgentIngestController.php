<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, Database, TenantContext};
use CallMetrics\Models\{Pbx, CallRecord, Event, AlertRule};
use CallMetrics\WebSocket\EventBridge;

/**
 * Clase AgentIngestController
 *
 * Controlador de ingesta de datos desde el agente collector. Recibe heartbeats,
 * registros CDR, eventos AMI/CEL y métricas de salud del servidor PBX.
 * Autentica agentes mediante UUID único (X-Agent-ID header).
 *
 * @description Compatible con agente Python y agente Spring/Java. Procesa lotes
 *              de datos, valida integridad, almacena en BD y dispara alertas
 *              cuando se superan umbrales configurados. Broadcastea eventos
 *              en tiempo real a través de EventBridge.
 * @package CallMetrics\Http\Controllers
 */
class AgentIngestController extends Controller
{
    /**
     * POST /api/agent/heartbeat
     *
     * Recibe un heartbeat del agente con el estado actual del PBX.
     *
     * @description El agente envía un heartbeat cada 30 segundos. Actualiza el
     *              estado del PBX, registra evento de salud y broadcastea el
     *              estado a suscriptores del canal pbx_{id}. Normaliza estados
     *              del agente (español/inglés) al formato de la BD.
     *
     * @param Request $request Solicitud con X-Agent-ID header y datos del PBX en el body
     * @return void Nunca retorna — termina con Response
     */
    public function heartbeat(Request $request): void
    {
        $pbx = $this->authenticateAgent($request);
        if (!$pbx) return;

        $data = $request->body();
        $db = Database::getInstance();

        // Normalizar estado del agente (Python envía español: "activo", "error", etc.)
        // La DB espera ENUM('ONLINE','OFFLINE','ERROR')
        $estadoRaw = $data['estado'] ?? 'ONLINE';
        $estado = match (strtolower($estadoRaw)) {
            'activo', 'active', 'online'       => 'ONLINE',
            'detenido', 'stopped', 'offline',
            'deteniendo', 'deteniendose'         => 'OFFLINE',
            'error', 'modo_seguro', 'safe_mode' => 'ERROR',
            default                              => 'ONLINE',  // fallback seguro
        };

        // Actualizar estado del PBX
        $db->execute(
            "UPDATE pbx SET
                estado = :estado,
                ultimo_heartbeat = NOW(),
                updated_at = NOW()
             WHERE id = :id",
            [
                ':id' => $pbx['id'],
                ':estado' => $estado
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

        // Broadcast estado del PBX a suscriptores del canal pbx_{id}
        $bridge = EventBridge::getInstance();
        if ($bridge->isReady()) {
            $bridge->broadcastPbxHealth((int) $pbx['id'], [
                'estado' => $estado,
                'uptime' => $data['uptime'] ?? null,
                'active_channels' => $data['active_channels'] ?? null,
                'conexion_ami' => $data['conexion_ami'] ?? null,
            ]);
        }

        Response::ok(['received' => true], 'Heartbeat recibido');
    }

    /**
     * POST /api/agent/cdr
     *
     * Recibe un lote de registros CDR desde el agente.
     *
     * @description Procesa e inserta registros CDR en la tabla llamadas_cdr.
     *              Valida campos requeridos, integridad de datos, sanitiza URLs
     *              de grabación y verifica alertas de llamadas perdidas.
     *              Broadcastea cada CDR procesado como evento call_ended.
     *
     * @param Request $request Solicitud con X-Agent-ID header y array cdr en el body
     * @return void Nunca retorna — termina con Response
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

            // Validar integridad de datos
            $validacion = $this->validarCdr($cdr, $i);
            if ($validacion !== true) {
                $errors[] = $validacion;
                continue;
            }

            // Sanitizar grabacion_url para prevenir XSS almacenado
            if (!empty($cdr['grabacion_url'])) {
                $cdr['grabacion_url'] = filter_var($cdr['grabacion_url'], FILTER_SANITIZE_URL);
                // Validar que sea URL absoluta válida
                if (!filter_var($cdr['grabacion_url'], FILTER_VALIDATE_URL)) {
                    $errors[] = "Registro $i: URL de grabación inválida";
                    continue;
                }
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

        // Broadcast cada CDR procesado como call_ended
        $bridge = EventBridge::getInstance();
        if ($bridge->isReady()) {
            foreach ($cdrList as $cdr) {
                if (empty($cdr['callid'])) continue;

                $bridge->broadcastCallEvent((int) $pbx['tenant_id'], 'call_ended', [
                    'callid' => $cdr['callid'],
                    'extension_origen' => $cdr['extension_origen'] ?? null,
                    'extension_destino' => $cdr['extension_destino'] ?? null,
                    'numero_origen' => $cdr['numero_origen'] ?? null,
                    'numero_destino' => $cdr['numero_destino'] ?? null,
                    'duracion' => $cdr['duracion'] ?? 0,
                    'billable_seconds' => $cdr['billable_seconds'] ?? 0,
                    'estado' => $cdr['estado'] ?? 'FAILED',
                    'inicio_llamada' => $cdr['inicio_llamada'] ?? null,
                    'fin_llamada' => $cdr['fin_llamada'] ?? null,
                    'pbx_id' => $pbx['id'],
                ]);
            }
        }

        Response::created([
            'inserted' => $inserted,
            'errors' => $errors
        ], "$inserted registros CDR procesados");
    }

    /**
     * POST /api/agent/cdr-report
     *
     * Recibe el reporte completo CDR del agente-collector con 5 datasets anidados.
     *
     * @description Procesa llamadasNormalizadas, colasResumen, agentesResumen,
     *              estadisticasColas y llamadasReal. Utiliza INSERT ... ON DUPLICATE KEY
     *              UPDATE para upsert (insertar o actualizar). Broadcastea evento cdr_report.
     *
     * @param Request $request Solicitud con X-Agent-ID header y datos anidados en el body
     * @return void Nunca retorna — termina con Response
     */
    public function cdrReport(Request $request): void
    {
        $pbx = $this->authenticateAgent($request);
        if (!$pbx) return;

        $data = $request->body();
        $datos = $data['datos'] ?? [];

        if (empty($datos)) {
            Response::error('No se recibieron datos en el campo "datos"', 422);
            return;
        }

        $db = Database::getInstance();
        $tenantId = (int) $pbx['tenant_id'];
        $pbxId = (int) $pbx['id'];
        $counts = [];

        // --- 1. llamadasNormalizadas → cdr_llamadas ---
        $llamadas = $datos['llamadasNormalizadas'] ?? [];
        $counts['llamadas'] = 0;
        foreach ($llamadas as $row) {
            if (empty($row['linkedid'])) continue;
            try {
                $db->execute(
                    "INSERT INTO cdr_llamadas
                        (tenant_id, pbx_id, linkedid, fecha_inicio, numero_origen, destino_inicial,
                         paso_por_cola, nombre_cola, extension_agente, nombre_agente,
                         tiempo_conversacion, tiempo_timbrado, estado_final)
                     VALUES
                        (:tenant_id, :pbx_id, :linkedid, :fecha_inicio, :numero_origen, :destino_inicial,
                         :paso_por_cola, :nombre_cola, :extension_agente, :nombre_agente,
                         :tiempo_conversacion, :tiempo_timbrado, :estado_final)
                     ON DUPLICATE KEY UPDATE
                        fecha_inicio = VALUES(fecha_inicio), numero_origen = VALUES(numero_origen),
                        destino_inicial = VALUES(destino_inicial), paso_por_cola = VALUES(paso_por_cola),
                        nombre_cola = VALUES(nombre_cola), extension_agente = VALUES(extension_agente),
                        nombre_agente = VALUES(nombre_agente), tiempo_conversacion = VALUES(tiempo_conversacion),
                        tiempo_timbrado = VALUES(tiempo_timbrado), estado_final = VALUES(estado_final)",
                    [
                        ':tenant_id' => $tenantId,
                        ':pbx_id' => $pbxId,
                        ':linkedid' => $row['linkedid'],
                        ':fecha_inicio' => $row['fecha_inicio'] ?? null,
                        ':numero_origen' => $row['numero_origen'] ?? null,
                        ':destino_inicial' => $row['destino_inicial'] ?? null,
                        ':paso_por_cola' => $row['paso_por_cola'] ?? null,
                        ':nombre_cola' => $row['nombre_cola'] ?? null,
                        ':extension_agente' => $row['extension_agente'] ?? null,
                        ':nombre_agente' => $row['nombre_agente'] ?? null,
                        ':tiempo_conversacion' => $row['tiempo_conversacion'] ?? null,
                        ':tiempo_timbrado' => $row['tiempo_timbrado'] ?? null,
                        ':estado_final' => $row['estado_final'] ?? null,
                    ]
                );
                $counts['llamadas']++;
            } catch (\Throwable $e) {
                // Skip duplicate errors silently
            }
        }

        // --- 2. colasResumen → cdr_colas_resumen ---
        $colasResumen = $datos['colasResumen'] ?? [];
        $counts['colas'] = 0;
        foreach ($colasResumen as $row) {
            if (empty($row['numero_cola'])) continue;
            try {
                $db->execute(
                    "INSERT INTO cdr_colas_resumen
                        (tenant_id, pbx_id, numero_cola, total_llamadas, contestadas,
                         no_contestadas, ocupadas, fallidas, porcentaje_efectividad,
                         promedio_espera, promedio_duracion)
                     VALUES
                        (:tenant_id, :pbx_id, :numero_cola, :total_llamadas, :contestadas,
                         :no_contestadas, :ocupadas, :fallidas, :porcentaje_efectividad,
                         :promedio_espera, :promedio_duracion)
                     ON DUPLICATE KEY UPDATE
                        total_llamadas = VALUES(total_llamadas), contestadas = VALUES(contestadas),
                        no_contestadas = VALUES(no_contestadas), ocupadas = VALUES(ocupadas),
                        fallidas = VALUES(fallidas), porcentaje_efectividad = VALUES(porcentaje_efectividad),
                        promedio_espera = VALUES(promedio_espera), promedio_duracion = VALUES(promedio_duracion)",
                    [
                        ':tenant_id' => $tenantId,
                        ':pbx_id' => $pbxId,
                        ':numero_cola' => $row['numero_cola'],
                        ':total_llamadas' => (int) ($row['total_llamadas'] ?? 0),
                        ':contestadas' => (int) ($row['contestadas'] ?? 0),
                        ':no_contestadas' => (int) ($row['no_contestadas'] ?? 0),
                        ':ocupadas' => (int) ($row['ocupadas'] ?? 0),
                        ':fallidas' => (int) ($row['fallidas'] ?? 0),
                        ':porcentaje_efectividad' => (int) ($row['porcentaje_efectividad'] ?? 0),
                        ':promedio_espera' => $row['promedio_espera'] ?? null,
                        ':promedio_duracion' => $row['promedio_duracion'] ?? null,
                    ]
                );
                $counts['colas']++;
            } catch (\Throwable $e) {
                // Skip
            }
        }

        // --- 3. agentesResumen → cdr_agentes_resumen ---
        $agentesResumen = $datos['agentesResumen'] ?? [];
        $counts['agentes'] = 0;
        foreach ($agentesResumen as $row) {
            if (empty($row['extension_agente'])) continue;
            try {
                $db->execute(
                    "INSERT INTO cdr_agentes_resumen
                        (tenant_id, pbx_id, extension_agente, nombre_agente,
                         contestadas, no_contestadas, ocupadas, fallidas, total_llamadas,
                         porcentaje_efectividad, promedio_espera, promedio_duracion)
                     VALUES
                        (:tenant_id, :pbx_id, :extension_agente, :nombre_agente,
                         :contestadas, :no_contestadas, :ocupadas, :fallidas, :total_llamadas,
                         :porcentaje_efectividad, :promedio_espera, :promedio_duracion)
                     ON DUPLICATE KEY UPDATE
                        nombre_agente = VALUES(nombre_agente), contestadas = VALUES(contestadas),
                        no_contestadas = VALUES(no_contestadas), ocupadas = VALUES(ocupadas),
                        fallidas = VALUES(fallidas), total_llamadas = VALUES(total_llamadas),
                        porcentaje_efectividad = VALUES(porcentaje_efectividad),
                        promedio_espera = VALUES(promedio_espera), promedio_duracion = VALUES(promedio_duracion)",
                    [
                        ':tenant_id' => $tenantId,
                        ':pbx_id' => $pbxId,
                        ':extension_agente' => $row['extension_agente'],
                        ':nombre_agente' => $row['nombre_agente'] ?? null,
                        ':contestadas' => (int) ($row['contestadas'] ?? 0),
                        ':no_contestadas' => (int) ($row['no_contestadas'] ?? 0),
                        ':ocupadas' => (int) ($row['ocupadas'] ?? 0),
                        ':fallidas' => (int) ($row['fallidas'] ?? 0),
                        ':total_llamadas' => (int) ($row['total_llamadas'] ?? 0),
                        ':porcentaje_efectividad' => (int) ($row['porcentaje_efectividad'] ?? 0),
                        ':promedio_espera' => $row['promedio_espera'] ?? null,
                        ':promedio_duracion' => $row['promedio_duracion'] ?? null,
                    ]
                );
                $counts['agentes']++;
            } catch (\Throwable $e) {
                // Skip
            }
        }

        // --- 4. estadisticasColas → cdr_estadisticas_colas ---
        $estadisticasColas = $datos['estadisticasColas'] ?? [];
        $counts['estadisticas'] = 0;
        foreach ($estadisticasColas as $row) {
            if (empty($row['linkedid'])) continue;
            try {
                $db->execute(
                    "INSERT INTO cdr_estadisticas_colas
                        (tenant_id, pbx_id, linkedid, numero_cola, fecha_entrada,
                         estado_final, caller_id, agente_asignado, espera_seg, duracion_seg)
                     VALUES
                        (:tenant_id, :pbx_id, :linkedid, :numero_cola, :fecha_entrada,
                         :estado_final, :caller_id, :agente_asignado, :espera_seg, :duracion_seg)
                     ON DUPLICATE KEY UPDATE
                        numero_cola = VALUES(numero_cola), fecha_entrada = VALUES(fecha_entrada),
                        estado_final = VALUES(estado_final), caller_id = VALUES(caller_id),
                        agente_asignado = VALUES(agente_asignado), espera_seg = VALUES(espera_seg),
                        duracion_seg = VALUES(duracion_seg)",
                    [
                        ':tenant_id' => $tenantId,
                        ':pbx_id' => $pbxId,
                        ':linkedid' => $row['linkedid'],
                        ':numero_cola' => $row['numero_cola'] ?? null,
                        ':fecha_entrada' => $row['fecha_entrada'] ?? null,
                        ':estado_final' => $row['estado_final'] ?? null,
                        ':caller_id' => $row['caller_id'] ?? null,
                        ':agente_asignado' => $row['agente_asignado'] ?? null,
                        ':espera_seg' => (int) ($row['espera_seg'] ?? 0),
                        ':duracion_seg' => (int) ($row['duracion_seg'] ?? 0),
                    ]
                );
                $counts['estadisticas']++;
            } catch (\Throwable $e) {
                // Skip
            }
        }

        // --- 5. llamadasReal → cdr_llamadas_real ---
        $llamadasReal = $datos['llamadasReal'] ?? [];
        $counts['real'] = 0;
        foreach ($llamadasReal as $row) {
            if (empty($row['linkedid'])) continue;
            try {
                $db->execute(
                    "INSERT INTO cdr_llamadas_real
                        (tenant_id, pbx_id, linkedid, fecha_inicio, fecha_fin,
                         total_segmentos, duracion_total, tiempo_total_conversacion)
                     VALUES
                        (:tenant_id, :pbx_id, :linkedid, :fecha_inicio, :fecha_fin,
                         :total_segmentos, :duracion_total, :tiempo_total_conversacion)
                     ON DUPLICATE KEY UPDATE
                        fecha_inicio = VALUES(fecha_inicio), fecha_fin = VALUES(fecha_fin),
                        total_segmentos = VALUES(total_segmentos), duracion_total = VALUES(duracion_total),
                        tiempo_total_conversacion = VALUES(tiempo_total_conversacion)",
                    [
                        ':tenant_id' => $tenantId,
                        ':pbx_id' => $pbxId,
                        ':linkedid' => $row['linkedid'],
                        ':fecha_inicio' => $row['fecha_inicio'] ?? null,
                        ':fecha_fin' => $row['fecha_fin'] ?? null,
                        ':total_segmentos' => (int) ($row['total_segmentos'] ?? 0),
                        ':duracion_total' => (int) ($row['duracion_total'] ?? 0),
                        ':tiempo_total_conversacion' => (int) ($row['tiempo_total_conversacion'] ?? 0),
                    ]
                );
                $counts['real']++;
            } catch (\Throwable $e) {
                // Skip
            }
        }

        // Broadcast cdr_report event if EventBridge is ready
        $bridge = EventBridge::getInstance();
        if ($bridge->isReady()) {
            $bridge->broadcastCallEvent($tenantId, 'cdr_report', [
                'counts' => $counts,
                'pbx_id' => $pbxId,
            ]);
        }

        Response::created(['counts' => $counts], 'CDR Report procesado');
    }

    /**
     * POST /api/agent/events
     *
     * Recibe un lote de eventos AMI/CEL desde el agente.
     *
     * @description Almacena eventos en la tabla eventos y broadcastea eventos
     *              de ciclo de vida de llamada (Newchannel, Dial, Answer, Hangup)
     *              a través de EventBridge.
     *
     * @param Request $request Solicitud con X-Agent-ID header y array events en el body
     * @return void Nunca retorna — termina con Response
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

        // Broadcast eventos AMI relevantes al canal del tenant
        // Solo eventos de ciclo de vida de llamada se broadcastean (no todos los AMI)
        $bridge = EventBridge::getInstance();
        if ($bridge->isReady()) {
            $callEvents = ['Newchannel', 'Dial', 'Answer', 'Hangup', 'Newcallerid'];
            foreach ($eventList as $event) {
                if (empty($event['evento']) || !in_array($event['evento'], $callEvents)) {
                    continue;
                }

                // Mapear evento AMI a evento de broadcast
                $broadcastEvent = match ($event['evento']) {
                    'Newchannel' => 'call_started',
                    'Dial' => 'call_ringing',
                    'Answer' => 'call_answered',
                    'Hangup' => 'call_ended',
                    default => 'call_update',
                };

                $bridge->broadcastCallEvent((int) $pbx['tenant_id'], $broadcastEvent, [
                    'ami_event' => $event['evento'],
                    'callid' => $event['callid'] ?? null,
                    'datos' => $event['contenido'] ?? $event,
                    'pbx_id' => $pbx['id'],
                ]);
            }
        }

        Response::created(['inserted' => $inserted], "$inserted eventos procesados");
    }

    /**
     * POST /api/agent/metrics
     *
     * Recibe métricas de salud del servidor PBX.
     *
     * @description Registra métricas como evento de sistema, verifica alertas
     *              de CPU/RAM y broadcastea métricas de salud a suscriptores.
     *
     * @param Request $request Solicitud con X-Agent-ID header y métricas en el body
     * @return void Nunca retorna — termina con Response
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

        // Broadcast métricas de salud del PBX
        $bridge = EventBridge::getInstance();
        if ($bridge->isReady()) {
            $bridge->broadcastPbxHealth((int) $pbx['id'], [
                'cpu_usage' => $data['cpu_usage'] ?? null,
                'memory_usage' => $data['memory_usage'] ?? null,
                'disk_usage' => $data['disk_usage'] ?? null,
                'active_channels' => $data['active_channels'] ?? null,
                'sip_peers_online' => $data['sip_peers_online'] ?? null,
                'uptime' => $data['uptime'] ?? null,
            ]);
        }

        Response::ok(['received' => true], 'Metricas recibidas');
    }

    /**
     * Autentica agente usando UUID único (X-Agent-ID header).
     *
     * @description Valida el header X-Agent-ID, busca el PBX por agente_id
     *              (columna unique en tabla pbx) y verifica que esté activo.
     *              Compatible con agente Python y agente Spring/Java.
     *
     * @param Request $request Solicitud con header X-Agent-ID
     * @return ?array Datos del PBX autenticado o null si falla la autenticación
     */
    private function authenticateAgent(Request $request): ?array
    {
        $agenteId = $request->header('X-Agent-ID');
        if (!$agenteId) {
            Response::unauthorized('Identificador de agente requerido (X-Agent-ID)');
            return null;
        }

        $pbx = Pbx::findByAgenteId($agenteId);
        if (!$pbx) {
            Response::unauthorized('Agente no registrado: ' . htmlspecialchars($agenteId, ENT_QUOTES));
            return null;
        }

        if (!$pbx['activo']) {
            Response::forbidden('Agente desactivado');
            return null;
        }

        return $pbx;
    }

    /**
     * Valida la integridad de un registro CDR.
     *
     * @description Verifica que duracion y billable_seconds sean numéricos no negativos,
     *              que fin_llamada no sea anterior a inicio_llamada, que el estado sea
     *              válido según Asterisk y que el callid no contenga caracteres peligrosos.
     *
     * @param array $cdr Registro CDR a validar
     * @param int $index Índice del registro en el lote (para mensajes de error)
     * @return bool|string true si es válido, o string con mensaje de error
     */
    private function validarCdr(array $cdr, int $index): bool|string
    {
        // Validar que duracion sea numérico y no negativo
        if (isset($cdr['duracion']) && (!is_numeric($cdr['duracion']) || $cdr['duracion'] < 0)) {
            return "Registro $index: duracion debe ser numérico y >= 0";
        }

        // Validar que billable_seconds sea numérico y no negativo
        if (isset($cdr['billable_seconds']) && (!is_numeric($cdr['billable_seconds']) || $cdr['billable_seconds'] < 0)) {
            return "Registro $index: billable_seconds debe ser numérico y >= 0";
        }

        // Validar coherencia de fechas: fin_llamada >= inicio_llamada
        if (!empty($cdr['fin_llamada']) && !empty($cdr['inicio_llamada'])) {
            try {
                $inicio = new \DateTime($cdr['inicio_llamada']);
                $fin = new \DateTime($cdr['fin_llamada']);
                if ($fin < $inicio) {
                    return "Registro $index: fin_llamada no puede ser anterior a inicio_llamada";
                }
            } catch (\Exception $e) {
                return "Registro $index: formato de fecha inválido";
            }
        }

        // Validar estado contra valores permitidos de Asterisk
        $estadosValidos = ['ANSWERED', 'BUSY', 'NO ANSWER', 'FAILED', 'CANCEL', 'CONGESTION', 'CHANUNAVAIL'];
        if (!empty($cdr['estado']) && !in_array(strtoupper($cdr['estado']), $estadosValidos)) {
            return "Registro $index: estado '" . htmlspecialchars($cdr['estado'], ENT_QUOTES) . "' no es válido";
        }

        // Validar formato básico de callid (Asterisk usa formato específico)
        if (!empty($cdr['callid'])) {
            // CallID típico: uniqueid.ocean o similar - al menos evitar caracteres peligrosos
            if (preg_match('/[<>"\';]/', $cdr['callid'])) {
                return "Registro $index: callid contiene caracteres inválidos";
            }
        }

        return true;
    }

    /**
     * Verifica alertas de llamadas perdidas.
     *
     * @description Consulta reglas activas de tipo LLAMADAS_PERDIDAS y evalúa
     *              si el total de llamadas perdidas en la última hora supera el umbral.
     *
     * @param int $tenantId ID del tenant
     * @param int $pbxId ID del servidor PBX
     * @return void
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
     * Verifica alertas de CPU/RAM.
     *
     * @description Consulta reglas activas de tipo CPU o RAM y evalúa si los
     *              valores actuales superan los umbrales configurados.
     *
     * @param int $tenantId ID del tenant
     * @param array $metrics Métricas recibidas del agente
     * @return void
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
     * Evalúa una condición de alerta contra un valor y umbral.
     *
     * @description Compara el valor actual con el umbral usando la condición
     *              especificada (MAYOR, MENOR o IGUAL).
     *
     * @param float $value Valor actual a evaluar
     * @param string $condition Condición de comparación (MAYOR, MENOR, IGUAL)
     * @param float $threshold Umbral de comparación
     * @return bool true si la condición se cumple, false de lo contrario
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
     * Dispara una alerta y la registra en el historial.
     *
     * @description Genera un mensaje descriptivo con el nombre de la regla, valor actual,
     *              condición y umbral. Determina el nivel (CRITICAL si el valor supera
     *              el umbral en un 50%, WARNING de lo contrario).
     *
     * @param array $rule Regla de alerta que se está disparando
     * @param float $actualValue Valor actual que provocó la alerta
     * @return void
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
