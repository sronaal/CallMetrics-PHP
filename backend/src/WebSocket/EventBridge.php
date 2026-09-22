<?php
declare(strict_types=1);

/**
 * Puente entre la ingesta HTTP del backend y el servidor WebSocket.
 *
 * Patrón Singleton: guarda la referencia del Server.php cuando arranca
 * y permite que los controladores hagan broadcast después de procesar eventos.
 *
 * Este桥接 permite desacoplar los controladores HTTP del servidor WebSocket
 *运行时. Los controladores nunca importan directamente Server, solo usan
 * EventBridge para enviar actualizaciones en tiempo real.
 *
 * Uso:
 *   EventBridge::init($server);                         // En websocket-server.php
 *   EventBridge::getInstance()->broadcastCallEvent(...); // En controladores
 *
 * @package CallMetrics\WebSocket
 */

namespace CallMetrics\WebSocket;

/**
 * Puente entre la ingesta HTTP del backend y el servidor WebSocket.
 *
 * Patrón Singleton: guarda la referencia del Server.php cuando arranca
 * y permite que los controladores hagan broadcast después de procesar eventos.
 */
class EventBridge
{
    /**
     * Instancia Singleton del puente.
     *
     * @var self|null
     */
    private static ?EventBridge $instance = null;

    /**
     * Referencia al servidor WebSocket Ratchet.
     *
     * @var Server|null
     */
    private ?Server $server = null;

    /**
     * Constructor privado — patrón Singleton.
     *
     * @description Impide instanciación externa. Usar init() o getInstance().
     */
    private function __construct() {}

    /**
     * Inicializar el bridge con la referencia del Server WebSocket.
     *
     * @description Debe llamarse UNA VEZ en websocket-server.php después de
     *              crear el objeto Server de Ratchet. Establece la instancia
     *              Singleton y vincula el servidor WebSocket.
     *
     * @param Server $server Instancia del servidor WebSocket de Ratchet.
     *
     * @return void
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
     *
     * @description Retorna la instancia Singleton. Si no existe, crea una nueva
     *              (sin servidor vinculado — verificar con isReady() antes de usar).
     *
     * @return self Instancia del puente EventBridge.
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
     *
     * @description Los métodos de broadcast verifican esto internamente y retornan
     *              silenciosamente si el servidor no está listo.
     *
     * @return bool true si el servidor WebSocket está vinculado y listo.
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
     * @description Envía el evento a todos los clientes suscritos al canal
     *              "tenant_{id}". Tipos comunes: call_started, call_ended,
     *              call_ringing, call_answered, call_update.
     *
     * @param int    $tenantId Identificador del tenant destino.
     * @param string $event    Tipo de evento: call_started, call_ended, call_ringing, call_answered.
     * @param array  $data     Datos de la llamada (callid, estado, origen, destino, etc.).
     *
     * @return void
     */
    public function broadcastCallEvent(int $tenantId, string $event, array $data): void
    {
        if (!$this->isReady()) return;
        $this->server->broadcastCallEvent($tenantId, $event, $data);
    }

    /**
     * Broadcast de métricas de salud de un PBX.
     *
     * @description Envía métricas de sistema (CPU, memoria, disco, canales activos)
     *              a todos los clientes suscritos al canal "pbx_{id}".
     *
     * @param int   $pbxId   Identificador del PBX.
     * @param array $metrics Métricas: cpu, memoria, disco, canales activos, uptime, etc.
     *
     * @return void
     */
    public function broadcastPbxHealth(int $pbxId, array $metrics): void
    {
        if (!$this->isReady()) return;
        $this->server->broadcastPbxHealth($pbxId, $metrics);
    }

    /**
     * Broadcast de actualización de cola.
     *
     * @description Envía el estado actual de una cola de atención (llamadas en
     *              espera, agentes disponibles, etc.) a los suscritos al canal
     *              "colas_{id}".
     *
     * @param int   $queueId Identificador de la cola.
     * @param array $data    Datos de la cola (evento, llamadas en espera, agentes, etc.).
     *
     * @return void
     */
    public function broadcastQueueUpdate(int $queueId, array $data): void
    {
        if (!$this->isReady()) return;
        $this->server->broadcastQueueUpdate($queueId, $data);
    }

    /**
     * Broadcast de KPIs del dashboard a todos los clientes.
     *
     * @description Envía las métricas globales del dashboard (KPIs, resúmenes)
     *              a todos los clientes suscritos al canal "dashboard".
     *
     * @param array $data KPIs globales: llamadas totales, tasa de answer, SLA, etc.
     *
     * @return void
     */
    public function broadcastDashboard(array $data): void
    {
        if (!$this->isReady()) return;
        $this->server->broadcastDashboard($data);
    }

    /**
     * Enviar mensaje raw a un canal específico.
     *
     * @description Envía datos directamente a un canal sin procesamiento adicional.
     *              Usar para canales personalizados o mensajes que no encajan en
     *              los métodos de broadcast predefinidos.
     *
     * @param string $channel Nombre del canal destino (ej: "tenant_5", "pbx_3").
     * @param array  $data    Datos a enviar serializados como JSON.
     *
     * @return void
     */
    public function broadcastToChannel(string $channel, array $data): void
    {
        if (!$this->isReady()) return;
        $this->server->broadcastToChannel($channel, $data);
    }
}
