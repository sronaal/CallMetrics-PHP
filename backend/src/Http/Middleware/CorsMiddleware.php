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
