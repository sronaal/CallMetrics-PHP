<?php
declare(strict_types=1);

/**
 * Servidor WebSocket para actualizaciones en tiempo real de CallMetric Pro.
 *
 * Implementa MessageComponentInterface de Ratchet para manejar conexiones
 * WebSocket bidireccionales. Soporta dos tipos de cliente:
 *
 * Tipos de conexión:
 *   - Agente collector: se identifica con X-Agent-ID en el handshake.
 *     Puede ENVIAR eventos al servidor (procesa incoming events).
 *   - Frontend/cliente: se identifica con JWT o token de sesión.
 *     Solo SUSCRIBE y RECIBE broadcasts.
 *
 * Canales de suscripción:
 *   - tenant_{id}: Actualizaciones generales del tenant
 *   - pbx_{id}: Métricas y estado de un PBX específico
 *   - colas_{id}: Estado de una cola de atención
 *   - dashboard: KPIs y métricas del dashboard
 *   - agent_{id}: Canal privado del agente (para ACKs)
 *
 * Eventos entrantes del agente (vía WS):
 *   - agent_event: Evento AMI/CEL normalizado
 *   - agent_heartbeat: Heartbeat del agente
 *   - agent_cdr: Registro CDR
 *   - agent_metric: Métrica de salud
 *
 * @package CallMetrics\WebSocket
 */

namespace CallMetrics\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use CallMetrics\Models\Pbx;

/**
 * Servidor WebSocket para actualizaciones en tiempo real.
 *
 * Tipos de conexión:
 *   - Agente collector: se identifica con X-Agent-ID en el handshake.
 *     Puede ENVIAR eventos al servidor (procesa incoming events).
 *   - Frontend/cliente: se identifica con JWT o token de sesión.
 *     Solo SUSCRIBE y RECIBE broadcasts.
 *
 * Canales de suscripción:
 *   - tenant_{id}: Actualizaciones generales del tenant
 *   - pbx_{id}: Métricas y estado de un PBX específico
 *   - colas_{id}: Estado de una cola de atención
 *   - dashboard: KPIs y métricas del dashboard
 *
 * Eventos entrantes del agente (vía WS):
 *   - agent_event: Evento AMI/CEL normalizado
 *   - agent_heartbeat: Heartbeat del agente
 *   - agent_cdr: Registro CDR
 *   - agent_metric: Métrica de salud
 */
class Server implements MessageComponentInterface
{
    /**
     * Clientes suscritos por canal.
     *
     * @var array<string, array<ConnectionInterface>>
     */
    private array $channels = [];

    /**
     * Canales suscritos por conexión (inverso de $channels).
     *
     * @var array<int, array<string, true>>
     */
    private array $subscriptions = [];

    /**
     * Tipo de conexión por resourceId.
     *
     * 'agent'  — agente collector (puede enviar eventos)
     * 'client' — frontend/dashboard (solo recibe broadcasts)
     *
     * @var array<int, string>
     */
    private array $connectionType = [];

    /**
     * ID del agente para conexiones de tipo agent.
     *
     * key = resourceId, value = agente_id
     *
     * @var array<int, string>
     */
    private array $agentIds = [];

    /**
     * Tenant ID para conexiones de tipo client.
     *
     * key = resourceId, value = tenant_id
     *
     * @var array<int, int>
     */
    private array $tenantIds = [];

    /**
     * Manejar nueva conexión WebSocket.
     *
     * @description Detecta si es un agente (X-Agent-ID header) o un frontend (JWT).
     *              Los agentes pueden enviar eventos; los frontends solo reciben.
     *              Valida que el agente esté registrado y activo en la DB.
     *
     * @param ConnectionInterface $conn Conexión entrante de Ratchet.
     *
     * @return void
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $id = (int) $conn->resourceId;
        $this->subscriptions[$id] = [];

        // Detectar tipo de conexión por headers del handshake HTTP
        $headers = $conn->httpRequest->getHeaders();
        $agenteId = $headers['X-Agent-ID'][0] ?? null;

        if ($agenteId) {
            // Conexión de agente collector — verificar que esté registrado
            $pbx = Pbx::findByAgenteId($agenteId);
            if (!$pbx || !$pbx['activo']) {
                $conn->send(json_encode([
                    'error' => 'Agente no registrado o desactivado',
                    'code' => 'AUTH_FAILED'
                ]));
                $conn->close();
                echo "WS Rechazado: agente_id=$agenteId no válido\n";
                return;
            }

            $this->connectionType[$id] = 'agent';
            $this->agentIds[$id] = $agenteId;

            // Auto-suscribir al canal del PBX para recibir acks
            $this->subscribe($conn, "agent_{$agenteId}");

            $conn->send(json_encode([
                'action' => 'authenticated',
                'type' => 'agent',
                'agente_id' => $agenteId,
                'pbx_id' => $pbx['id'],
            ]));

            echo "WS Agente conectado: $agenteId (PBX: {$pbx['id']})\n";

        } else {
            // Conexión de frontend/cliente — por ahora se acepta sin JWT
            // TODO: validar JWT del query string o header
            $this->connectionType[$id] = 'client';

            $conn->send(json_encode([
                'action' => 'authenticated',
                'type' => 'client',
            ]));

            echo "WS Cliente conectado: resourceId=$id\n";
        }
    }

    /**
     * Mensaje entrante desde un cliente.
     *
     * @description Procesa mensajes JSON de clientes conectados. Los agentes
     *              pueden enviar eventos (call, queue, cdr, heartbeat, sip, sistema).
     *              Los clientes pueden suscribirse, desuscribirse o hacer ping.
     *
     * @param ConnectionInterface $from Conexión que envió el mensaje.
     * @param string              $msg  Mensaje JSON codificado.
     *
     * @return void
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        if (!$data) {
            $from->send(json_encode(['error' => 'Mensaje inválido']));
            return;
        }

        $id = (int) $from->resourceId;
        $type = $this->connectionType[$id] ?? 'client';
        $action = $data['action'] ?? '';

        // --- Agentes: procesar eventos entrantes ---
        if ($type === 'agent') {
            $this->handleAgentMessage($from, $data);
            return;
        }

        // --- Clientes: suscripción y control ---
        $channel = $data['channel'] ?? '';

        match ($action) {
            'subscribe' => $this->subscribe($from, $channel),
            'unsubscribe' => $this->unsubscribe($from, $channel),
            'ping' => $from->send(json_encode(['action' => 'pong', 'time' => time()])),
            default => $from->send(json_encode(['error' => 'Acción desconocida']))
        };
    }

    /**
     * Procesar mensaje de un agente collector.
     *
     * @description El agente envía eventos normalizados por WS en vez de HTTP.
     *              El servidor los procesa: inserta en DB + broadcast a frontends.
     *              Responde con un ACK a cada mensaje procesado.
     *
     * @param ConnectionInterface $from Conexión del agente.
     * @param array               $data Datos del mensaje (debe contener 'tipo').
     *
     * @return void
     */
    private function handleAgentMessage(ConnectionInterface $from, array $data): void
    {
        $id = (int) $from->resourceId;
        $agenteId = $this->agentIds[$id] ?? null;

        if (!$agenteId) {
            $from->send(json_encode(['error' => 'Agente no identificado']));
            return;
        }

        $tipo = $data['tipo'] ?? '';

        switch ($tipo) {
            case 'evento_llamada':
            case 'llamada_completa':
                $this->processAgentCallEvent($agenteId, $data);
                break;

            case 'evento_queue':
                $this->processAgentQueueEvent($agenteId, $data);
                break;

            case 'cdr_completo':
                $this->processAgentCdrEvent($agenteId, $data);
                break;

            case 'heartbeat':
                $this->processAgentHeartbeat($agenteId, $data);
                break;

            case 'evento_sip':
            case 'sip_log':
            case 'sistema':
                // Eventos de sistema: solo almacenar, no broadcast
                $this->storeAgentEvent($agenteId, $data);
                break;

            default:
                // Cualquier otro tipo: almacenar genérico
                $this->storeAgentEvent($agenteId, $data);
                break;
        }

        // Ack al agente
        $from->send(json_encode([
            'action' => 'ack',
            'tipo' => $tipo,
            'timestamp' => time(),
        ]));
    }

    /**
     * Procesar evento de llamada del agente.
     *
     * @description Almacena el evento en DB y determina el tipo de broadcast
     *              basado en el estado de la llamada (ringing, answered, ended, update).
     *              Emite el evento al canal del tenant.
     *
     * @param string $agenteId Identificador del agente collector.
     * @param array  $data     Datos del evento (debe contener callid y/o datos con estado).
     *
     * @return void
     */
    private function processAgentCallEvent(string $agenteId, array $data): void
    {
        $pbx = Pbx::findByAgenteId($agenteId);
        if (!$pbx) return;

        // Almacenar en DB
        $this->storeAgentEvent($agenteId, $data);

        // Determinar evento de broadcast
        $datos = $data['datos'] ?? $data;
        $estado = $datos['estado'] ?? $datos['state'] ?? '';
        $broadcastEvent = match (true) {
            str_contains(strtolower($estado), 'ring') => 'call_ringing',
            str_contains(strtolower($estado), 'answer') => 'call_answered',
            str_contains(strtolower($estado), 'hangup') || str_contains(strtolower($estado), 'end') => 'call_ended',
            default => 'call_update',
        };

        // Broadcast al canal del tenant
        $this->broadcastCallEvent((int) $pbx['tenant_id'], $broadcastEvent, [
            'callid' => $data['callid'] ?? $datos['id_unico'] ?? null,
            'datos' => $datos,
            'pbx_id' => $pbx['id'],
        ]);
    }

    /**
     * Procesar evento de cola del agente.
     *
     * @description Almacena el evento, emite broadcast al canal de la cola específica
     *              (si se proporciona cola_id) y también al canal del tenant.
     *
     * @param string $agenteId Identificador del agente collector.
     * @param array  $data     Datos del evento de cola.
     *
     * @return void
     */
    private function processAgentQueueEvent(string $agenteId, array $data): void
    {
        $pbx = Pbx::findByAgenteId($agenteId);
        if (!$pbx) return;

        $this->storeAgentEvent($agenteId, $data);

        // Broadcast a canal de colas si hay cola_id
        $datos = $data['datos'] ?? $data;
        $queueId = $datos['cola_id'] ?? $datos['queue'] ?? null;
        if ($queueId) {
            $this->broadcastQueueUpdate((int) $queueId, [
                'evento' => $data['evento'] ?? $data['tipo'] ?? null,
                'datos' => $datos,
                'pbx_id' => $pbx['id'],
            ]);
        }

        // También broadcast al tenant
        $this->broadcastCallEvent((int) $pbx['tenant_id'], 'queue_update', [
            'evento' => $data['evento'] ?? $data['tipo'] ?? null,
            'datos' => $datos,
            'pbx_id' => $pbx['id'],
        ]);
    }

    /**
     * Procesar CDR del agente.
     *
     * @description Almacena el CDR completo y emite broadcast de call_ended al tenant.
     *
     * @param string $agenteId Identificador del agente collector.
     * @param array  $data     Datos del CDR (callid, duración, estado final, etc.).
     *
     * @return void
     */
    private function processAgentCdrEvent(string $agenteId, array $data): void
    {
        $pbx = Pbx::findByAgenteId($agenteId);
        if (!$pbx) return;

        $this->storeAgentEvent($agenteId, $data);

        $this->broadcastCallEvent((int) $pbx['tenant_id'], 'call_ended', [
            'callid' => $data['callid'] ?? null,
            'datos' => $data['datos'] ?? $data,
            'pbx_id' => $pbx['id'],
        ]);
    }

    /**
     * Procesar heartbeat del agente.
     *
     * @description Actualiza el campo ultimo_heartbeat en la tabla pbx y emite
     *              broadcast de salud al canal del PBX con las métricas reportadas.
     *
     * @param string $agenteId Identificador del agente collector.
     * @param array  $data     Datos del heartbeat (estado, uptime, métricas, sistema).
     *
     * @return void
     */
    private function processAgentHeartbeat(string $agenteId, array $data): void
    {
        $pbx = Pbx::findByAgenteId($agenteId);
        if (!$pbx) return;

        // Actualizar ultimo_heartbeat en DB
        $db = \CallMetrics\Core\Database::getInstance();
        $db->execute(
            "UPDATE pbx SET ultimo_heartbeat = NOW(), updated_at = NOW() WHERE id = :id",
            [':id' => $pbx['id']]
        );

        // Broadcast health al canal del PBX
        $this->broadcastPbxHealth((int) $pbx['id'], [
            'estado' => $data['estado'] ?? 'ONLINE',
            'uptime' => $data['tiempo_activo'] ?? null,
            'conexion_ami' => $data['conexion_ami'] ?? null,
            'metricas' => $data['metricas'] ?? null,
            'sistema' => $data['sistema'] ?? null,
        ]);
    }

    /**
     * Almacenar evento genérico del agente en la tabla eventos.
     *
     * @description Inserta el evento completo (serializado como JSON) en la tabla
     *              eventos con el tenant_id, pbx_id, tipo, evento y callid correspondientes.
     *
     * @param string $agenteId Identificador del agente collector.
     * @param array  $data     Datos completos del evento a almacenar.
     *
     * @return void
     */
    private function storeAgentEvent(string $agenteId, array $data): void
    {
        $pbx = Pbx::findByAgenteId($agenteId);
        if (!$pbx) return;

        $db = \CallMetrics\Core\Database::getInstance();
        $db->insert(
            "INSERT INTO eventos (tenant_id, pbx_id, tipo, evento, callid, contenido)
             VALUES (:tenant_id, :pbx_id, :tipo, :evento, :callid, :contenido)",
            [
                ':tenant_id' => $pbx['tenant_id'],
                ':pbx_id' => $pbx['id'],
                ':tipo' => strtoupper($data['fuente'] ?? $data['tipo'] ?? 'AMI'),
                ':evento' => $data['evento'] ?? $data['tipo'] ?? 'unknown',
                ':callid' => $data['callid'] ?? $data['datos']['id_unico'] ?? null,
                ':contenido' => json_encode($data),
            ]
        );
    }

    /**
     * Desconexión de un cliente — limpiar suscripciones.
     *
     * @description Elimina todas las suscripciones del cliente desconectado de
     *              los arrays internos para evitar memory leaks y mensajes a
     *              conexiones cerradas.
     *
     * @param ConnectionInterface $conn Conexión que se cerró.
     *
     * @return void
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $id = (int) $conn->resourceId;
        unset($this->subscriptions[$id]);
        unset($this->connectionType[$id]);
        unset($this->agentIds[$id]);
        unset($this->tenantIds[$id]);
        echo "Desconexión: {$conn->resourceId}\n";
    }

    /**
     * Error en la conexión.
     *
     * @description Registra el error en salida estándar y cierra la conexión
     *              para liberar recursos.
     *
     * @param ConnectionInterface $conn Conexión con error.
     * @param \Exception          $e    Excepción capturada.
     *
     * @return void
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Suscribir un cliente a un canal.
     *
     * @description Registra la conexión en el canal especificado y notifica
     *              al cliente con un mensaje 'subscribed'.
     *
     * @param ConnectionInterface $conn    Conexión del cliente.
     * @param string              $channel Nombre del canal (ej: "tenant_5", "dashboard").
     *
     * @return void
     */
    private function subscribe(ConnectionInterface $conn, string $channel): void
    {
        $id = (int) $conn->resourceId;
        $this->channels[$channel][$id] = $conn;
        $this->subscriptions[$id][$channel] = true;

        $conn->send(json_encode([
            'action' => 'subscribed',
            'channel' => $channel
        ]));
    }

    /**
     * Desuscribir un cliente de un canal.
     *
     * @description Elimina la conexión del canal y notifica al cliente con
     *              un mensaje 'unsubscribed'.
     *
     * @param ConnectionInterface $conn    Conexión del cliente.
     * @param string              $channel Nombre del canal.
     *
     * @return void
     */
    private function unsubscribe(ConnectionInterface $conn, string $channel): void
    {
        $id = (int) $conn->resourceId;
        unset($this->channels[$channel][$id]);
        unset($this->subscriptions[$id][$channel]);

        $conn->send(json_encode([
            'action' => 'unsubscribed',
            'channel' => $channel
        ]));
    }

    /**
     * Enviar mensaje a todos los suscritos de un canal.
     *
     * @description Serializa $data como JSON y lo envía a cada conexión
     *              registrada en el canal. No retorna error si el canal no existe.
     *
     * @param string $channel Nombre del canal destino.
     * @param array  $data    Datos a enviar.
     *
     * @return void
     */
    public function broadcastToChannel(string $channel, array $data): void
    {
        if (!isset($this->channels[$channel])) return;

        $message = json_encode($data);
        foreach ($this->channels[$channel] as $conn) {
            $conn->send($message);
        }
    }

    /**
     * Enviar actualización de dashboard a todos los tenants activos.
     *
     * @description Emite un evento 'dashboard_update' al canal "dashboard" con
     *              los KPIs proporcionados y timestamp actual.
     *
     * @param array $data KPIs globales: llamadas totales, tasa de answer, SLA, etc.
     *
     * @return void
     */
    public function broadcastDashboard(array $data): void
    {
        $this->broadcastToChannel('dashboard', [
            'type' => 'dashboard_update',
            'data' => $data,
            'timestamp' => time()
        ]);
    }

    /**
     * Enviar evento de llamada a un tenant específico.
     *
     * @description Emite un evento 'call_event' al canal "tenant_{id}" con el
     *              tipo de evento y datos de la llamada.
     *
     * @param int    $tenantId Identificador del tenant.
     * @param string $event    Tipo de evento: call_started, call_ended, call_ringing, call_answered.
     * @param array  $data     Datos de la llamada.
     *
     * @return void
     */
    public function broadcastCallEvent(int $tenantId, string $event, array $data): void
    {
        $this->broadcastToChannel("tenant_{$tenantId}", [
            'type' => 'call_event',
            'event' => $event,
            'data' => $data,
            'timestamp' => time()
        ]);
    }

    /**
     * Enviar actualización de PBX a suscritos.
     *
     * @description Emite un evento 'pbx_health' al canal "pbx_{id}" con las
     *              métricas de salud del PBX.
     *
     * @param int   $pbxId   Identificador del PBX.
     * @param array $metrics Métricas de salud: estado, uptime, CPU, memoria, etc.
     *
     * @return void
     */
    public function broadcastPbxHealth(int $pbxId, array $metrics): void
    {
        $this->broadcastToChannel("pbx_{$pbxId}", [
            'type' => 'pbx_health',
            'data' => $metrics,
            'timestamp' => time()
        ]);
    }

    /**
     * Enviar actualización de cola.
     *
     * @description Emite un evento 'queue_update' al canal "colas_{id}" con el
     *              estado actual de la cola de atención.
     *
     * @param int   $queueId Identificador de la cola.
     * @param array $data    Datos de la cola: evento, llamadas en espera, agentes, etc.
     *
     * @return void
     */
    public function broadcastQueueUpdate(int $queueId, array $data): void
    {
        $this->broadcastToChannel("colas_{$queueId}", [
            'type' => 'queue_update',
            'data' => $data,
            'timestamp' => time()
        ]);
    }
}
