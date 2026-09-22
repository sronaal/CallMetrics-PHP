<?php
declare(strict_types=1);

namespace CallMetrics\Core;

/**
 * Clase Router
 *
 * Motor de enrutamiento HTTP que resuelve rutas URI contra un registro de rutas
 * predefinidas. Soporta parámetros dinámicos en las rutas (ej: /users/{id})
 * y extrae los valores de los parámetros de la URI.
 *
 * @description Analiza la ruta solicitada y la compara con las rutas registradas,
 *              retornando el handler, si requiere autenticación, el rol requerido
 *              y los parámetros de ruta extraídos.
 * @package CallMetrics\Core
 */
class Router
{
    /** @var array Lista de rutas registradas */
    private array $routes;

    /**
     * Constructor del Router.
     *
     * @description Inicializa el router con la lista de rutas proporcionada.
     *              Cada ruta es un array con: [método, patrón, handler, auth, role].
     *
     * @param array $routes Array de rutas registradas en la aplicación
     */
    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    /**
     * Resuelve un método HTTP + ruta URI contra el registro de rutas.
     *
     * @description Compara la solicitud con cada ruta registrada. Convierte los
     *              marcadores {param} a grupos de captura con nombre en regex.
     *              Si hay coincidencia, retorna el handler, si requiere auth,
     *              el rol requerido y los parámetros de ruta extraídos.
     *
     * @param string $method Método HTTP de la solicitud (GET, POST, PUT, DELETE)
     * @param string $path Ruta URI de la solicitud
     * @return array|null Array con handler, auth, role y params si hay coincidencia, null si no
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
