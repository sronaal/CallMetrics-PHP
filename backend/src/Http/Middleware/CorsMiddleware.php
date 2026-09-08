<?php
declare(strict_types=1);

namespace CallMetrics\Http\Middleware;

class CorsMiddleware
{
    /**
     * Establecer headers CORS y manejar preflight OPTIONS.
     */
    public static function handle(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Agent-Token');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
