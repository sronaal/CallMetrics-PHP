<?php
declare(strict_types=1);

namespace CallMetrics\Http\Middleware;

use CallMetrics\Core\{JwtHelper, Response, TenantContext};

/**
 * Clase AuthMiddleware
 *
 * Middleware de autenticación JWT. Extrae el token Bearer del header Authorization,
 * lo decodifica y valida, establece el TenantContext para la solicitud actual,
 * y opcionalmente aplica una verificación de jerarquía de roles.
 *
 * @description Implementa la autenticación basada en JWT con soporte multi-tenant.
 *              Jerarquía de roles: SUPER_ADMIN(4) > ADMIN_TENANT(3) > SUPERVISOR(2) > OPERADOR(1)
 * @package CallMetrics\Http\Middleware
 */
class AuthMiddleware
{
    /**
     * Niveles de rol — mayor número = más privilegios.
     *
     * @description Mapa de roles a sus niveles numéricos para comparación jerárquica.
     */
    private const ROLE_HIERARCHY = [
        'SUPER_ADMIN'  => 4,
        'ADMIN_TENANT' => 3,
        'SUPERVISOR'   => 2,
        'OPERADOR'     => 1,
    ];

    /**
     * Verifica que la solicitud porte un token de acceso válido.
     *
     * @description Extrae el token del header Authorization, lo decodifica usando
     *              JwtHelper, valida que sea un token de acceso (no refresh),
     *              establece TenantContext con los datos del JWT, y opcionalmente
     *              verifica que el rol del usuario tenga los privilegios requeridos.
     *              En caso de error, emite una respuesta JSON y termina la ejecución.
     *
     * @param string|null $requiredRole Rol mínimo requerido para acceder (ej. 'SUPERVISOR')
     * @return void Nunca retorna — termina con Response::error en caso de fallo
     */
    public static function handle(?string $requiredRole = null): void
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token      = null;

        // Extraer token Bearer
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            Response::unauthorized('Token de acceso requerido');
            exit;
        }

        try {
            $payload = JwtHelper::decode($token);

            // Debe ser un token de acceso
            if (($payload->type ?? '') !== 'access') {
                Response::unauthorized('Tipo de token invalido');
                exit;
            }

            // Poblar el contexto del tenant a nivel de solicitud
            TenantContext::set(
                (int) ($payload->tid ?? 0),
                (int) ($payload->sub ?? 0),
                (string) ($payload->role ?? '')
            );

            // Verificación de jerarquía de roles
            if ($requiredRole !== null && $requiredRole !== '') {
                if (!self::roleHasAccess((string) ($payload->role ?? ''), $requiredRole)) {
                    Response::forbidden('No tienes permisos para esta accion');
                    exit;
                }
            }
        } catch (\Throwable $e) {
            Response::unauthorized('Token invalido o expirado');
            exit;
        }
    }

    /**
     * Verifica si el rol del usuario cumple o supera el rol requerido.
     *
     * @description Compara los niveles numéricos de los roles utilizando ROLE_HIERARCHY.
     *              El usuario tiene acceso si su nivel es mayor o igual al requerido.
     *
     * @param string $userRole Rol del usuario actual
     * @param string $requiredRole Rol mínimo requerido
     * @return bool true si el usuario tiene acceso, false de lo contrario
     */
    private static function roleHasAccess(string $userRole, string $requiredRole): bool
    {
        $userLevel    = self::ROLE_HIERARCHY[$userRole] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$requiredRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }
}
