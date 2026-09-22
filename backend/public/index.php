<?php
/**
 * Front Controller — CallMetrics API
 *
 * Este es el punto de entrada único de toda la API REST del backend.
 * Todas las peticiones HTTP pasan por este archivo (vía Apache RewriteRule).
 *
 * Flujo de ejecución:
 *   1. Carga del autoloader de Composer (psr-4 namespaces)
 *   2. Carga de variables de entorno desde .env
 *   3. Registro de rutas desde config/routes.php
 *   4. Construcción del objeto Request desde superglobals PHP
 *   5. Manejo de CORS (preflight OPTIONS + headers)
 *   6. Sirve archivos estáticos de documentación (Swagger UI + OpenAPI spec)
 *   7. Resolución de ruta → match con regex
 *   8. Validación de autenticación (JWT) y autorización (role hierarchy)
 *   9. Dispatch al controller@action correspondiente
 *
 * @package CallMetrics
 * @version  1.0.0
 * @link     http://localhost:8080
 */

declare(strict_types=1);

// ═══════════════════════════════════════════════════════════════════
// 1. AUTOLOADER — Carga automática de clases PSR-4
// ═══════════════════════════════════════════════════════════════════
// Mapea namespaces como CallMetrics\Core\*, CallMetrics\Http\*, etc.
// a los directorios físicos en src/
require_once __DIR__ . '/../vendor/autoload.php';

// ═══════════════════════════════════════════════════════════════════
// 2. ENTORNO — Variables de configuración desde .env
// ═══════════════════════════════════════════════════════════════════
// Carga DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_CHARSET,
// JWT_SECRET, JWT_ACCESS_EXPIRY, JWT_REFRESH_EXPIRY, APP_ENV, APP_PORT
// from ../.env (raíz del backend, no el directorio public/)
//
// Dotenv es inmutable: si .env no existe, lanza Dotenv\Exception\InvalidPathException
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// ═══════════════════════════════════════════════════════════════════
// 3. REGISTRO DE RUTAS
// ═══════════════════════════════════════════════════════════════════
// Carga el array de rutas desde config/routes.php.
// Cada ruta es: [METHOD, PATH_PATTERN, HANDLER, AUTH_REQUIRED, ROLE]
//
// Ejemplo:
//   ['GET', '/api/tenants', 'TenantController@index', true, 'SUPER_ADMIN'],
//   ['POST', '/api/auth/login', 'AuthController@login', false, null],
//
// El Router convierte {param} a named capture groups en regex:
//   /api/tenants/{id} → #^/api/tenants/(?P<id>[^/]+)$#
//
$routes = require __DIR__ . '/../config/routes.php';

// ═══════════════════════════════════════════════════════════════════
// 4. REQUEST — Construcción desde superglobals
// ═══════════════════════════════════════════════════════════════════
// Extrae method, path, headers, body (JSON), query params, cookies
// y los encapsula en un objeto Request inmutable.
//
// POST/PUT/PATCH: Decodifica JSON del body si Content-Type es application/json
// GET: Params desde $_GET
// Headers: Desde $_SERVER con prefijo HTTP_
$request = \CallMetrics\Core\Request::fromGlobals();

// ═══════════════════════════════════════════════════════════════════
// 5. CORS — Cross-Origin Resource Sharing
// ═══════════════════════════════════════════════════════════════════
// DEBE ejecutarse ANTES del router porque el método OPTIONS (preflight)
// no coincide con ninguna ruta registrada.
//
// - Headers CORS: Access-Control-Allow-Origin, Methods, Headers
// - Preflight OPTIONS: Responde 200 sin llegar al router
// - Whitelist de orígenes configurada en CorsMiddleware
\CallMetrics\Http\Middleware\CorsMiddleware::handle();

// ═══════════════════════════════════════════════════════════════════
// 6. DOCUMENTACIÓN ESTÁTICA — Swagger UI + OpenAPI Spec
// ═══════════════════════════════════════════════════════════════════
// Sirve archivos estáticos sin pasar por el router ni auth.
// Accesibles en: http://localhost:8080/docs

// Swagger UI — Interfaz web interactiva para probar endpoints
if ($request->path() === '/docs' || $request->path() === '/docs/') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/docs/index.html');
    exit;
}

// OpenAPI 3.0.3 spec — Especificación YAML (51 endpoints, 62 schemas)
if ($request->path() === '/docs/openapi.yaml') {
    header('Content-Type: text/yaml; charset=utf-8');
    readfile(__DIR__ . '/docs/openapi.yaml');
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// 7. ROUTER — Resolución de ruta
// ═══════════════════════════════════════════════════════════════════
// Matchea el método HTTP + path contra el registro de rutas.
// Retorna:
//   - match (array): ['handler', 'auth', 'role', 'params']
//   - null: si no hay coincidencia (404)
//
// El match incluye los parámetros extraídos de la URL:
//   /api/tenants/42 → ['params' => ['id' => '42']]
$router = new \CallMetrics\Core\Router($routes);
$match = $router->match($request->method(), $request->path());

// Si no hay match, retorna 404 JSON y termina
if (!$match) {
    \CallMetrics\Core\Response::notFound('Ruta no encontrada');
}

// ═══════════════════════════════════════════════════════════════════
// 8. PARÁMETROS DE RUTA — Inyección en el Request
// ═══════════════════════════════════════════════════════════════════
// Inyecta los parámetros capturados (ej: {id}) en el objeto Request
// para que los controllers puedan acceder con $request->param('id')
$request->setRouteParams($match['params']);

// ═══════════════════════════════════════════════════════════════════
// 9. AUTH MIDDLEWARE — Autenticación JWT + Autorización por Rol
// ═══════════════════════════════════════════════════════════════════
// Solo se ejecuta si la ruta requiere auth (match['auth'] === true).
//
// AuthMiddleware:
//   - Extrae Bearer token del header Authorization
//   - Valida JWT con JwtHelper::decode()
//   - Verifica expiración (exp claim)
//   - Popula TenantContext con tenantId, userId, role
//   - Verifica role hierarchy: SUPER_ADMIN(4) > ADMIN(3) > SUPERVISOR(2) > OPERADOR(1)
//
// Si el token es inválido o expirado → 401
// Si el role es insuficiente → 403
if ($match['auth']) {
    \CallMetrics\Http\Middleware\AuthMiddleware::handle($match['role'] ?? null);
}

// ═══════════════════════════════════════════════════════════════════
// 10. DISPATCH — Controller + Action
// ═══════════════════════════════════════════════════════════════════
// El handler está en formato "ControllerName@actionName"
// Ejemplo: "TenantController@index" → clase TenantController, método index()
//
// 1. Se extrae class y action del string
// 2. Se construye el FQCN: CallMetrics\Http\Controllers\{Controller}
// 3. Se instancia el controller (sin DI, creación directa)
// 4. Se invoca el método action pasando el Request
// 5. El controller retorna la respuesta vía Response::ok(), Response::created(), etc.
[$controllerClass, $action] = explode('@', $match['handler']);
$fullyQualifiedClass = "CallMetrics\\Http\\Controllers\\{$controllerClass}";
$controller = new $fullyQualifiedClass();
$controller->$action($request);
