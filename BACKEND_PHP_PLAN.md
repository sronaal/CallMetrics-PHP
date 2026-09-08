# CallMetrics — Backend PHP Vanilla Completo desde Cero

> **Fecha:** 2026-08-30  
> **Stack:** PHP 8.2+ vanilla, MySQL 8, JWT (firebase/php-jwt)  
> **Entorno:** LAMPP (Apache + MySQL en localhost)  
> **Arquitectura:** Multi-tenant con columna `tenant_id`, API REST JSON, MVC ligero

---

## 1. Arquitectura General

### 1.1 Patrón: Routing Front Controller + MVC Ligero

```
peticion HTTP
    │
    ▼
/public/index.php          ← Front Controller (entrar UNICO)
    │
    ▼
/src/Core/Router.php       ← Resuelve metodo + URI → Controlador@accion
    │
    ▼
/src/Http/Request.php      ← Normaliza $_GET, $_POST, php://input, headers
    │
    ▼
/src/Http/Middleware/
    ├── AuthMiddleware.php  ← Verifica JWT, inyecta tenant_id en contexto
    └── CorsMiddleware.php ← Headers CORS
    │
    ▼
/src/Controllers/XController.php  ← Logica del endpoint
    │
    ▼
/src/Models/XModel.php            ← Acceso a BD (Active Record ligero)
    │
    ▼
/src/Core/Database.php            ← PDO singleton, prepared statements
    │
    ▼
Respuesta JSON: { success, data, message, errors }
```

### 1.2 Multi-Tenancy: Columna `tenant_id`

Todas las tablas transaccionales tienen una columna `tenant_id`. El middleware de autenticacion extrae el `tenant_id` del JWT y lo inyecta en un contexto de request. Cada modelo lo usa automaticamente para filtrar.

```
Token JWT → AuthMiddleware → TenantContext::set($tenantId)
                                    │
                                    ▼
                        UserModel::findAll()
                            → SELECT * FROM usuarios WHERE tenant_id = :tid
                            (filtrado automatico)
```

**Excepciones (datos globales, solo SUPER_ADMIN):**
- `empresas` (tenants) — solo SUPER_ADMIN ve todo
- `usuarios` con `rol = 'SUPER_ADMIN'` — sin filtro de tenant

---

## 2. Estructura de Directorios

```
CallMetrics_4TO/
├── public/                          ← DocumentRoot de Apache
│   └── index.php                    ← Front Controller
│
├── src/
│   ├── Core/
│   │   ├── Config.php               ← Constantes, .env loader
│   │   ├── Database.php             ← PDO singleton
│   │   ├── Router.php               ← Mapeo URI → Controller@action
│   │   ├── Request.php              ← Wrapper de superglobals
│   │   ├── Response.php             ← Helper respuestas JSON
│   │   ├── JwtHelper.php            ← Generar/verificar tokens JWT
│   │   └── TenantContext.php        ← Almacena tenant_id por request
│   │
│   ├── Http/
│   │   ├── Middleware/
│   │   │   ├── AuthMiddleware.php   ← Requiere JWT valido
│   │   │   ├── CorsMiddleware.php   ← Headers CORS
│   │   │   └── AdminMiddleware.php  ← Requiere SUPER_ADMIN o ADMIN_TENANT
│   │   └── Controllers/
│   │       ├── AuthController.php   ← Login, logout, refresh, me
│   │       ├── TenantController.php ← CRUD empresas
│   │       ├── UserController.php   ← CRUD usuarios
│   │       └── DashboardController.php ← KPIs
│   │
│   ├── Models/
│   │   ├── BaseModel.php            ← Active Record base (findAll, find, save, delete)
│   │   ├── Tenant.php               ← Modelo empresa/tenant
│   │   ├── User.php                 ← Modelo usuario
│   │   └── Session.php              ← Manejo de sesiones PHP (fallback)
│   │
│   └── Helpers/
│       └── Validator.php            ← Validacion de inputs
│
├── config/
│   ├── routes.php                   ← Registro de rutas
│   └── database.php                 ← Credenciales BD
│
├── .env                             ← Variables de entorno
├── composer.json
└── sql/
    └── schema.sql                   ← Script de creacion de BD
```

---

## 3. Base de Datos

### 3.1 Script de Creación (`sql/schema.sql`)

```sql
-- ============================================================
-- CallMetrics — Schema MySQL 8
-- Multi-tenant con columna tenant_id
-- ============================================================

CREATE DATABASE IF NOT EXISTS callmetrics
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE callmetrics;

-- -----------------------------------------------------------
-- Tabla: empresas (tenants)
-- Datos globales, NO se filtra por tenant_id
-- -----------------------------------------------------------
CREATE TABLE empresas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    nit             VARCHAR(20)  NOT NULL UNIQUE,
    email           VARCHAR(150) NOT NULL,
    telefono        VARCHAR(30)  DEFAULT NULL,
    direccion       VARCHAR(255) DEFAULT NULL,
    plan            ENUM('FREE','BASIC','PRO','ENTERPRISE') NOT NULL DEFAULT 'FREE',
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_empresas_nombre (nombre),
    INDEX idx_empresas_nit (nit)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Tabla: usuarios
-- Filtrada por tenant_id (excepto SUPER_ADMIN global)
-- -----------------------------------------------------------
CREATE TABLE usuarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED DEFAULT NULL,
    nombre          VARCHAR(150) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    rol             ENUM('SUPER_ADMIN','ADMIN_TENANT','SUPERVISOR','OPERADOR') NOT NULL DEFAULT 'OPERADOR',
    extension       VARCHAR(20)  DEFAULT NULL,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    primer_ingreso  TINYINT(1)   NOT NULL DEFAULT 1,
    ultimo_login    DATETIME     DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Un email es unico DENTRO de un tenant (o global para SUPER_ADMIN)
    UNIQUE KEY uk_usuario_tenant_email (tenant_id, email),
    INDEX idx_usuario_email (email),
    INDEX idx_usuario_rol (rol),

    CONSTRAINT fk_usuario_tenant
        FOREIGN KEY (tenant_id) REFERENCES empresas(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Tabla: refresh_tokens (blacklist + rotation)
-- -----------------------------------------------------------
CREATE TABLE refresh_tokens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    token_hash      VARCHAR(64)  NOT NULL UNIQUE,  -- SHA-256 del token
    expires_at      DATETIME     NOT NULL,
    revoked         TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_refresh_user (user_id),
    INDEX idx_refresh_hash (token_hash),

    CONSTRAINT fk_refresh_user
        FOREIGN KEY (user_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Tabla: login_attempts (rate limiting)
-- -----------------------------------------------------------
CREATE TABLE login_attempts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(150) NOT NULL,
    ip_address      VARCHAR(45)  NOT NULL,
    attempted_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_attempts_email_ip (email, ip_address),
    INDEX idx_attempts_time (attempted_at)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Datos iniciales: SUPER_ADMIN por defecto
-- Password: admin123 (bcrypt)
-- -----------------------------------------------------------
INSERT INTO usuarios (tenant_id, nombre, email, password_hash, rol, activo, primer_ingreso)
VALUES (
    NULL,
    'Super Administrador',
    'admin@callmetrics.com',
    '$2y$12$LJ3m4yPzQ8vK5vQ8vK5vQuYx8vK5vQ8vK5vQuYx8vK5vQ8vK5vQu', -- admin123
    'SUPER_ADMIN',
    1,
    0
);
```

### 3.2 Diagrama ER

```
┌─────────────────┐       ┌─────────────────────┐
│    empresas      │       │      usuarios        │
├─────────────────┤       ├─────────────────────┤
│ id         (PK) │◄──────│ tenant_id    (FK)   │
│ nombre          │       │ id            (PK)   │
│ nit       (UQ)  │       │ nombre               │
│ email           │       │ email                │
│ telefono        │       │ password_hash        │
│ direccion       │       │ rol                  │
│ plan            │       │ extension            │
│ activo          │       │ activo               │
│ created_at      │       │ primer_ingreso       │
│ updated_at      │       │ ultimo_login         │
└─────────────────┘       │ created_at           │
                          │ updated_at           │
                          └──────────┬──────────┘
                                     │
                          ┌──────────▼──────────┐
                          │   refresh_tokens     │
                          ├─────────────────────┤
                          │ id            (PK)   │
                          │ user_id       (FK)   │
                          │ token_hash    (UQ)   │
                          │ expires_at           │
                          │ revoked              │
                          │ created_at           │
                          └─────────────────────┘

┌─────────────────────┐
│   login_attempts     │
├─────────────────────┤
│ id            (PK)  │
│ email               │
│ ip_address          │
│ attempted_at        │
└─────────────────────┘
```

---

## 4. Configuración

### 4.1 `.env`

```env
# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=callmetrics
DB_USER=root
DB_PASS=

# JWT
JWT_SECRET=tu_clave_secreta_minimo_32_caracteres_aqui_2026
JWT_ACCESS_EXPIRY=900        # 15 minutos
JWT_REFRESH_EXPIRY=604800    # 7 dias

# App
APP_ENV=development
APP_DEBUG=1
APP_URL=http://localhost

# Rate limiting
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15
```

### 4.2 `composer.json`

```json
{
    "name": "callmetrics/backend-php",
    "description": "CallMetrics PHP Vanilla Backend",
    "type": "project",
    "require": {
        "php": ">=8.1",
        "firebase/php-jwt": "^6.10",
        "vlucas/phpdotenv": "^5.6",
        "ext-pdo": "*",
        "ext-json": "*"
    },
    "autoload": {
        "psr-4": {
            "CallMetrics\\": "src/"
        }
    }
}
```

### 4.3 `config/database.php`

```php
<?php
return [
    'host'   => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port'   => (int)($_ENV['DB_PORT'] ?? 3306),
    'name'   => $_ENV['DB_NAME'] ?? 'callmetrics',
    'user'   => $_ENV['DB_USER'] ?? 'root',
    'pass'   => $_ENV['DB_PASS'] ?? '',
    'charset'=> 'utf8mb4',
];
```

### 4.4 `config/routes.php`

```php
<?php
// Registro de rutas: [metodo, URI, Controller@action, requiere_auth, rol_minimo]

return [
    // === Auth (publico) ===
    ['POST',   '/api/auth/login',            'AuthController@login',         false],
    ['POST',   '/api/auth/refresh',          'AuthController@refresh',       false],
    ['POST',   '/api/auth/logout',           'AuthController@logout',        true],

    // === Auth (autenticado) ===
    ['GET',    '/api/auth/me',               'AuthController@me',            true],
    ['PUT',    '/api/auth/password',         'AuthController@changePassword', true],
    ['PUT',    '/api/auth/primer-ingreso',   'AuthController@primerIngreso', false],

    // === Empresas (SUPER_ADMIN) ===
    ['GET',    '/api/tenants',               'TenantController@index',       true, 'SUPER_ADMIN'],
    ['GET',    '/api/tenants/{id}',          'TenantController@show',        true, 'SUPER_ADMIN'],
    ['POST',   '/api/tenants',               'TenantController@store',       true, 'SUPER_ADMIN'],
    ['PUT',    '/api/tenants/{id}',          'TenantController@update',      true, 'SUPER_ADMIN'],
    ['PATCH',  '/api/tenants/{id}/toggle',   'TenantController@toggle',      true, 'SUPER_ADMIN'],

    // === Usuarios ===
    ['GET',    '/api/usuarios',              'UserController@index',         true],
    ['GET',    '/api/usuarios/{id}',         'UserController@show',          true],
    ['POST',   '/api/usuarios',              'UserController@store',         true, 'ADMIN_TENANT'],
    ['PUT',    '/api/usuarios/{id}',         'UserController@update',        true, 'ADMIN_TENANT'],
    ['PATCH',  '/api/usuarios/{id}/toggle',  'UserController@toggle',        true, 'ADMIN_TENANT'],

    // === Dashboard ===
    ['GET',    '/api/dashboard/summary',     'DashboardController@summary',  true],
];
```

---

## 5. Core: Componentes Fundamentales

### 5.1 Front Controller (`public/index.php`)

```php
<?php
declare(strict_types=1);

// Cargar autoload de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Cargar rutas
$routes = require __DIR__ . '/../config/routes.php';

// Crear Request
$request = CallMetrics\Core\Request::fromGlobals();

// Resolver ruta
$router = new CallMetrics\Core\Router($routes);
$match = $router->match($request->method(), $request->path());

if (!$match) {
    CallMetrics\Core\Response::json([], 'Ruta no encontrada', 404);
    exit;
}

// Inyectar parametros de ruta (ej: {id})
$request->setRouteParams($match['params']);

// Ejecutar middlewares previos
CallMetrics\Http\Middleware\CorsMiddleware::handle();

// Verificar autenticacion si la ruta lo requiere
if ($match['auth']) {
    CallMetrics\Http\Middleware\AuthMiddleware::handle($match['role'] ?? null);
}

// Ejecutar controlador
[$controllerClass, $action] = explode('@', $match['handler']);
$controller = new $controllerClass();
$controller->$action($request);
```

### 5.2 Config (`src/Core/Config.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Core;

class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }

    public static function jwtSecret(): string
    {
        return self::get('JWT_SECRET', '');
    }

    public static function jwtAccessExpiry(): int
    {
        return (int) self::get('JWT_ACCESS_EXPIRY', 900);
    }

    public static function jwtRefreshExpiry(): int
    {
        return (int) self::get('JWT_REFRESH_EXPIRY', 604800);
    }

    public static function isDev(): bool
    {
        return self::get('APP_ENV') === 'development';
    }
}
```

### 5.3 Database (`src/Core/Database.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'], $config['port'], $config['name'], $config['charset']
        );

        $this->pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Ejecuta un SELECT y retorna todas las filas.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ejecuta un SELECT y retorna una fila.
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Ejecuta un INSERT y retorna el ID insertado.
     */
    public function insert(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Ejecuta un UPDATE/DELETE y retorna filas afectadas.
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Cuenta filas con COUNT(*).
     */
    public function count(string $sql, array $params = []): int
    {
        $result = $this->fetchOne($sql, $params);
        return (int) reset($result);
    }
}
```

### 5.4 Router (`src/Core/Router.php`)

```php
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
     * Resuelve una peticion HTTP contra el registro de rutas.
     * Soporta parametros dinamicos: /api/tenants/{id}
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

            // Convertir patron con {param} a regex
            $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $routePattern);
            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $path, $matches)) {
                // Extraer parametros nombrados
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
```

### 5.5 Request (`src/Core/Request.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Core;

class Request
{
    private string $method;
    private string $path;
    private array $queryParams;
    private array $body;
    private array $headers;
    private array $routeParams = [];

    public function __construct(
        string $method,
        string $path,
        array $queryParams,
        array $body,
        array $headers
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->queryParams = $queryParams;
        $this->body = $body;
        $this->headers = $headers;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $queryParams = $_GET;

        // Leer body JSON
        $body = [];
        $rawBody = file_get_contents('php://input');
        if ($rawBody) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        // Headers normalizados
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$header] = $value;
            }
        }

        return new self($method, $path, $queryParams, $body, $headers);
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function query(): array { return $this->queryParams; }
    public function body(): array { return $this->body; }
    public function header(string $name): ?string { return $this->headers[strtolower($name)] ?? null; }
    public function input(string $key, mixed $default = null): mixed { return $this->body[$key] ?? $default; }
    public function param(string $key): ?string { return $this->routeParams[$key] ?? null; }
    public function all(): array { return array_merge($this->queryParams, $this->body); }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function tenantId(): ?int
    {
        return TenantContext::get();
    }
}
```

### 5.6 Response (`src/Core/Response.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Core;

class Response
{
    /**
     * Responde con JSON estandarizado.
     */
    public static function json(
        mixed $data = null,
        string $message = '',
        int $status = 200,
        array $meta = []
    ): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'success' => $status >= 200 && $status < 300,
            'message' => $message,
            'data'    => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function created(mixed $data, string $message = 'Creado correctamente'): void
    {
        self::json($data, $message, 201);
    }

    public static function ok(mixed $data, string $message = ''): void
    {
        self::json($data, $message, 200);
    }

    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }

    public static function error(string $message, int $status = 400, array $errors = []): void
    {
        $data = !empty($errors) ? ['errors' => $errors] : null;
        self::json($data, $message, $status);
    }

    public static function unauthorized(string $message = 'No autenticado'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Sin permisos'): void
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Recurso no encontrado'): void
    {
        self::error($message, 404);
    }

    public static function paginated(array $data, int $page, int $size, int $total): void
    {
        self::json($data, '', 200, [
            'page'        => $page,
            'size'        => $size,
            'total'       => $total,
            'totalPages'  => (int) ceil($total / $size),
        ]);
    }
}
```

### 5.7 TenantContext (`src/Core/TenantContext.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Core;

/**
 * Almacena el tenant_id del usuario autenticado durante la request actual.
 * Seteado por AuthMiddleware despues de validar el JWT.
 */
class TenantContext
{
    private static ?int $tenantId = null;
    private static ?int $userId = null;
    private static ?string $role = null;

    public static function set(int $tenantId, int $userId, string $role): void
    {
        self::$tenantId = $tenantId;
        self::$userId = $userId;
        self::$role = $role;
    }

    public static function get(): ?int { return self::$tenantId; }
    public static function getUserId(): ?int { return self::$userId; }
    public static function getRole(): ?string { return self::$role; }

    public static function isSuperAdmin(): bool
    {
        return self::$role === 'SUPER_ADMIN';
    }

    public static function isAdmin(): bool
    {
        return in_array(self::$role, ['SUPER_ADMIN', 'ADMIN_TENANT'], true);
    }

    public static function clear(): void
    {
        self::$tenantId = null;
        self::$userId = null;
        self::$role = null;
    }
}
```

### 5.8 JwtHelper (`src/Core/JwtHelper.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

class JwtHelper
{
    private static string $secret = '';
    private static string $algo = 'HS256';

    public static function init(): void
    {
        self::$secret = Config::jwtSecret();
    }

    /**
     * Genera un access token (corta duracion).
     */
    public static function generateAccessToken(int $userId, int $tenantId, string $role, ?string $email): string
    {
        self::init();

        $now = time();
        $payload = [
            'iss'  => 'callmetrics',
            'iat'  => $now,
            'exp'  => $now + Config::jwtAccessExpiry(),
            'sub'  => $userId,
            'tid'  => $tenantId,   // tenant_id
            'role' => $role,
            'email'=> $email,
            'type' => 'access',
        ];

        return JWT::encode($payload, self::$secret, self::$algo);
    }

    /**
     * Genera un refresh token (larga duracion).
     */
    public static function generateRefreshToken(int $userId): string
    {
        self::init();

        $now = time();
        $payload = [
            'iss'  => 'callmetrics',
            'iat'  => $now,
            'exp'  => $now + Config::jwtRefreshExpiry(),
            'sub'  => $userId,
            'type' => 'refresh',
        ];

        return JWT::encode($payload, self::$secret, self::$algo);
    }

    /**
     * Decodifica y valida un token.
     * Retorna el payload o lanza excepcion.
     */
    public static function decode(string $token): object
    {
        self::init();
        return JWT::decode($token, new Key(self::$secret, self::$algo));
    }

    /**
     * Retorna hash SHA-256 del token (para almacenar en BD).
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
```

---

## 6. Middleware

### 6.1 CorsMiddleware (`src/Http/Middleware/CorsMiddleware.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Http\Middleware;

class CorsMiddleware
{
    public static function handle(): void
    {
        // En desarrollo, permitir todo. En produccion, restringir origenes.
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
```

### 6.2 AuthMiddleware (`src/Http/Middleware/AuthMiddleware.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Http\Middleware;

use CallMetrics\Core\{JwtHelper, TenantContext, Response};

class AuthMiddleware
{
    /**
     * Verifica que la peticion tenga un JWT valido.
     * Si $role es requerido, verifica que el rol del usuario cumpla.
     */
    public static function handle(?string $requiredRole = null): void
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = null;

        // Extraer token del header Authorization: Bearer <token>
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            Response::unauthorized('Token de acceso requerido');
            exit;
        }

        try {
            $payload = JwtHelper::decode($token);

            // Verificar que sea access token
            if (($payload->type ?? '') !== 'access') {
                Response::unauthorized('Tipo de token invalido');
                exit;
            }

            // Establecer contexto de tenant
            TenantContext::set(
                (int) ($payload->tid ?? 0),
                (int) ($payload->sub ?? 0),
                (string) ($payload->role ?? '')
            );

            // Verificar rol si se requiere
            if ($requiredRole) {
                if (!self::roleHasAccess($payload->role ?? '', $requiredRole)) {
                    Response::forbidden('No tienes permisos para esta accion');
                    exit;
                }
            }

        } catch (\Exception $e) {
            Response::unauthorized('Token invalido o expirado');
            exit;
        }
    }

    /**
     * Jerarquia de roles: SUPER_ADMIN > ADMIN_TENANT > SUPERVISOR > OPERADOR
     */
    private static function roleHasAccess(string $userRole, string $requiredRole): bool
    {
        $hierarchy = [
            'SUPER_ADMIN'   => 4,
            'ADMIN_TENANT'  => 3,
            'SUPERVISOR'    => 2,
            'OPERADOR'      => 1,
        ];

        $userLevel = $hierarchy[$userRole] ?? 0;
        $requiredLevel = $hierarchy[$requiredRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }
}
```

---

## 7. Modelos (Active Record Ligero)

### 7.1 BaseModel (`src/Models/BaseModel.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\{Database, TenantContext};

abstract class BaseModel
{
    protected static string $table = '';
    protected static bool $tenantScoped = true;

    /**
     * Obtener todas las filas (con filtro de tenant si aplica).
     */
    public static function findAll(array $conditions = [], string $orderBy = 'id DESC', int $limit = 100): array
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM " . static::$table;
        $params = [];
        $wheres = [];

        // Filtro automatico de tenant
        if (static::$tenantScoped && !TenantContext::isSuperAdmin()) {
            $tid = TenantContext::get();
            if ($tid !== null) {
                $wheres[] = "tenant_id = :tenant_id";
                $params[':tenant_id'] = $tid;
            }
        }

        // Condiciones adicionales
        foreach ($conditions as $column => $value) {
            $wheres[] = "$column = :$column";
            $params[":$column"] = $value;
        }

        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(' AND ', $wheres);
        }

        $sql .= " ORDER BY $orderBy";
        $sql .= " LIMIT " . (int) $limit;

        return $db->fetchAll($sql, $params);
    }

    /**
     * Contar filas.
     */
    public static function count(array $conditions = []): int
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as total FROM " . static::$table;
        $params = [];
        $wheres = [];

        if (static::$tenantScoped && !TenantContext::isSuperAdmin()) {
            $tid = TenantContext::get();
            if ($tid !== null) {
                $wheres[] = "tenant_id = :tenant_id";
                $params[':tenant_id'] = $tid;
            }
        }

        foreach ($conditions as $column => $value) {
            $wheres[] = "$column = :$column";
            $params[":$column"] = $value;
        }

        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(' AND ', $wheres);
        }

        return (int) $db->fetchOne($sql, $params)['total'];
    }

    /**
     * Buscar por ID.
     */
    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM " . static::$table . " WHERE id = :id";
        $params = [':id' => $id];

        // Filtro de tenant
        if (static::$tenantScoped && !TenantContext::isSuperAdmin()) {
            $tid = TenantContext::get();
            if ($tid !== null) {
                $sql .= " AND tenant_id = :tenant_id";
                $params[':tenant_id'] = $tid;
            }
        }

        return $db->fetchOne($sql, $params);
    }

    /**
     * Buscar por una columna especifica.
     */
    public static function findBy(string $column, mixed $value): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM " . static::$table . " WHERE $column = :value LIMIT 1";
        return $db->fetchOne($sql, [':value' => $value]);
    }

    /**
     * Insertar un registro.
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO " . static::$table . " ($columns) VALUES ($placeholders)";
        return $db->insert($sql, $data);
    }

    /**
     * Actualizar un registro.
     */
    public static function update(int $id, array $data): int
    {
        $db = Database::getInstance();
        $sets = [];
        $params = [':id' => $id];

        foreach ($data as $column => $value) {
            $sets[] = "$column = :$column";
            $params[":$column"] = $value;
        }

        $sql = "UPDATE " . static::$table . " SET " . implode(', ', $sets) . " WHERE id = :id";

        // Filtro de tenant
        if (static::$tenantScoped && !TenantContext::isSuperAdmin()) {
            $tid = TenantContext::get();
            if ($tid !== null) {
                $sql .= " AND tenant_id = :tenant_id";
                $params[':tenant_id'] = $tid;
            }
        }

        return $db->execute($sql, $params);
    }

    /**
     * Eliminar un registro.
     */
    public static function delete(int $id): int
    {
        $db = Database::getInstance();
        $sql = "DELETE FROM " . static::$table . " WHERE id = :id";
        $params = [':id' => $id];

        if (static::$tenantScoped && !TenantContext::isSuperAdmin()) {
            $tid = TenantContext::get();
            if ($tid !== null) {
                $sql .= " AND tenant_id = :tenant_id";
                $params[':tenant_id'] = $tid;
            }
        }

        return $db->execute($sql, $params);
    }

    /**
     * Paginacion generica.
     */
    public static function paginate(int $page = 0, int $size = 10, array $conditions = [], string $search = '', array $searchColumns = []): array
    {
        $db = Database::getInstance();
        $wheres = [];
        $params = [];

        // Filtro de tenant
        if (static::$tenantScoped && !TenantContext::isSuperAdmin()) {
            $tid = TenantContext::get();
            if ($tid !== null) {
                $wheres[] = "tenant_id = :tenant_id";
                $params[':tenant_id'] = $tid;
            }
        }

        // Busqueda
        if ($search && !empty($searchColumns)) {
            $searchClauses = [];
            foreach ($searchColumns as $col) {
                $searchClauses[] = "$col LIKE :search";
            }
            $wheres[] = '(' . implode(' OR ', $searchClauses) . ')';
            $params[':search'] = "%$search%";
        }

        // Condiciones
        foreach ($conditions as $column => $value) {
            $wheres[] = "$column = :$column";
            $params[":$column"] = $value;
        }

        $whereClause = !empty($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

        // Total
        $countSql = "SELECT COUNT(*) as total FROM " . static::$table . " $whereClause";
        $total = (int) $db->fetchOne($countSql, $params)['total'];

        // Datos
        $offset = $page * $size;
        $dataSql = "SELECT * FROM " . static::$table . " $whereClause ORDER BY id DESC LIMIT $size OFFSET $offset";
        $data = $db->fetchAll($dataSql, $params);

        return ['data' => $data, 'total' => $total];
    }
}
```

### 7.2 Tenant (`src/Models/Tenant.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Models;

class Tenant extends BaseModel
{
    protected static string $table = 'empresas';
    protected static bool $tenantScoped = false; // Datos globales

    /**
     * Buscar empresa por NIT.
     */
    public static function findByNit(string $nit): ?array
    {
        return parent::findBy('nit', $nit);
    }

    /**
     * Verificar si un NIT ya existe (excluyendo un ID).
     */
    public static function nitExists(string $nit, ?int $excludeId = null): bool
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as total FROM empresas WHERE nit = :nit";
        $params = [':nit' => $nit];

        if ($excludeId) {
            $sql .= " AND id != :id";
            $params[':id'] = $excludeId;
        }

        return (int) $db->fetchOne($sql, $params)['total'] > 0;
    }

    /**
     * Contar usuarios de un tenant.
     */
    public static function countUsers(int $tenantId): int
    {
        $db = Database::getInstance();
        $result = $db->fetchOne(
            "SELECT COUNT(*) as total FROM usuarios WHERE tenant_id = :tid",
            [':tid' => $tenantId]
        );
        return (int) $result['total'];
    }
}
```

### 7.3 User (`src/Models/User.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Models;

class User extends BaseModel
{
    protected static string $table = 'usuarios';
    protected static bool $tenantScoped = true;

    /**
     * Buscar usuario por email (global, sin filtro de tenant).
     */
    public static function findByEmail(string $email): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM usuarios WHERE email = :email LIMIT 1",
            [':email' => $email]
        );
    }

    /**
     * Verificar si un email ya existe en un tenant.
     */
    public static function emailExistsInTenant(string $email, int $tenantId, ?int $excludeId = null): bool
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as total FROM usuarios WHERE email = :email AND tenant_id = :tid";
        $params = [':email' => $email, ':tid' => $tenantId];

        if ($excludeId) {
            $sql .= " AND id != :id";
            $params[':id'] = $excludeId;
        }

        return (int) $db->fetchOne($sql, $params)['total'] > 0;
    }

    /**
     * Crear usuario con password hasheado.
     */
    public static function createWithPassword(array $data): int
    {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            unset($data['password']);
        }
        return parent::create($data);
    }

    /**
     * Actualizar usuario (hashea password si se provee).
     */
    public static function updateWithPassword(int $id, array $data): int
    {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            unset($data['password']);
        }
        return parent::update($id, $data);
    }

    /**
     * Verificar password.
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Contar intentos de login recientes (rate limiting).
     */
    public static function countLoginAttempts(string $email, string $ip, int $windowMinutes = 15): int
    {
        $db = Database::getInstance();
        $result = $db->fetchOne(
            "SELECT COUNT(*) as total FROM login_attempts 
             WHERE email = :email AND ip_address = :ip 
             AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)",
            [':email' => $email, ':ip' => $ip, ':minutes' => $windowMinutes]
        );
        return (int) $result['total'];
    }

    /**
     * Registrar intento de login.
     */
    public static function logLoginAttempt(string $email, string $ip): void
    {
        Database::getInstance()->insert(
            "INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip)",
            [':email' => $email, ':ip' => $ip]
        );
    }

    /**
     * Limpiar intentos antiguos.
     */
    public static function cleanOldAttempts(int $olderThanMinutes = 60): void
    {
        Database::getInstance()->execute(
            "DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL :min MINUTE)",
            [':min' => $olderThanMinutes]
        );
    }
}
```

---

## 8. Controladores

### 8.1 AuthController (`src/Http/Controllers/AuthController.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{JwtHelper, Response, TenantContext, Database};
use CallMetrics\Models\{User, Tenant};
use CallMetrics\Http\Controllers\Controller;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     * Body: { email, password }
     */
    public function login(Request $request): void
    {
        $email = trim($request->input('email', ''));
        $password = $request->input('password', '');

        if (!$email || !$password) {
            Response::error('Email y password son requeridos', 400);
            exit;
        }

        // Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $maxAttempts = (int) ($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5);

        if (User::countLoginAttempts($email, $ip) >= $maxAttempts) {
            Response::error('Demasiados intentos. Intenta en 15 minutos.', 429);
            exit;
        }

        // Buscar usuario
        $user = User::findByEmail($email);
        if (!$user || !User::verifyPassword($password, $user['password_hash'])) {
            User::logLoginAttempt($email, $ip);
            Response::error('Credenciales invalidas', 401);
            exit;
        }

        // Verificar que este activo
        if (!$user['activo']) {
            Response::error('Usuario desactivado', 403);
            exit;
        }

        // Limpiar intentos fallidos
        Database::getInstance()->execute(
            "DELETE FROM login_attempts WHERE email = :email AND ip_address = :ip",
            [':email' => $email, ':ip' => $ip]
        );

        // Generar tokens
        $accessToken = JwtHelper::generateAccessToken(
            (int) $user['id'],
            (int) ($user['tenant_id'] ?? 0),
            $user['rol'],
            $user['email']
        );

        $refreshToken = JwtHelper::generateRefreshToken((int) $user['id']);

        // Guardar refresh token en BD
        User::create([
            'user_id'    => $user['id'],
            'token_hash' => JwtHelper::hash($refreshToken),
            'expires_at' => date('Y-m-d H:i:s', time() + (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 604800)),
        ]);

        // Actualizar ultimo login
        User::update((int) $user['id'], [
            'ultimo_login' => date('Y-m-d H:i:s'),
        ]);

        // Buscar nombre de empresa
        $empresa = $user['tenant_id'] ? Tenant::find((int) $user['tenant_id']) : null;

        Response::ok([
            'accessToken'  => $accessToken,
            'refreshToken' => $refreshToken,
            'user' => [
                'id'       => $user['id'],
                'nombre'   => $user['nombre'],
                'email'    => $user['email'],
                'rol'      => $user['rol'],
                'tenantId' => $user['tenant_id'],
                'empresa'  => $empresa['nombre'] ?? 'Global',
            ],
        ], 'Login exitoso');
    }

    /**
     * POST /api/auth/refresh
     * Body: { refreshToken }
     */
    public function refresh(Request $request): void
    {
        $refreshToken = $request->input('refreshToken', '');

        if (!$refreshToken) {
            Response::error('Refresh token requerido', 400);
            exit;
        }

        try {
            $payload = JwtHelper::decode($refreshToken);

            if (($payload->type ?? '') !== 'refresh') {
                Response::error('Token invalido', 401);
                exit;
            }

            $userId = (int) $payload->sub;
            $tokenHash = JwtHelper::hash($refreshToken);

            // Verificar que el token exista y no este revocado
            $db = Database::getInstance();
            $stored = $db->fetchOne(
                "SELECT * FROM refresh_tokens WHERE user_id = :uid AND token_hash = :hash AND revoked = 0",
                [':uid' => $userId, ':hash' => $tokenHash]
            );

            if (!$stored) {
                Response::error('Token revocado o invalido', 401);
                exit;
            }

            // Obtener usuario
            $user = User::find($userId);
            if (!$user || !$user['activo']) {
                Response::error('Usuario no encontrado o desactivado', 401);
                exit;
            }

            // Revocar token anterior
            Database::getInstance()->execute(
                "UPDATE refresh_tokens SET revoked = 1 WHERE id = :id",
                [':id' => $stored['id']]
            );

            // Generar nuevos tokens (rotation)
            $newAccess = JwtHelper::generateAccessToken(
                $userId,
                (int) ($user['tenant_id'] ?? 0),
                $user['rol'],
                $user['email']
            );
            $newRefresh = JwtHelper::generateRefreshToken($userId);

            // Guardar nuevo refresh token
            Database::getInstance()->insert(
                "INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (:uid, :hash, :exp)",
                [
                    ':uid'   => $userId,
                    ':hash'  => JwtHelper::hash($newRefresh),
                    ':exp'   => date('Y-m-d H:i:s', time() + (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 604800)),
                ]
            );

            Response::ok([
                'accessToken'  => $newAccess,
                'refreshToken' => $newRefresh,
            ], 'Token renovado');

        } catch (\Exception $e) {
            Response::unauthorized('Refresh token invalido o expirado');
        }
    }

    /**
     * POST /api/auth/logout
     * Body: { refreshToken }
     */
    public function logout(Request $request): void
    {
        $refreshToken = $request->input('refreshToken', '');

        if ($refreshToken) {
            $tokenHash = JwtHelper::hash($refreshToken);
            Database::getInstance()->execute(
                "UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = :hash",
                [':hash' => $tokenHash]
            );
        }

        Response::ok(null, 'Sesion cerrada');
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): void
    {
        $userId = TenantContext::getUserId();
        $user = User::find($userId);

        if (!$user) {
            Response::notFound('Usuario no encontrado');
            exit;
        }

        unset($user['password_hash']);

        $empresa = $user['tenant_id'] ? Tenant::find((int) $user['tenant_id']) : null;
        $user['empresa_nombre'] = $empresa['nombre'] ?? 'Global';

        Response::ok($user);
    }

    /**
     * PUT /api/auth/password
     * Body: { currentPassword, newPassword }
     */
    public function changePassword(Request $request): void
    {
        $userId = TenantContext::getUserId();
        $current = $request->input('currentPassword', '');
        $new = $request->input('newPassword', '');

        if (!$current || !$new) {
            Response::error('Password actual y nuevo son requeridos', 400);
            exit;
        }

        if (strlen($new) < 6) {
            Response::error('El nuevo password debe tener al menos 6 caracteres', 400);
            exit;
        }

        $user = User::find($userId);
        if (!$user || !User::verifyPassword($current, $user['password_hash'])) {
            Response::error('Password actual incorrecto', 401);
            exit;
        }

        User::updateWithPassword($userId, ['password' => $new]);

        Response::ok(null, 'Password actualizado');
    }

    /**
     * PUT /api/auth/primer-ingreso
     * Body: { email, newPassword }
     */
    public function primerIngreso(Request $request): void
    {
        $email = trim($request->input('email', ''));
        $new = $request->input('newPassword', '');

        if (!$email || !$new) {
            Response::error('Email y nuevo password requeridos', 400);
            exit;
        }

        $user = User::findByEmail($email);
        if (!$user) {
            Response::notFound('Usuario no encontrado');
            exit;
        }

        if (!$user['primer_ingreso']) {
            Response::error('Este usuario ya completó su primer ingreso', 400);
            exit;
        }

        User::updateWithPassword((int) $user['id'], [
            'password'       => $new,
            'primer_ingreso' => 0,
        ]);

        // Auto-login: generar tokens
        $accessToken = JwtHelper::generateAccessToken(
            (int) $user['id'],
            (int) ($user['tenant_id'] ?? 0),
            $user['rol'],
            $user['email']
        );
        $refreshToken = JwtHelper::generateRefreshToken((int) $user['id']);

        Response::ok([
            'accessToken'  => $accessToken,
            'refreshToken' => $refreshToken,
        ], 'Primer ingreso completado');
    }
}
```

### 8.2 TenantController (`src/Http/Controllers/TenantController.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Response, TenantContext};
use CallMetrics\Models\Tenant;

class TenantController extends Controller
{
    /**
     * GET /api/tenants?page=0&size=10&search=nombre
     */
    public function index(Request $request): void
    {
        $page = max(0, (int) ($request->query()['page'] ?? 0));
        $size = max(1, min(100, (int) ($request->query()['size'] ?? 10)));
        $search = $request->query()['search'] ?? '';

        $result = Tenant::paginate($page, $size, [], $search, ['nombre', 'nit']);

        // Enriquecer con conteo de usuarios
        foreach ($result['data'] as &$tenant) {
            $tenant['usuarios_count'] = Tenant::countUsers((int) $tenant['id']);
            unset($tenant['updated_at']);
        }

        Response::paginated($result['data'], $page, $size, $result['total']);
    }

    /**
     * GET /api/tenants/{id}
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $tenant = Tenant::find($id);

        if (!$tenant) {
            Response::notFound('Empresa no encontrada');
            exit;
        }

        $tenant['usuarios_count'] = Tenant::countUsers($id);
        unset($tenant['updated_at']);

        Response::ok($tenant);
    }

    /**
     * POST /api/tenants
     * Body: { nombre, nit, email, telefono?, direccion?, plan? }
     */
    public function store(Request $request): void
    {
        $data = $request->body();

        // Validaciones
        $errors = [];
        if (empty($data['nombre'])) $errors['nombre'] = 'Nombre es requerido';
        if (empty($data['nit']))     $errors['nit'] = 'NIT es requerido';
        if (empty($data['email']))   $errors['email'] = 'Email es requerido';

        if (!empty($data['nit']) && Tenant::nitExists($data['nit'])) {
            $errors['nit'] = 'Este NIT ya esta registrado';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalido';
        }

        if (!empty($errors)) {
            Response::error('Errores de validacion', 422, $errors);
            exit;
        }

        $tenantId = Tenant::create([
            'nombre'    => trim($data['nombre']),
            'nit'       => trim($data['nit']),
            'email'     => trim($data['email']),
            'telefono'  => trim($data['telefono'] ?? ''),
            'direccion' => trim($data['direccion'] ?? ''),
            'plan'      => strtoupper($data['plan'] ?? 'FREE'),
            'activo'    => 1,
        ]);

        $tenant = Tenant::find($tenantId);
        Response::created($tenant, 'Empresa creada correctamente');
    }

    /**
     * PUT /api/tenants/{id}
     * Body: { nombre?, nit?, email?, telefono?, direccion?, plan? }
     */
    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $tenant = Tenant::find($id);

        if (!$tenant) {
            Response::notFound('Empresa no encontrada');
            exit;
        }

        $data = $request->body();
        $errors = [];

        // Verificar NIT unico si se cambia
        if (!empty($data['nit']) && $data['nit'] !== $tenant['nit']) {
            if (Tenant::nitExists($data['nit'], $id)) {
                $errors['nit'] = 'Este NIT ya esta registrado';
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalido';
        }

        if (!empty($errors)) {
            Response::error('Errores de validacion', 422, $errors);
            exit;
        }

        $updateData = array_filter([
            'nombre'    => trim($data['nombre'] ?? ''),
            'nit'       => trim($data['nit'] ?? ''),
            'email'     => trim($data['email'] ?? ''),
            'telefono'  => trim($data['telefono'] ?? ''),
            'direccion' => trim($data['direccion'] ?? ''),
            'plan'      => strtoupper($data['plan'] ?? ''),
        ], fn($v) => $v !== '');

        Tenant::update($id, $updateData);

        $updated = Tenant::find($id);
        Response::ok($updated, 'Empresa actualizada');
    }

    /**
     * PATCH /api/tenants/{id}/toggle
     */
    public function toggle(Request $request): void
    {
        $id = (int) $request->param('id');
        $tenant = Tenant::find($id);

        if (!$tenant) {
            Response::notFound('Empresa no encontrada');
            exit;
        }

        $newStatus = $tenant['activo'] ? 0 : 1;
        Tenant::update($id, ['activo' => $newStatus]);

        $updated = Tenant::find($id);
        Response::ok($updated, $newStatus ? 'Empresa activada' : 'Empresa desactivada');
    }
}
```

### 8.3 UserController (`src/Http/Controllers/UserController.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Response, TenantContext};
use CallMetrics\Models\User;

class UserController extends Controller
{
    /**
     * GET /api/usuarios?page=0&size=10&search=nombre
     */
    public function index(Request $request): void
    {
        $page = max(0, (int) ($request->query()['page'] ?? 0));
        $size = max(1, min(100, (int) ($request->query()['size'] ?? 10)));
        $search = $request->query()['search'] ?? '';

        $result = User::paginate($page, $size, [], $search, ['nombre', 'email']);

        // Limpiar datos sensibles
        foreach ($result['data'] as &$user) {
            unset($user['password_hash']);
            unset($user['updated_at']);
        }

        Response::paginated($result['data'], $page, $size, $result['total']);
    }

    /**
     * GET /api/usuarios/{id}
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $user = User::find($id);

        if (!$user) {
            Response::notFound('Usuario no encontrado');
            exit;
        }

        unset($user['password_hash']);
        Response::ok($user);
    }

    /**
     * POST /api/usuarios
     * Body: { nombre, email, password, rol, extension?, tenant_id? }
     */
    public function store(Request $request): void
    {
        $data = $request->body();
        $errors = [];

        if (empty($data['nombre']))  $errors['nombre'] = 'Nombre es requerido';
        if (empty($data['email']))   $errors['email'] = 'Email es requerido';
        if (empty($data['password'])) $errors['password'] = 'Password es requerido';
        if (strlen($data['password'] ?? '') < 6) $errors['password'] = 'Minimo 6 caracteres';

        // El tenant_id viene del contexto (el admin crea usuarios en SU tenant)
        $tenantId = TenantContext::isSuperAdmin()
            ? ($data['tenant_id'] ?? TenantContext::get())
            : TenantContext::get();

        if (!$tenantId) {
            $errors['tenant_id'] = 'Tenant requerido';
        }

        // Verificar email unico en el tenant
        if (!empty($data['email']) && $tenantId) {
            if (User::emailExistsInTenant($data['email'], $tenantId)) {
                $errors['email'] = 'Este email ya esta registrado en la empresa';
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalido';
        }

        $rolesValidos = ['SUPER_ADMIN', 'ADMIN_TENANT', 'SUPERVISOR', 'OPERADOR'];
        if (!empty($data['rol']) && !in_array($data['rol'], $rolesValidos)) {
            $errors['rol'] = 'Rol invalido';
        }

        if (!empty($errors)) {
            Response::error('Errores de validacion', 422, $errors);
            exit;
        }

        $userId = User::createWithPassword([
            'tenant_id'     => $tenantId,
            'nombre'        => trim($data['nombre']),
            'email'         => trim($data['email']),
            'password'      => $data['password'],
            'rol'           => strtoupper($data['rol'] ?? 'OPERADOR'),
            'extension'     => trim($data['extension'] ?? ''),
            'activo'        => 1,
            'primer_ingreso'=> 1,
        ]);

        $user = User::find($userId);
        unset($user['password_hash']);
        Response::created($user, 'Usuario creado correctamente');
    }

    /**
     * PUT /api/usuarios/{id}
     * Body: { nombre?, email?, rol?, extension?, password? }
     */
    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $user = User::find($id);

        if (!$user) {
            Response::notFound('Usuario no encontrado');
            exit;
        }

        $data = $request->body();
        $errors = [];

        // Verificar email unico si se cambia
        if (!empty($data['email']) && $data['email'] !== $user['email']) {
            $tenantId = (int) $user['tenant_id'];
            if (User::emailExistsInTenant($data['email'], $tenantId, $id)) {
                $errors['email'] = 'Este email ya esta registrado en la empresa';
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalido';
        }

        if (!empty($data['password']) && strlen($data['password']) < 6) {
            $errors['password'] = 'Minimo 6 caracteres';
        }

        if (!empty($errors)) {
            Response::error('Errores de validacion', 422, $errors);
            exit;
        }

        $updateData = array_filter([
            'nombre'    => trim($data['nombre'] ?? ''),
            'email'     => trim($data['email'] ?? ''),
            'rol'       => strtoupper($data['rol'] ?? ''),
            'extension' => trim($data['extension'] ?? ''),
        ], fn($v) => $v !== '');

        if (!empty($data['password'])) {
            User::updateWithPassword($id, array_merge($updateData, ['password' => $data['password']]));
        } else {
            User::update($id, $updateData);
        }

        $updated = User::find($id);
        unset($updated['password_hash']);
        Response::ok($updated, 'Usuario actualizado');
    }

    /**
     * PATCH /api/usuarios/{id}/toggle
     */
    public function toggle(Request $request): void
    {
        $id = (int) $request->param('id');
        $user = User::find($id);

        if (!$user) {
            Response::notFound('Usuario no encontrado');
            exit;
        }

        // No permitir desactivarse a si mismo
        if ((int) $user['id'] === TenantContext::getUserId()) {
            Response::error('No puedes desactivar tu propia cuenta', 400);
            exit;
        }

        // Proteger SUPER_ADMIN
        if ($user['rol'] === 'SUPER_ADMIN' && !TenantContext::isSuperAdmin()) {
            Response::forbidden('No puedes modificar un SUPER_ADMIN');
            exit;
        }

        $newStatus = $user['activo'] ? 0 : 1;
        User::update($id, ['activo' => $newStatus]);

        $updated = User::find($id);
        unset($updated['password_hash']);
        Response::ok($updated, $newStatus ? 'Usuario activado' : 'Usuario desactivado');
    }
}
```

### 8.4 DashboardController (`src/Http/Controllers/DashboardController.php`)

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Response, Database, TenantContext};

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/summary
     */
    public function summary(Request $request): void
    {
        $db = Database::getInstance();
        $tid = TenantContext::get();
        $isSuperAdmin = TenantContext::isSuperAdmin();

        // Filtro de tenant
        $tenantFilter = $isSuperAdmin ? '' : 'WHERE tenant_id = :tid';
        $params = $isSuperAdmin ? [] : [':tid' => $tid];

        // Total empresas (solo SUPER_ADMIN)
        $empresas = $isSuperAdmin
            ? (int) $db->fetchOne("SELECT COUNT(*) as t FROM empresas")['t']
            : 0;

        // Total usuarios
        $usuarios = (int) $db->fetchOne("SELECT COUNT(*) as t FROM usuarios $tenantFilter", $params)['t'];

        // Usuarios activos
        $usuariosActivos = (int) $db->fetchOne(
            "SELECT COUNT(*) as t FROM usuarios $tenantFilter " . ($isSuperAdmin ? '' : ' AND ') . "activo = 1",
            $params
        )['t'];

        Response::ok([
            'totalEmpresas'    => $empresas,
            'totalUsuarios'    => $usuarios,
            'usuariosActivos'  => $usuariosActivos,
            // KPIs de llamadas se agregan cuando se implemente el modulo de CC
            'llamadasHoy'      => 0,
            'llamadasActivas'  => 0,
            'agentesActivos'   => 0,
            'alertasActivas'   => 0,
        ]);
    }
}
```

### 8.5 Base Controller

```php
<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\Request;

abstract class Controller
{
    // Todos los controladores extienden de esta base
    // Los metodos se implementan en cada subclase
}
```

---

## 9. Seed de Datos de Prueba

### `sql/seed.sql`

```sql
-- Empresas de prueba
INSERT INTO empresas (nombre, nit, email, telefono, plan, activo) VALUES
('Corporacion Alpha S.A.', '900123456-1', 'alpha@corporacion.com', '+57 601 234 5678', 'ENTERPRISE', 1),
('Tecnologia Beta S.L.',   '900987654-2', 'beta@tech.com',        '+57 601 876 5432', 'PRO', 1),
('Grupo Gamma Corp.',      '900555123-3', 'gamma@grupo.com',      '+57 601 555 1234', 'ENTERPRISE', 1);

-- Usuarios de prueba (password: demo123 para todos)
INSERT INTO usuarios (tenant_id, nombre, email, password_hash, rol, activo, primer_ingreso) VALUES
-- SUPER_ADMIN global
(NULL, 'Carlos Admin',    'carlos@admin.com',    '$2y$12$LJ3m4yPzQ8vK5vQ8vK5vQuYx8vK5vQ8vK5vQuYx8vK5vQ8vK5vQu', 'SUPER_ADMIN', 1, 0),
-- Tenant 1: Alpha
(1, 'Jorge Mendoza',     'jorge@alpha.com',     '$2y$12$LJ3m4yPzQ8vK5vQ8vK5vQuYx8vK5vQ8vK5vQuYx8vK5vQ8vK5vQu', 'ADMIN_TENANT', 1, 0),
(1, 'Ana Supervisora',   'ana@alpha.com',        '$2y$12$LJ3m4yPzQ8vK5vQ8vK5vQuYx8vK5vQ8vK5vQuYx8vK5vQ8vK5vQu', 'SUPERVISOR', 1, 0),
(1, 'Pedro Operador',    'pedro@alpha.com',      '$2y$12$LJ3m4yPzQ8vK5vQ8vK5vQuYx8vK5vQ8vK5vQuYx8vK5vQ8vK5vQu', 'OPERADOR', 1, 1),
-- Tenant 2: Beta
(2, 'Maria Gerente',     'maria@beta.com',       '$2y$12$LJ3m4yPzQ8vK5vQ8vK5vQuYx8vK5vQ8vK5vQuYx8vK5vQ8vK5vQu', 'ADMIN_TENANT', 1, 0),
(2, 'Luis Operador',     'luis@beta.com',        '$2y$12$LJ3m4yPzQ8vK5vQ8vK5vQuYx8vK5vQ8vK5vQuYx8vK5vQ8vK5vQu', 'OPERADOR', 1, 0);
```

---

## 10. Fases de Implementación

### Vista General

```
Fase 0                Fase 1              Fase 2             Fase 3
[Infraestructura] ──► [Autenticación] ──► [CRUD Empresas] ──► [CRUD Usuarios]
     │                      │                    │                    │
     │                      │                    │                    │
     ▼                      ▼                    ▼                    ▼
  DB + Router +          JWT Login/           CRUD completo        CRUD completo
  Config + Base          Register/           con validaciones     con validaciones
                         Refresh                                   multi-tenant
     │
     ▼
  Fase 4                Fase 5
  [Dashboard] ────────► [Deploy + Pruebas]
       │                      │
       ▼                      ▼
  KPIs endpoint          VirtualHost curl
                         seed data
```

---

### Fase 0: Infraestructura Base

**Objetivo:** Tener el proyecto andando con base de datos, routing, y conexión a MySQL.

**Archivos a crear:**

| Archivo | Descripción |
|---|---|
| `composer.json` | Dependencias: firebase/php-jwt, vlucas/phpdotenv |
| `.env` | Variables de entorno (DB, JWT, App) |
| `sql/schema.sql` | Schema completo MySQL (empresas, usuarios, refresh_tokens, login_attempts) |
| `public/index.php` | Front Controller: carga autoload, .env, resuelve ruta, ejecuta controlador |
| `src/Core/Config.php` | Loader de .env, helper para leer config |
| `src/Core/Database.php` | PDO singleton con prepared statements (fetchAll, fetchOne, insert, execute, count) |
| `src/Core/Router.php` | Mapeo URI → Controller@action con soporte de parámetros dinámicos `{id}` |
| `src/Core/Request.php` | Wrapper de superglobals (method, path, body JSON, query, headers, params) |
| `src/Core/Response.php` | Helper JSON estandarizado (ok, created, error, paginated, unauthorized, etc.) |
| `src/Http/Middleware/CorsMiddleware.php` | Headers CORS + preflight OPTIONS |
| `src/Http/Controllers/Controller.php` | Clase base vacía para todos los controladores |
| `config/database.php` | Credenciales MySQL (lee de .env) |
| `config/routes.php` | Registro de rutas (array vacío, se llena en fases posteriores) |

**Criterio de aceptación:**
- `composer install` ejecuta sin errores
- MySQL: `schema.sql` crea la BD y tablas sin errores
- Apache: `GET /` responde JSON de bienvenida
- Ruta ficticia: `GET /api/test` responde `{"message": "Router funciona"}`
- Database: `Database::getInstance()->fetchAll("SELECT 1")` retorna resultado

**Esfuerzo estimado:** ~2 horas

---

### Fase 1: Autenticación (JWT)

**Objetivo:** Login, logout, refresh token, verificar identidad, cambio de password.

**Dependencia:** Fase 0

**Archivos a crear:**

| Archivo | Descripción |
|---|---|
| `src/Core/JwtHelper.php` | Generar access token (15min), refresh token (7d), decode, hash SHA-256 |
| `src/Http/Middleware/AuthMiddleware.php` | Extraer Bearer token, decodificar JWT, inyectar TenantContext, verificar rol |
| `src/Models/BaseModel.php` | Active Record base (findAll, find, create, update, delete, paginate) con filtro automático de tenant |
| `src/Models/User.php` | findByEmail, createWithPassword (bcrypt), verifyPassword, countLoginAttempts, logLoginAttempt |
| `src/Http/Controllers/AuthController.php` | login, refresh, logout, me, changePassword, primerIngreso |

**Archivos a modificar:**

| Archivo | Cambio |
|---|---|
| `config/routes.php` | Agregar rutas de auth (6 rutas) |

**Endpoints implementados:**

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| POST | `/api/auth/login` | No | Login con email+password, rate limiting (5 intentos/15min) |
| POST | `/api/auth/refresh` | No | Renovar tokens con rotación |
| POST | `/api/auth/logout` | Sí | Revocar refresh token |
| GET | `/api/auth/me` | Sí | Datos del usuario actual |
| PUT | `/api/auth/password` | Sí | Cambiar password (requiere password actual) |
| PUT | `/api/auth/primer-ingreso` | No | Establecer password en primer login |

**Criterio de aceptación:**
- Login con credenciales correctas retorna access + refresh token
- Login con credenciales incorrectas retorna 401
- Rate limiting: 6to intento retorna 429
- Token expirado retorna 401
- Refresh rotation: token anterior se revoca
- `/api/auth/me` retorna datos del usuario autenticado
- Cambio de password funciona

**Esfuerzo estimado:** ~3 horas

---

### Fase 2: CRUD Empresas (Tenants)

**Objetivo:** ABM completo de empresas, solo accesible por SUPER_ADMIN.

**Dependencia:** Fase 1

**Archivos a crear:**

| Archivo | Descripción |
|---|---|
| `src/Models/Tenant.php` | findByNit, nitExists, countUsers (extiende BaseModel con tenantScoped=false) |
| `src/Http/Controllers/TenantController.php` | index (paginado+search), show, store, update, toggle |

**Archivos a modificar:**

| Archivo | Cambio |
|---|---|
| `config/routes.php` | Agregar 5 rutas de tenants (todas requieren SUPER_ADMIN) |

**Endpoints implementados:**

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/tenants?page=0&size=10&search=` | SUPER_ADMIN | Listar empresas (paginado, búsqueda por nombre/NIT) |
| GET | `/api/tenants/{id}` | SUPER_ADMIN | Obtener empresa por ID |
| POST | `/api/tenants` | SUPER_ADMIN | Crear empresa `{ nombre, nit, email, telefono?, direccion?, plan? }` |
| PUT | `/api/tenants/{id}` | SUPER_ADMIN | Actualizar empresa |
| PATCH | `/api/tenants/{id}/toggle` | SUPER_ADMIN | Activar/desactivar empresa |

**Validaciones:**
- `nombre` requerido
- `nit` requerido y único
- `email` requerido y válido
- `plan` válido (FREE, BASIC, PRO, ENTERPRISE)
- NIT duplicado retorna 422

**Criterio de aceptación:**
- SUPER_ADMIN puede listar, crear, editar, activar/desactivar empresas
- ADMIN_TENANT reciben 403 al intentar acceder
- NIT duplicado retorna error de validación
- Response paginado con meta (page, size, total, totalPages)
- Toggle cambia estado y retorna empresa actualizada

**Esfuerzo estimado:** ~2 horas

---

### Fase 3: CRUD Usuarios

**Objetivo:** ABM de usuarios con control de acceso por rol y multi-tenancy.

**Dependencia:** Fase 2

**Archivos a crear:**

| Archivo | Descripción |
|---|---|
| `src/Http/Controllers/UserController.php` | index (paginado+search), show, store, update, toggle |

**Archivos a modificar:**

| Archivo | Cambio |
|---|---|
| `config/routes.php` | Agregar 5 rutas de usuarios |
| `src/Models/BaseModel.php` | Ajustar paginación para búsqueda multi-columna |

**Endpoints implementados:**

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/usuarios?page=0&size=10&search=` | Cualquiera | Listar usuarios del tenant |
| GET | `/api/usuarios/{id}` | Cualquiera | Obtener usuario por ID |
| POST | `/api/usuarios` | ADMIN_TENANT+ | Crear usuario `{ nombre, email, password, rol, extension? }` |
| PUT | `/api/usuarios/{id}` | ADMIN_TENANT+ | Actualizar usuario |
| PATCH | `/api/usuarios/{id}/toggle` | ADMIN_TENANT+ | Activar/desactivar usuario |

**Reglas de negocio:**
- SUPER_ADMIN crea usuarios en cualquier tenant (via `tenant_id` en body)
- ADMIN_TENANT crea usuarios solo en su propio tenant
- Email único dentro del tenant (no global)
- No puedes desactivarte a ti mismo
- Solo SUPER_ADMIN puede modificar un SUPER_ADMIN
- Password hasheado con bcrypt (cost 12)
- Nuevo usuario tiene `primer_ingreso = 1`

**Criterio de aceptación:**
- CRUD completo funciona contra MySQL
- Multi-tenant: ADMIN_TENANT solo ve usuarios de su tenant
- SUPER_ADMIN ve todos los usuarios (cross-tenant)
- Validación de email duplicado within tenant
- Protección contra auto-desactivación
- Password nunca se retorna en responses

**Esfuerzo estimado:** ~2 horas

---

### Fase 4: Dashboard (KPIs)

**Objetivo:** Endpoint de métricas agregadas para el dashboard principal.

**Dependencia:** Fase 3

**Archivos a crear:**

| Archivo | Descripción |
|---|---|
| `src/Http/Controllers/DashboardController.php` | summary con métricas agregadas |

**Archivos a modificar:**

| Archivo | Cambio |
|---|---|
| `config/routes.php` | Agregar 1 ruta de dashboard |

**Endpoints implementados:**

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| GET | `/api/dashboard/summary` | Sí | KPIs: totalEmpresas, totalUsuarios, usuariosActivos |

**Criterio de aceptación:**
- SUPER_ADMIN ve métricas globales (todas las empresas)
- Otros roles ven métricas de su tenant
- Response contiene todos los campos del dashboard

**Esfuerzo estimado:** ~1 hora

---

### Fase 5: Deploy + Pruebas

**Objetivo:** Configurar Apache, sembrar datos de prueba, validar todo con curl.

**Dependencia:** Fase 4

**Archivos a crear:**

| Archivo | Descripción |
|---|---|
| `sql/seed.sql` | Datos de prueba: 3 empresas, 6 usuarios, password demo123 |
| VirtualHost Apache | Configuración para LAMPP |

**Tareas:**

| # | Tarea | Verificación |
|---|---|---|
| 1 | Ejecutar `schema.sql` en MySQL | Tablas creadas sin errores |
| 2 | Ejecutar `seed.sql` | Datos insertados |
| 3 | Configurar VirtualHost Apache | `http://callmetrics.local` responde |
| 4 | `composer install` | Dependencias instaladas |
| 5 | Test curl: Login | Token obtenido |
| 6 | Test curl: /api/auth/me | Datos del usuario |
| 7 | Test curl: /api/tenants (SUPER_ADMIN) | Lista de empresas |
| 8 | Test curl: /api/usuarios (ADMIN_TENANT) | Lista de usuarios del tenant |
| 9 | Test curl: Crear empresa | Empresa creada |
| 10 | Test curl: Crear usuario | Usuario creado |
| 11 | Test curl: Toggle activo | Estado cambia |
| 12 | Test curl: Refresh token | Nuevos tokens |
| 13 | Test curl: Acceso denegado (rol bajo) | 403 |
| 14 | Test curl: Token expirado | 401 |

**Criterio de aceptación:**
- Todos los 14 tests pasan
- No hay errores en Apache error log
- No hay errores en PHP
- Multi-tenant funciona: datos de un tenant no se filtran a otro

**Esfuerzo estimado:** ~2 horas

---

### Resumen de Fases

| Fase | Nombre | Archivos | Endpoints | Efuerzo |
|---|---|---|---|---|
| **0** | Infraestructura | 12 archivos nuevos | 0 (solo test) | ~2h |
| **1** | Autenticación | 5 archivos nuevos | 6 endpoints | ~3h |
| **2** | CRUD Empresas | 2 archivos nuevos | 5 endpoints | ~2h |
| **3** | CRUD Usuarios | 1 archivo nuevo | 5 endpoints | ~2h |
| **4** | Dashboard | 1 archivo nuevo | 1 endpoint | ~1h |
| **5** | Deploy + Pruebas | 2 archivos nuevos | - | ~2h |
| | **TOTAL** | **~23 archivos** | **17 endpoints** | **~12h** |

### Flujo de Dependencias

```
Fase 0 ──► Fase 1 ──► Fase 2 ──► Fase 3 ──► Fase 4 ──► Fase 5
(DB+Router)  (JWT)     (Empresas)  (Usuarios)  (Dashboard) (Deploy)
```

**Regla:** No se puede arrancar una fase sin completar la anterior. Cada fase genera un entregable funcional y testeable de forma independiente.

---

## 11. Ejemplo de Uso con curl

```bash
# Login
curl -X POST http://localhost/CallMetrics_4TO/public/index.php/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"jorge@alpha.com","password":"demo123"}'

# Respuesta:
# {
#   "success": true,
#   "message": "Login exitoso",
#   "data": {
#     "accessToken": "eyJ...",
#     "refreshToken": "eyJ...",
#     "user": { "id": 2, "nombre": "Jorge Mendoza", "rol": "ADMIN_TENANT", ... }
#   }
# }

# Listar usuarios (con token)
curl http://localhost/CallMetrics_4TO/public/index.php/api/usuarios \
  -H "Authorization: Bearer eyJ..."

# Crear usuario
curl -X POST http://localhost/CallMetrics_4TO/public/index.php/api/usuarios \
  -H "Authorization: Bearer eyJ..." \
  -H "Content-Type: application/json" \
  -d '{"nombre":"Nuevo User","email":"nuevo@alpha.com","password":"pass123","rol":"OPERADOR"}'

# Listar empresas (solo SUPER_ADMIN)
curl http://localhost/CallMetrics_4TO/public/index.php/api/tenants \
  -H "Authorization: Bearer eyJ..."
```

---

## 12. Apache VirtualHost (LAMPP)

```apache
<VirtualHost *:80>
    ServerName callmetrics.local
    DocumentRoot /opt/lampp/htdocs/CallMetrics_4TO/public

    <Directory /opt/lampp/htdocs/CallMetrics_4TO/public>
        AllowOverride All
        Require all granted

        # Front Controller: todas las rutas van a index.php
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [QSA,L]
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/callmetrics_error.log
    CustomLog ${APACHE_LOG_DIR}/callmetrics_access.log combined
</VirtualHost>
```
