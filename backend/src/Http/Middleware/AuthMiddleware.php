<?php
declare(strict_types=1);

namespace CallMetrics\Http\Middleware;

use CallMetrics\Core\{JwtHelper, Response, TenantContext};

/**
 * Middleware de autenticación JWT.
 *
 * Extrae el token Bearer, lo decodifica, establece TenantContext,
 * y opcionalmente aplica una verificación de jerarquía de roles.
 *
 * Jerarquía de roles: SUPER_ADMIN(4) > ADMIN_TENANT(3) > SUPERVISOR(2) > OPERADOR(1)
 */
class AuthMiddleware
{
    /** Niveles de rol — mayor número = más privilegios. */
    private const ROLE_HIERARCHY = [
        'SUPER_ADMIN'  => 4,
        'ADMIN_TENANT' => 3,
        'SUPERVISOR'   => 2,
        'OPERADOR'     => 1,
    ];

    /**
     * Verificar que la solicitud porte un token de acceso válido.
     *
     * Cuando se proporciona $requiredRole, el rol del token debe ser >= ese nivel.
     * Llama a Response::error + exit en cualquier fallo — nunca retorna.
     *
     * @param string|null $requiredRole  Rol mínimo requerido (ej. 'SUPERVISOR')
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
     * Verificar si $userRole cumple o supera $requiredRole.
     */
    private static function roleHasAccess(string $userRole, string $requiredRole): bool
    {
        $userLevel    = self::ROLE_HIERARCHY[$userRole] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$requiredRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }
}
