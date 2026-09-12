<?php
declare(strict_types=1);

namespace CallMetrics\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use CallMetrics\Models\Pbx;

/**
 * Servidor WebSocket para actualizaciones en tiempo real.
 *
 * Tipos de conexion:
 *   - Agente collector: se identifica con X-Agent-ID en el handshake.
 *     Puede ENVIAR eventos al servidor (procesa incoming events).
 *   - Frontend/cliente: se identifica con JWT o token de sesion.
 *     Solo SUSCRIBE y RECIBE broadcasts.
 *
 * Canales de suscripcion:
 *   - tenant_{id}: Actualizaciones generales del tenant
 *   - pbx_{id}: Metricas y estado de un PBX especifico
 *   - colas_{id}: Estado de una cola de atencion
 *   - dashboard: KPIs y metricas del dashboard
 *
 * Eventos entrantes del agente (via WS):
 *   - agent_event: Evento AMI/CEL normalizado
 *   - agent_heartbeat: Heartbeat del agente
 *   - agent_cdr: Registro CDR
 *   - agent_metric: Metrica de salud
 */
class Server implements MessageComponentInterface
{
    /** @var array<string, array<ConnectionInterface>> Clientes suscritos por canal */
    private array $channels = [];

    /** @var array<int, array<string, true>> Canales suscritos por conexion */
    private array $subscriptions = [];

    /**
     * Tipo de conexion:
     *   'agent'   — agente collector (puede enviar eventos)
     *   'client'  — frontend/dashboard (solo recibe broadcasts)
     */
    private array $connectionType = [];

    /**
     * ID del agente para conexiones de tipo agent.
     * key = resourceId, value = agente_id
     */
    private array $agentIds = [];

    /**
     * Tenant ID para conexiones de tipo client.
     * key = resourceId, value = tenant_id
     */
    private array $tenantIds = [];

    /**
     * Manejar nueva conexion WebSocket.
     *
     * Detecta si es un agente (X-Agent-ID header) o un frontend (JWT).
     * Los agentes pueden enviar eventos; los frontends solo reciben.
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $id = (int) $conn->resourceId;
        $this->subscriptions[$id] = [];

        // Detectar tipo de conexion por headers del handshake HTTP
        $headers = $conn->httpRequest->getHeaders();
        $agenteId = $headers['X-Agent-ID'][0] ?? null;

        if ($agenteId) {
            // Conexion de agente collector — verificar que esté registrado
            $pbx = Pbx::findByAgenteId($agenteId);
            if (!$pbx || !$pbx['activo']) {
                $conn->send(json_encode([
                    'error' => 'Agente no registrado o desactivado',
                    'code' => 'AUTH_FAILED'
                ]));
                $conn->close();
                echo "WS Rechazado: agente_id=$agenteId no valido\n";
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
            // Conexion de frontend/cliente — por ahora se acepta sin JWT
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
     * Agentes pueden enviar: agent_event, agent_heartbeat, agent_cdr, agent_metric
     * Clientes pueden enviar: subscribe, unsubscribe, ping
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        if (!$data) {
            $from->send(json_encode(['error' => 'Mensaje invalido']));
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

        // --- Clientes: suscripcion y control ---
        $channel = $data['channel'] ?? '';

        match ($action) {
            'subscribe' => $this->subscribe($from, $channel),
            'unsubscribe' => $this->unsubscribe($from, $channel),
            'ping' => $from->send(json_encode(['action' => 'pong', 'time' => time()])),
            default => $from->send(json_encode(['error' => 'Accion desconocida']))
        };
    }

    /**
     * Procesar mensaje de un agente collector.
     *
     * El agente envía eventos normalizados por WS en vez de HTTP.
     * El servidor los procesa: inserta en DB + broadcast a frontends.
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
     * Inserta en DB + broadcast al tenant.
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
     * Desconexion de un cliente — limpiar suscripciones.
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $id = (int) $conn->resourceId;
        unset($this->subscriptions[$id]);
        unset($this->connectionType[$id]);
        unset($this->agentIds[$id]);
        unset($this->tenantIds[$id]);
        echo "Desconexion: {$conn->resourceId}\n";
    }

    /**
     * Error en la conexion.
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Suscribir un cliente a un canal.
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
     * Enviar actualizacion de dashboard a todos los tenants activos.
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
     * Enviar evento de llamada a un tenant especifico.
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
     * Enviar actualizacion de PBX a suscritos.
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
     * Enviar actualizacion de cola.
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
