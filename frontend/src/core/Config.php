<?php
declare(strict_types=1);

/**
 * Configuracion centralizada del frontend.
 */
class Config
{
    const BACKEND_URL = 'http://localhost:8080';
    const API_URL     = '/CallMetrics_4TO/frontend/api-proxy.php';
    const WS_URL      = 'ws://localhost:8081';
    const SESSION_TIMEOUT = 900; // 15 minutos
    const API_TIMEOUT = 30;

    /** URL directa al backend (solo para PHP server-side, NO para JS) */
    const DIRECT_API_URL = 'http://localhost:8080/api';
}
