<?php
declare(strict_types=1);

namespace CallMetrics\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * Servidor WebSocket para actualizaciones en tiempo real.
 *
 * Canales:
 *   - tenant_{id}: Actualizaciones generales del tenant
 *   - pbx_{id}: Metricas y estado de un PBX especifico
 *   - colas_{id}: Estado de una cola de atencion
 *   - dashboard: KPIs y metricas del dashboard
 *
 * Eventos:
 *   - call_started: Llamada iniciada
 *   - call_ended: Llamada finalizada
 *   - agent_status: Cambio de estado de agente
 *   - queue_update: Actualizacion de cola
 *   - pbx_health: Metricas de salud del PBX
 *   - alert: Alerta disparada
 */
class Server implements MessageComponentInterface
{
    /** @var array<string, array<ConnectionInterface>> Clientes suscritos por canal */
    private array $channels = [];

    /** @var array<int, array<string, true>> Canales suscritos por conexion */
    private array $subscriptions = [];

    /**
     * Manejar nueva conexion WebSocket.
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->subscriptions[(int) $conn->resourceId] = [];
        echo "Nueva conexion: {$conn->resourceId}\n";
    }

    /**
     * Mensaje entrante desde un cliente.
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        if (!$data) {
            $from->send(json_encode(['error' => 'Mensaje invalido']));
            return;
        }

        $action = $data['action'] ?? '';
        $channel = $data['channel'] ?? '';

        match ($action) {
            'subscribe' => $this->subscribe($from, $channel),
            'unsubscribe' => $this->unsubscribe($from, $channel),
            'ping' => $from->send(json_encode(['action' => 'pong', 'time' => time()])),
            default => $from->send(json_encode(['error' => 'Accion desconocida']))
        };
    }

    /**
     * Desconexion de un cliente — limpiar suscripciones.
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $id = (int) $conn->resourceId;
        unset($this->subscriptions[$id]);
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
