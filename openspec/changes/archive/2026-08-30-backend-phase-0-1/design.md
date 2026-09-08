# Design: Backend Phase 0 (Infrastructure) + Phase 1 (Authentication)

## Technical Approach

Front Controller pattern with PSR-4 autoloading under `CallMetrics\` namespace. Single entry point at `public/index.php` routes all requests through Router → Request → CORS → AuthMiddleware → Controller → Model → Database. Multi-tenancy via `tenant_id` column with automatic filtering in BaseModel. JWT access tokens (15min) + refresh tokens (7d) with one-time rotation. Rate limiting via `login_attempts` table with IP+email tracking.

## Architecture Decisions

| Decision | Options | Tradeoff | Choice |
|----------|---------|----------|--------|
| **Routing** | Static array vs regex compilation | Array simpler but no middleware chaining; regex complex but flexible | Static array (per BACKEND_PHP_PLAN.md) |
| **ORM** | Full ORM vs Active Record lite | Full ORM heavy for vanilla PHP; Active Record simpler, matches plan | Active Record lite in BaseModel |
| **JWT Storage** | Memory-only vs DB-persisted | Memory-only can't revoke; DB enables rotation + revocation | DB-persisted refresh_tokens |
| **Password Hashing** | bcrypt cost 10 vs 12 | 10 faster but weaker; 12 slower but more secure | bcrypt cost 12 (per spec) |
| **Error Handling** | Exception middleware vs inline | Middleware cleaner but adds complexity; inline simpler for small project | Inline with Response::error() calls |

## Data Flow

```
HTTP Request
    │
    ▼
public/index.php (Front Controller)
    │
    ├── autoload.php (PSR-4)
    ├── Dotenv::createImmutable() → $_ENV
    ├── config/routes.php → $routes[]
    │
    ▼
Router::match($method, $path) → $match['handler', 'auth', 'role', 'params']
    │
    ▼
Request::fromGlobals() → parses $_SERVER, $_GET, php://input JSON
    │
    ▼
CorsMiddleware::handle() → sets CORS headers; OPTIONS → 204 + exit
    │
    ▼
AuthMiddleware::handle($role) [if $match['auth']]
    │
    ├── Extract Bearer token from Authorization header
    ├── JwtHelper::decode($token) → $payload
    ├── Verify type=access
    ├── TenantContext::set($tid, $sub, $role)
    └── Verify role hierarchy (if $requiredRole)
    │
    ▼
Controller@$action($request)
    │
    ├── Input validation
    ├── Model::method() → Database::getInstance()
    │                     → PDO prepared statements
    └── Response::json() → exit
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `composer.json` | Create | PSR-4 autoload, firebase/php-jwt ^6.10, vlucas/phpdotenv ^5.6 |
| `.env` | Create | DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, JWT_SECRET, JWT_ACCESS_EXPIRY, JWT_REFRESH_EXPIRY, APP_ENV, APP_DEBUG, LOGIN_MAX_ATTEMPTS, LOGIN_LOCKOUT_MINUTES |
| `sql/schema.sql` | Create | MySQL 8 DDL: empresas, usuarios, refresh_tokens, login_attempts + seed SUPER_ADMIN |
| `public/index.php` | Create | Front Controller: autoload → dotenv → routes → Router → Request → CORS → Auth → Controller |
| `src/Core/Config.php` | Create | Static `get($key, $default)`, `jwtSecret()`, `jwtAccessExpiry()`, `jwtRefreshExpiry()`, `isDev()` |
| `src/Core/Database.php` | Create | PDO singleton: `getInstance()`, `fetchAll()`, `fetchOne()`, `insert()`, `execute()`, `count()` |
| `src/Core/Router.php` | Create | `match($method, $path)` with `{param}` placeholder support |
| `src/Core/Request.php` | Create | `fromGlobals()`, `method()`, `path()`, `body()`, `header()`, `input()`, `param()`, `setRouteParams()`, `tenantId()` |
| `src/Core/Response.php` | Create | `json()`, `ok()`, `created()`, `error()`, `unauthorized()`, `forbidden()`, `notFound()`, `paginated()` — all call `exit` |
| `src/Core/TenantContext.php` | Create | Static storage: `set()`, `get()`, `getUserId()`, `getRole()`, `isSuperAdmin()`, `isAdmin()`, `clear()` |
| `src/Core/JwtHelper.php` | Create | `generateAccessToken()`, `generateRefreshToken()`, `decode()`, `hash()` |
| `src/Http/Middleware/CorsMiddleware.php` | Create | `handle()` — CORS headers + OPTIONS 204 exit |
| `src/Http/Middleware/AuthMiddleware.php` | Create | `handle($role)` — Bearer extraction, JWT decode, TenantContext set, role hierarchy check |
| `src/Http/Controllers/Controller.php` | Create | Abstract base class |
| `src/Http/Controllers/AuthController.php` | Create | `login()`, `refresh()`, `logout()`, `me()`, `changePassword()`, `primerIngreso()` |
| `src/Models/BaseModel.php` | Create | Abstract: `findAll()`, `count()`, `find()`, `findBy()`, `create()`, `update()`, `delete()`, `paginate()` with auto tenant filtering |
| `src/Models/User.php` | Create | `findByEmail()`, `createWithPassword()`, `updateWithPassword()`, `verifyPassword()`, `countLoginAttempts()`, `logLoginAttempt()`, `cleanOldAttempts()` |
| `config/database.php` | Create | Returns `[host, port, name, user, pass, charset]` from $_ENV |
| `config/routes.php` | Create | Phase 0: empty array. Phase 1: 6 auth routes |

## Interfaces / Contracts

### JWT Access Token Payload
```json
{
  "iss": "callmetrics",
  "iat": 1725000000,
  "exp": 1725000900,
  "sub": 5,
  "tid": 2,
  "role": "OPERADOR",
  "email": "user@example.com",
  "type": "access"
}
```

### JWT Refresh Token Payload
```json
{
  "iss": "callmetrics",
  "iat": 1725000000,
  "exp": 1725604800,
  "sub": 5,
  "type": "refresh"
}
```

### Standard Response Envelope
```json
{
  "success": true,
  "message": "Login exitoso",
  "data": { ... },
  "meta": { "page": 0, "size": 10, "total": 25, "totalPages": 3 }
}
```

### Role Hierarchy
```
SUPER_ADMIN (4) > ADMIN_TENANT (3) > SUPERVISOR (2) > OPERADOR (1)
```

## Database Schema (MySQL 8 DDL)

```sql
CREATE DATABASE IF NOT EXISTS callmetrics CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE callmetrics;

CREATE TABLE empresas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    nit VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL,
    telefono VARCHAR(30) DEFAULT NULL,
    direccion VARCHAR(255) DEFAULT NULL,
    plan ENUM('FREE','BASIC','PRO','ENTERPRISE') NOT NULL DEFAULT 'FREE',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_empresas_nombre (nombre),
    INDEX idx_empresas_nit (nit)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED DEFAULT NULL,
    nombre VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('SUPER_ADMIN','ADMIN_TENANT','SUPERVISOR','OPERADOR') NOT NULL DEFAULT 'OPERADOR',
    extension VARCHAR(20) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    primer_ingreso TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuario_tenant_email (tenant_id, email),
    INDEX idx_usuario_email (email),
    INDEX idx_usuario_rol (rol),
    CONSTRAINT fk_usuario_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE refresh_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    revoked TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_refresh_user (user_id),
    INDEX idx_refresh_hash (token_hash),
    CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempts_email_ip (email, ip_address),
    INDEX idx_attempts_time (attempted_at)
) ENGINE=InnoDB;

-- Seed: SUPER_ADMIN (password: admin123)
INSERT INTO usuarios (tenant_id, nombre, email, password_hash, rol, activo, primer_ingreso)
VALUES (NULL, 'Super Administrador', 'admin@callmetrics.com',
    '$2y$12$YAXuLzTKYT6eR04enXW.IOrgKPfAv8/.RH.kk/1.jlpn.Mfk.VT..',
    'SUPER_ADMIN', 1, 0);
```

## Security Design

### Token Rotation Flow
1. Client sends `POST /api/auth/refresh` with `{refreshToken}`
2. Server decodes JWT, verifies `type=refresh`
3. Server hashes token, checks DB for `revoked=0`
4. Server revokes old token (`SET revoked=1`)
5. Server generates new access + refresh token pair
6. Server stores new refresh token hash in DB
7. Old token is permanently unusable

### Rate Limiting
- Table: `login_attempts` with `(email, ip_address, attempted_at)`
- Window: 15 minutes (configurable via `LOGIN_LOCKOUT_MINUTES`)
- Max attempts: 5 (configurable via `LOGIN_MAX_ATTEMPTS`)
- On success: delete all attempts for that email+IP
- Cleanup: `cleanOldAttempts(60)` runs on each login

### Password Hashing
- Algorithm: bcrypt (`PASSWORD_BCRYPT`)
- Cost factor: 12
- Format: `$2y$12$...` (60 chars)

### CORS Configuration
- `Access-Control-Allow-Origin: *` (development)
- `Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS`
- `Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With`
- `Access-Control-Max-Age: 86400`
- OPTIONS preflight returns 204 and exits

## API Endpoints (Phase 1)

| Method | Route | Auth | Body/Query | Response |
|--------|-------|------|------------|----------|
| `POST` | `/api/auth/login` | No | `{email, password}` | `{accessToken, refreshToken, user}` |
| `POST` | `/api/auth/refresh` | No | `{refreshToken}` | `{accessToken, refreshToken}` |
| `POST` | `/api/auth/logout` | Yes | `{refreshToken}` | `null` |
| `GET` | `/api/auth/me` | Yes | — | `user` (no password_hash) |
| `PUT` | `/api/auth/password` | Yes | `{currentPassword, newPassword}` | `null` |
| `PUT` | `/api/auth/primer-ingreso` | No | `{email, newPassword}` | `{accessToken, refreshToken}` |

### Error Response Format
```json
{
  "success": false,
  "message": "Credenciales invalidas",
  "data": null
}
```

### Validation Error Format
```json
{
  "success": false,
  "message": "Errores de validacion",
  "data": { "errors": { "email": "Email invalido" } }
}
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | Router::match() parameter extraction | curl with known routes |
| Unit | Database::fetchOne() prepared statements | curl with SELECT 1 |
| Unit | JwtHelper token generation/decode | curl login → decode token |
| Integration | Login → token pair returned | curl POST /api/auth/login |
| Integration | Refresh rotation → old token revoked | curl POST /api/auth/refresh twice |
| Integration | Rate limiting → 429 after 5 fails | curl 6x with wrong password |
| E2E | Full auth flow: login → me → password → logout | Sequential curl commands |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary in this phase.

## Migration / Rollout

1. Run `sql/schema.sql` to create database and tables
2. Run `composer install` to install dependencies
3. Configure Apache VirtualHost pointing DocumentRoot to `public/`
4. Test with seed SUPER_ADMIN credentials: `admin@callmetrics.com` / `admin123`
5. No data migration needed — greenfield project

## Open Questions

- None — all decisions are fully specified in BACKEND_PHP_PLAN.md and specs.md
