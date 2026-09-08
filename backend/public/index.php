<?php
declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env via Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Load route registry
$routes = require __DIR__ . '/../config/routes.php';

// Create Request from superglobals
$request = \CallMetrics\Core\Request::fromGlobals();

// CORS headers + OPTIONS preflight (MUST run before router — OPTIONS doesn't match any route)
\CallMetrics\Http\Middleware\CorsMiddleware::handle();

// Swagger UI endpoint
if ($request->path() === '/docs' || $request->path() === '/docs/') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/docs/index.html');
    exit;
}

// OpenAPI spec endpoint
if ($request->path() === '/docs/openapi.yaml') {
    header('Content-Type: text/yaml; charset=utf-8');
    readfile(__DIR__ . '/docs/openapi.yaml');
    exit;
}

// Resolve route
$router = new \CallMetrics\Core\Router($routes);
$match = $router->match($request->method(), $request->path());

if (!$match) {
    \CallMetrics\Core\Response::notFound('Ruta no encontrada');
}

// Inject route parameters (e.g., {id})
$request->setRouteParams($match['params']);

// Auth middleware (if route requires authentication)
if ($match['auth']) {
    \CallMetrics\Http\Middleware\AuthMiddleware::handle($match['role'] ?? null);
}

// Dispatch to controller
[$controllerClass, $action] = explode('@', $match['handler']);
$fullyQualifiedClass = "CallMetrics\\Http\\Controllers\\{$controllerClass}";
$controller = new $fullyQualifiedClass();
$controller->$action($request);
