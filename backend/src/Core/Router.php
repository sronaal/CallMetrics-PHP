<?php
declare(strict_types=1);

namespace CallMetrics\Core;

class Router
{
    private array $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    /**
     * Resolver un método HTTP + ruta URI contra el registro de rutas.
     * Soporta parámetros dinámicos: /api/tenants/{id} → extrae ['id' => valor].
     *
     * @return array{handler: string, auth: bool, role: ?string, params: array}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            [$routeMethod, $routePattern, $handler, $auth, $role] = array_pad($route, 5, null);

            if ($method !== $routeMethod) {
                continue;
            }

            // Convertir marcadores {param} a grupos de captura con nombre
            $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $routePattern);
            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $path, $matches)) {
                // Extraer solo los grupos de captura con nombre (claves de cadena)
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                return [
                    'handler' => $handler,
                    'auth'    => (bool) $auth,
                    'role'    => $role,
                    'params'  => $params,
                ];
            }
        }

        return null;
    }
}
