<?php
declare(strict_types=1);

namespace CallMetrics\WebSocket;

/**
 * Puente entre la ingesta HTTP del backend y el servidor WebSocket.
 *
 * Patrón Singleton: guarda la referencia del Server.php cuando arranca
 * y permite que los controladores hagan broadcast después de procesar eventos.
 *
 * Uso:
 *   EventBridge::init($server);                    // En websocket-server.php
 *   EventBridge::getInstance()->broadcastCallEvent(...); // En controladores
 */
class EventBridge
{
    private static ?EventBridge $instance = null;
    private ?Server $server = null;

    private function __construct() {}

    /**
     * Inicializar el bridge con la referencia del Server WebSocket.
     * Llamar UNA VEZ en websocket-server.php después de crear el Server.
     */
    public static function init(Server $server): void
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        self::$instance->server = $server;
    }

    /**
     * Obtener la instancia del bridge.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Verificar si el bridge está inicializado con un Server válido.
     */
    public function isReady(): bool
    {
        return $this->server !== null;
    }

    // ----------------------------------------------------------------
    // Broadcast methods — delegan al Server
    // ----------------------------------------------------------------

    /**
     * Broadcast de evento de llamada a un tenant específico.
     *
     * @param int    $tenantId  ID del tenant
     * @param string $event     Tipo: call_started, call_ended, call_ringing, call_answered
     * @param array  $data      Datos de la llamada
     */
    public function broadcastCallEvent(int $tenantId, string $event, array $data): void
    {
        if (!$this->isReady()) return;
        $this->server->broadcastCallEvent($tenantId, $event, $data);
    }

    /**
     * Broadcast de métricas de salud de un PBX.
     *
     * @param int   $pbxId   ID del PBX
     * @param array $metrics Métricas: cpu, memoria, disco, canales activos, etc.
     */
    public function broadcastPbxHealth(int $pbxId, array $metrics): void
    {
        if (!$this->isReady()) return;
        $this->server->broadcastPbxHealth($pbxId, $metrics);
    }

    /**
     * Broadcast de actualización de cola.
     *
     * @param int   $queueId ID de la cola
     * @param array $data    Datos de la cola
     */
    public function broadcastQueueUpdate(int $queueId, array $data): void
    {
        if (!$this->isReady()) return;
        $this->server->broadcastQueueUpdate($queueId, $data);
    }

    /**
     * Broadcast de KPIs del dashboard a todos los clientes.
     *
     * @param array $data KPIs globales
     */
    public function broadcastDashboard(array $data): void
    {
        if (!$this->isReady()) return;
        $this->server->broadcastDashboard($data);
    }

    /**
     * Enviar mensaje raw a un canal específico.
     *
     * @param string $channel Nombre del canal
     * @param array  $data    Datos a enviar
     */
    public function broadcastToChannel(string $channel, array $data): void
    {
        if (!$this->isReady()) return;
        $this->server->broadcastToChannel($channel, $data);
    }
}
