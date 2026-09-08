<?php
declare(strict_types=1);

namespace CallMetrics\Core;

class Config
{
    /**
     * Obtener un valor de configuración de $_ENV con valor por defecto opcional.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }

    /**
     * Clave secreta JWT para firmar tokens.
     */
    public static function jwtSecret(): string
    {
        return (string) self::get('JWT_SECRET', '');
    }

    /**
     * Tiempo de expiración del token de acceso en segundos (por defecto: 900 = 15 minutos).
     */
    public static function jwtAccessExpiry(): int
    {
        return (int) self::get('JWT_ACCESS_EXPIRY', 900);
    }

    /**
     * Tiempo de expiración del token de refresco en segundos (por defecto: 604800 = 7 días).
     */
    public static function jwtRefreshExpiry(): int
    {
        return (int) self::get('JWT_REFRESH_EXPIRY', 604800);
    }

    /**
     * Verificar si la aplicación está ejecutándose en modo desarrollo.
     */
    public static function isDev(): bool
    {
        return self::get('APP_ENV') === 'development';
    }
}
