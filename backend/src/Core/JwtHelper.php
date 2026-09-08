<?php
declare(strict_types=1);

namespace CallMetrics\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Asistente JWT para el ciclo de vida de tokens de acceso y refresco.
 *
 * Usa firebase/php-jwt v7 (compatible con la API de v6):
 *   JWT::encode(array $payload, string $key, string $alg): string
 *   JWT::decode(string $jwt, Key $key): stdClass
 */
class JwtHelper
{
    private static string $secret = '';
    private static string $algo   = 'HS256';

    /**
     * Cargar la clave secreta de firma desde Config (idempotente).
     */
    public static function init(): void
    {
        self::$secret = Config::jwtSecret();
    }

    /**
     * Generar un token de acceso de corta duración (por defecto 15 min).
     *
     * Payload:
     *   iss, iat, exp, sub (userId), tid (tenantId), role, email, type=access
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
     * Generar un token de refresco de larga duración (por defecto 7 días).
     *
     * Payload:
     *   iss, iat, exp, sub (userId), type=refresh
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
     * Decodificar y validar una cadena JWT.
     *
     * Retorna el payload como un stdClass.
     * Lanza \Exception en tokens inválidos o expirados.
     */
    public static function decode(string $token): object
    {
        self::init();
        return JWT::decode($token, new Key(self::$secret, self::$algo));
    }

    /**
     * Hash SHA-256 del token para almacenamiento en base de datos.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
