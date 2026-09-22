<?php
declare(strict_types=1);

namespace CallMetrics\Http\Middleware;

/**
 * Clase CorsMiddleware
 *
 * Middleware para manejar Cross-Origin Resource Sharing (CORS). Establece los
 * headers CORS necesarios para permitir solicitudes desde orígenes específicos
 * y maneja las solicitudes preflight OPTIONS.
 *
 * @description Configura los headers CORS con una whitelist de orígenes permitidos.
 *              Incluye soporte para credenciales, métodos HTTP y headers personalizados.
 * @package CallMetrics\Http\Middleware
 */
class CorsMiddleware
{
    /**
     * Establece headers CORS y maneja solicitudes preflight OPTIONS.
     *
     * @description Verifica el origen de la solicitud contra una whitelist de
     *              orígenes permitidos. Si el origen está permitido, establece
     *              los headers CORS correspondientes. Para solicitudes OPTIONS
     *              (preflight), retorna 204 y termina la ejecución.
     *
     * @return void
     */
    public static function handle(): void
    {
        // Orígenes permitidos (whitelist)
        $allowedOrigins = [
            'http://localhost',
            'http://localhost:80',
            'http://127.0.0.1',
            'http://localhost:3000',
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
