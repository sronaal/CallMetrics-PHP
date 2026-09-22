<?php
declare(strict_types=1);

namespace CallMetrics\Core;

/**
 * Clase Config
 *
 * Proporciona acceso centralizado a las variables de configuración del sistema
 * cargadas desde el archivo .env a través de $_ENV. Utiliza el patrón de acceso
 * estático para facilitar el uso desde cualquier parte de la aplicación.
 *
 * @package CallMetrics\Core
 */
class Config
{
    /**
     * Obtiene el valor de una variable de entorno con valor por defecto opcional.
     *
     * @description Busca la variable de entorno por su clave y retorna el valor.
     *              Si no existe, retorna el valor por defecto proporcionado.
     *
     * @param string $key Nombre de la variable de entorno a buscar
     * @param mixed $default Valor por defecto si la variable no existe
     * @return mixed Valor de la variable de entorno o el valor por defecto
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }

    /**
     * Obtiene la clave secreta JWT utilizada para firmar tokens.
     *
     * @description Retorna la clave secreta JWT desde la variable JWT_SECRET.
     *              Se utiliza para firmar y verificar tokens de autenticación.
     *
     * @return string Clave secreta JWT
     */
    public static function jwtSecret(): string
    {
        return (string) self::get('JWT_SECRET', '');
    }

    /**
     * Obtiene el tiempo de expiración del token de acceso en segundos.
     *
     * @description Retorna el tiempo de vida del token de acceso JWT.
     *              Por defecto es 900 segundos (15 minutos).
     *
     * @return int Tiempo de expiración en segundos
     */
    public static function jwtAccessExpiry(): int
    {
        return (int) self::get('JWT_ACCESS_EXPIRY', 900);
    }

    /**
     * Obtiene el tiempo de expiración del token de refresco en segundos.
     *
     * @description Retorna el tiempo de vida del token de refresco JWT.
     *              Por defecto es 604800 segundos (7 días).
     *
     * @return int Tiempo de expiración en segundos
     */
    public static function jwtRefreshExpiry(): int
    {
        return (int) self::get('JWT_REFRESH_EXPIRY', 604800);
    }

    /**
     * Verifica si la aplicación está ejecutándose en modo desarrollo.
     *
     * @description Compara la variable APP_ENV con 'development' para determinar
     *              si la aplicación está en modo de desarrollo.
     *
     * @return bool true si está en modo desarrollo, false de lo contrario
     */
    public static function isDev(): bool
    {
        return self::get('APP_ENV') === 'development';
    }
}
