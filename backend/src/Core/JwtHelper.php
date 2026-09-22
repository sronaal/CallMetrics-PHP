<?php
declare(strict_types=1);

namespace CallMetrics\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Clase JwtHelper
 *
 * Asistente JWT para el ciclo de vida de tokens de acceso y refresco.
 * Utiliza la librería firebase/php-jwt v7 (compatible con la API de v6) para
 * codificar y decodificar tokens JWT con el algoritmo HS256.
 *
 * @description Proporciona métodos estáticos para generar, decodificar y hashear
 *              tokens JWT utilizados en el sistema de autenticación.
 * @package CallMetrics\Core
 */
class JwtHelper
{
    /** @var string Clave secreta para firmar tokens JWT */
    private static string $secret = '';

    /** @var string Algoritmo de firma JWT (por defecto HS256) */
    private static string $algo   = 'HS256';

    /**
     * Inicializa la clave secreta de firma desde Config.
     *
     * @description Carga la clave secreta JWT desde la configuración del sistema.
     *              Es idempotente, puede llamarse múltiples veces sin efecto.
     *
     * @return void
     */
    public static function init(): void
    {
        self::$secret = Config::jwtSecret();
    }

    /**
     * Genera un token de acceso de corta duración (por defecto 15 min).
     *
     * @description Crea un token JWT con los claims: iss (issuer), iat (issued at),
     *              exp (expiration), sub (userId), tid (tenantId), role, email y
     *              type=access. El token se firma con la clave secreta HS256.
     *
     * @param int $userId ID del usuario propietario del token
     * @param int $tenantId ID del tenant al que pertenece el usuario
     * @param string $role Rol del usuario (SUPER_ADMIN, ADMIN_TENANT, USER)
     * @param string|null $email Correo electrónico del usuario
     * @return string Token JWT codificado como string
     */
    public static function generateAccessToken(int $userId, int $tenantId, string $role, ?string $email): string
    {
        self::init();

        $now    = time();
        $payload = [
            'iss'   => 'callmetrics',
            'iat'   => $now,
            'exp'   => $now + Config::jwtAccessExpiry(),
            'sub'   => $userId,
            'tid'   => $tenantId,
            'role'  => $role,
            'email' => $email,
            'type'  => 'access',
        ];

        return JWT::encode($payload, self::$secret, self::$algo);
    }

    /**
     * Genera un token de refresco de larga duración (por defecto 7 días).
     *
     * @description Crea un token JWT de refresco con los claims: iss, iat, exp,
     *              sub (userId) y type=refresh. Incluye un nonce (jti) generado
     *              aleatoriamente para garantizar unicidad del token.
     *
     * @param int $userId ID del usuario propietario del token
     * @return string Token JWT de refresco codificado como string
     */
    public static function generateRefreshToken(int $userId): string
    {
        self::init();

        $now    = time();
        $payload = [
            'iss'  => 'callmetrics',
            'iat'  => $now,
            'exp'  => $now + Config::jwtRefreshExpiry(),
            'sub'  => $userId,
            'type' => 'refresh',
            'jti'  => bin2hex(random_bytes(16)), // nonce para unicidad
        ];

        return JWT::encode($payload, self::$secret, self::$algo);
    }

    /**
     * Decodifica y valida una cadena JWT.
     *
     * @description Verifica la firma del token JWT y retorna el payload como
     *              un objeto stdClass. Lanza una excepción si el token es
     *              inválido, está expirado o tiene un formato incorrecto.
     *
     * @param string $token Cadena JWT a decodificar
     * @return object Payload del token como objeto stdClass
     * @throws \Exception Si el token es inválido o está expirado
     */
    public static function decode(string $token): object
    {
        self::init();
        return JWT::decode($token, new Key(self::$secret, self::$algo));
    }

    /**
     * Genera un hash SHA-256 del token para almacenamiento en base de datos.
     *
     * @description Crea un hash unidireccional del token JWT utilizando SHA-256.
     *              Se utiliza para almacenar tokens de forma segura en la BD.
     *
     * @param string $token Cadena JWT a hashear
     * @return string Hash SHA-256 del token en formato hexadecimal
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
