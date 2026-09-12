<?php
declare(strict_types=1);

/**
 * Punto de entrada para el servidor WebSocket.
 *
 * Uso: php websocket-server.php
 * Requiere: composer require cboden/ratchet
 *
 * Escucha en el puerto 8081 por defecto.
 * Configurable via variables de entorno WS_HOST y WS_PORT.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use CallMetrics\WebSocket\Server;
use CallMetrics\WebSocket\EventBridge;

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['WS_HOST'] ?? '0.0.0.0';
$port = (int) ($_ENV['WS_PORT'] ?? 8081);

echo "===========================================\n";
echo " CallMetrics WebSocket Server\n";
echo "===========================================\n";
echo "Escuchando en $host:$port\n";
echo "Presiona Ctrl+C para detener\n";
echo "===========================================\n\n";

$wsServer = new Server();

// Inicializar el puente HTTP → WebSocket
// Permite que los controladores hagan broadcast después de procesar eventos del agente
EventBridge::init($wsServer);

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            $wsServer
        )
    ),
    $port,
    $host
);

$server->run();
