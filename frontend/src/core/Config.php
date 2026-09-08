<?php
declare(strict_types=1);

/**
 * Configuracion centralizada del frontend.
 */
class Config
{
    const BACKEND_URL = 'http://localhost:8080';

    /**
     * API_URL se usa por el ApiClient PHP (curl server-side).
     * curl necesita URL absoluta — se usa directamente el backend.
     * Para requests del BROWSER (JS XHR), las páginas usan
     * el proxy en /api-proxy.php (same-origin, evita mixed content).
     */
    const API_URL = 'http://localhost:8080/api';

    /** URL del proxy same-origin para uso en JavaScript del browser */
    const API_PROXY_URL = '/CallMetrics_4TO/frontend/api-proxy.php';

    const WS_URL = 'ws://localhost:8081';
    const SESSION_TIMEOUT = 900; // 15 minutos
    const API_TIMEOUT = 30;
}
