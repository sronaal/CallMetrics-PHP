# Delta for CallMetrics Backend Phase 0-1

## Capability: backend-infrastructure

### Requirement: Front Controller Request Lifecycle

The system MUST route all HTTP requests through `public/index.php` as the single entry point. The lifecycle MUST execute in order: Composer autoload → `.env` loading → route resolution → Request creation → CORS middleware → auth middleware (if required) → controller dispatch.

#### Scenario: Successful route resolution

- GIVEN a valid route `POST /api/auth/login` is registered in `config/routes.php`
- WHEN a POST request hits the server
- THEN `Router::match()` returns handler `AuthController@login` with `auth=false`
- AND the controller method is invoked with a `Request` object

#### Scenario: Route not found

- GIVEN a request to `/api/nonexistent`
- WHEN no route matches
- THEN `Response::json()` is called with status 404
- AND the script exits

---

### Requirement: PSR-4 Autoloading

The system MUST use Composer PSR-4 autoloading under the `CallMetrics\` namespace mapped to `src/`. The `composer.json` MUST require `firebase/php-jwt ^6.10`, `vlucas/phpdotenv ^5.6`, `ext-pdo`, and `ext-json`.

#### Scenario: Class resolution

- GIVEN a class `CallMetrics\Core\Database` is referenced
- WHEN the autoloader resolves it
- THEN it loads `src/Core/Database.php`

---

### Requirement: Environment Configuration

The system MUST load `.env` via `vlucas/phpdotenv` at startup. `Config::get($key, $default)` MUST read from `$_ENV`. Required env vars: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `JWT_SECRET`, `JWT_ACCESS_EXPIRY`, `JWT_REFRESH_EXPIRY`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `LOGIN_MAX_ATTEMPTS`, `LOGIN_LOCKOUT_MINUTES`.

#### Scenario: Default fallback

- GIVEN `.env` does not define `DB_PORT`
- WHEN `Config::get('DB_PORT', 3306)` is called
- THEN it returns `3306`

---

### Requirement: PDO Database Singleton

`Database::getInstance()` MUST return a singleton PDO wrapper. It MUST provide: `fetchAll($sql, $params): array`, `fetchOne($sql, $params): ?array`, `insert($sql, $params): int` (returns lastInsertId), `execute($sql, $params): int` (returns rowCount), `count($sql, $params): int`. PDO MUST use `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, and `EMULATE_PREPARES=false`.

#### Scenario: Prepared statement execution

- GIVEN a query `SELECT * FROM usuarios WHERE id = :id`
- WHEN `Database::getInstance()->fetchOne($sql, [':id' => 1])` is called
- THEN it returns an associative array or null

---

### Requirement: Router with Dynamic Parameters

`Router::match($method, $path)` MUST match HTTP method and URI pattern. Patterns MAY contain `{param}` placeholders (e.g., `/api/tenants/{id}`). It MUST return `['handler' => 'Controller@action', 'auth' => bool, 'role' => ?string, 'params' => [...]]` or `null`.

#### Scenario: Dynamic parameter extraction

- GIVEN route `['GET', '/api/tenants/{id}', 'TenantController@show', true, 'SUPER_ADMIN']`
- WHEN `match('GET', '/api/tenants/42')` is called
- THEN it returns `params = ['id' => '42']` and `role = 'SUPER_ADMIN'`

#### Scenario: Method mismatch

- GIVEN a POST route `/api/auth/login`
- WHEN `match('GET', '/api/auth/login')` is called
- THEN it returns `null`

---

### Requirement: Request Abstraction

`Request::fromGlobals()` MUST parse `$_SERVER`, `$_GET`, `php://input` (JSON body), and normalize HTTP headers. Methods: `method()`, `path()`, `query()`, `body()`, `header($name)`, `input($key, $default)`, `param($key)`, `all()`, `setRouteParams()`, `tenantId()`.

#### Scenario: JSON body parsing

- GIVEN a POST request with body `{"email":"a@b.com","password":"x"}`
- WHEN `Request::fromGlobals()` is called
- THEN `$request->input('email')` returns `"a@b.com"`

---

### Requirement: Standardized JSON Responses

`Response::json($data, $message, $status, $meta)` MUST output `{"success": bool, "message": string, "data": mixed, "meta": ?object}` with correct HTTP status and `Content-Type: application/json`. Static helpers: `ok()`, `created()`, `error()`, `unauthorized()`, `forbidden()`, `notFound()`, `noContent()`, `paginated()`. ALL response methods MUST call `exit` after output.

#### Scenario: Paginated response

- GIVEN 25 total records, page=2, size=10
- WHEN `Response::paginated($data, 2, 10, 25)` is called
- THEN response contains `meta.totalPages = 3`, `meta.page = 2`, `meta.size = 10`, `meta.total = 25`

---

### Requirement: Multi-Tenant Context

`TenantContext` MUST store `tenantId`, `userId`, and `role` per request. Methods: `set($tenantId, $userId, $role)`, `get(): ?int`, `getUserId(): ?int`, `getRole(): ?string`, `isSuperAdmin(): bool`, `isAdmin(): bool`, `clear()`. `isAdmin()` MUST return true for `SUPER_ADMIN` and `ADMIN_TENANT`.

#### Scenario: SUPER_ADMIN check

- GIVEN `TenantContext::set(1, 1, 'SUPER_ADMIN')`
- WHEN `TenantContext::isSuperAdmin()` is called
- THEN it returns `true`

#### Scenario: ADMIN_TENANT is admin

- GIVEN `TenantContext::set(1, 2, 'ADMIN_TENANT')`
- WHEN `TenantContext::isAdmin()` is called
- THEN it returns `true`

---

### Requirement: CORS Middleware

`CorsMiddleware::handle()` MUST set `Access-Control-Allow-Origin: *`, `Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS`, `Allow-Headers: Content-Type, Authorization, X-Requested-With`, `Max-Age: 86400`. For `OPTIONS` requests it MUST respond with 204 and exit.

#### Scenario: Preflight OPTIONS

- GIVEN an OPTIONS request
- WHEN `CorsMiddleware::handle()` executes
- THEN response code is 204 and script exits

---

### Requirement: MySQL Schema

`sql/schema.sql` MUST create database `callmetrics` (utf8mb4_unicode_ci) with 4 tables: `empresas` (id, nombre, nit UNIQUE, email, telefono, direccion, plan ENUM, activo, timestamps), `usuarios` (id, tenant_id FK, nombre, email, password_hash, rol ENUM, extension, activo, primer_ingreso, ultimo_login, timestamps, UNIQUE(tenant_id,email)), `refresh_tokens` (id, user_id FK, token_hash UNIQUE, expires_at, revoked, created_at), `login_attempts` (id, email, ip_address, attempted_at). Seed data: 1 SUPER_ADMIN user (`admin@callmetrics.com`, password `admin123`).

#### Scenario: Schema creation

- GIVEN a fresh MySQL installation
- WHEN `schema.sql` is executed
- THEN 4 tables exist with correct columns and indexes
- AND 1 SUPER_ADMIN user exists in `usuarios`

---

### Requirement: Base Controller

`Controller` MUST be an abstract class in `CallMetrics\Http\Controllers` namespace. It serves as the base class for all controllers with no methods.

#### Scenario: Controller inheritance

- GIVEN `AuthController extends Controller`
- WHEN instantiated
- THEN it is a valid `Controller` instance

---

### Requirement: Database Config

`config/database.php` MUST return an array with keys `host`, `port`, `name`, `user`, `pass`, `charset`, reading values from `$_ENV` with defaults matching `.env`.

#### Scenario: Config loading

- GIVEN `.env` has `DB_HOST=127.0.0.1`
- WHEN `config/database.php` is required
- THEN `$_ENV['DB_HOST']` resolves correctly

---

### Requirement: Route Registry

`config/routes.php` MUST return an array of route definitions. Each route is `[$method, $pattern, $handler, $auth, $role?]`. Phase 0 returns an empty array.

#### Scenario: Empty routes

- GIVEN no routes are registered
- WHEN `config/routes.php` is loaded
- THEN it returns `[]`

---

## Capability: backend-auth-jwt

### Requirement: JWT Token Generation

`JwtHelper::generateAccessToken($userId, $tenantId, $role, $email)` MUST create HS256 JWT with claims: `iss=callmetrics`, `iat`, `exp=iat+accessExpiry`, `sub=userId`, `tid=tenantId`, `role`, `email`, `type=access`. `generateRefreshToken($userId)` MUST create HS256 JWT with claims: `iss`, `iat`, `exp=iat+refreshExpiry`, `sub=userId`, `type=refresh`. Access expiry defaults to 900s (15min), refresh to 604800s (7d).

#### Scenario: Access token payload

- GIVEN `generateAccessToken(5, 2, 'OPERADOR', 'a@b.com')`
- WHEN the token is decoded
- THEN claims contain `sub=5`, `tid=2`, `role='OPERADOR'`, `type='access'`

#### Scenario: Token hash for storage

- GIVEN a refresh token string
- WHEN `JwtHelper::hash($token)` is called
- THEN it returns a 64-char hex SHA-256 hash

---

### Requirement: JWT Auth Middleware

`AuthMiddleware::handle($requiredRole)` MUST extract Bearer token from `Authorization` header via regex `/^Bearer\s+(.+)$/i`. It MUST decode the JWT, verify `type=access`, set `TenantContext`, and optionally check role hierarchy. Role hierarchy: `SUPER_ADMIN(4) > ADMIN_TENANT(3) > SUPERVISOR(2) > OPERADOR(1)`. A user with level >= required level passes.

#### Scenario: Valid token with role access

- GIVEN a valid access token for role `ADMIN_TENANT`
- WHEN `AuthMiddleware::handle('SUPERVISOR')` is called
- THEN TenantContext is set and request continues (level 3 >= 2)

#### Scenario: Missing token

- GIVEN no Authorization header
- WHEN `AuthMiddleware::handle()` is called
- THEN `Response::unauthorized('Token de acceso requerido')` is called

#### Scenario: Token type mismatch

- GIVEN a refresh token (type=refresh) used as Bearer
- WHEN `AuthMiddleware::handle()` is called
- THEN `Response::unauthorized('Tipo de token invalido')` is called

---

### Requirement: BaseModel with Tenant Filtering

`BaseModel` MUST provide: `findAll($conditions, $orderBy, $limit)`, `count($conditions)`, `find($id)`, `findBy($column, $value)`, `create($data)`, `update($id, $data)`, `delete($id)`, `paginate($page, $size, $conditions, $search, $searchColumns)`. When `$tenantScoped=true` AND the user is NOT SUPER_ADMIN, ALL queries MUST append `WHERE tenant_id = :tenant_id` (except `findBy()` which is global). `paginate()` MUST return `['data' => array, 'total' => int]`.

#### Scenario: Tenant-scoped findAll

- GIVEN `User` model with `$tenantScoped = true`
- WHEN `TenantContext::set(2, 1, 'OPERADOR')` and `User::findAll()` is called
- THEN SQL includes `WHERE tenant_id = :tenant_id` with value 2

#### Scenario: SUPER_ADMIN bypass

- GIVEN `User` model with `$tenantScoped = true`
- WHEN `TenantContext::set(null, 1, 'SUPER_ADMIN')` and `User::findAll()` is called
- THEN SQL has NO tenant filter

#### Scenario: Paginated search

- GIVEN 15 records, page=1, size=5, search="test" on columns `['nombre']`
- WHEN `paginate(1, 5, [], 'test', ['nombre'])` is called
- THEN total=matching count, data has at most 5 rows, offset=5

---

### Requirement: User Model Authentication Methods

`User::findByEmail($email)` MUST query globally (no tenant filter). `User::createWithPassword($data)` MUST hash `password` with bcrypt (cost 12) and store as `password_hash`. `User::verifyPassword($plain, $hash)` MUST use `password_verify()`. `User::countLoginAttempts($email, $ip, $windowMinutes)` MUST count rows in `login_attempts` within the time window. `User::logLoginAttempt($email, $ip)` MUST insert a row. `User::cleanOldAttempts($olderThanMinutes)` MUST delete old rows.

#### Scenario: Password hashing

- GIVEN `createWithPassword(['password' => 'secret123'])`
- WHEN the row is inserted
- THEN `password_hash` column contains a bcrypt hash starting with `$2y$`

#### Scenario: Rate limit counting

- GIVEN 3 login attempts for `a@b.com` from `127.0.0.1` in last 15 minutes
- WHEN `countLoginAttempts('a@b.com', '127.0.0.1', 15)` is called
- THEN it returns 3

---

### Requirement: Login Endpoint

`POST /api/auth/login` MUST accept `{email, password}`, validate rate limiting (5 attempts/15min per email+IP), verify credentials, check `activo`, generate access+refresh tokens, store refresh token hash in `refresh_tokens` table, update `ultimo_login`, and return `{accessToken, refreshToken, user}`. On success, MUST clean failed attempts for that email+IP.

#### Scenario: Successful login

- GIVEN valid credentials `admin@callmetrics.com` / `admin123`
- WHEN `POST /api/auth/login` is called
- THEN response contains `accessToken`, `refreshToken`, and `user` object with `id`, `nombre`, `email`, `rol`, `tenantId`

#### Scenario: Rate limiting

- GIVEN 5 failed attempts from same email+IP within 15 minutes
- WHEN a 6th login attempt is made
- THEN response is 429 with message "Demasiados intentos"

#### Scenario: Inactive user

- GIVEN a user with `activo = 0`
- WHEN login with correct credentials
- THEN response is 403 "Usuario desactivado"

---

### Requirement: Token Refresh with Rotation

`POST /api/auth/refresh` MUST accept `{refreshToken}`, decode it, verify type=refresh, check token exists in DB and is not revoked, verify user is active, revoke old token (set `revoked=1`), generate new access+refresh tokens, store new refresh token hash, and return new pair. This is ONE-TIME rotation — old refresh token cannot be reused.

#### Scenario: Successful refresh

- GIVEN a valid, non-revoked refresh token
- WHEN `POST /api/auth/refresh` is called
- THEN old token is revoked, new pair is returned

#### Scenario: Revoked token reuse

- GIVEN a refresh token with `revoked=1` in DB
- WHEN `POST /api/auth/refresh` is called
- THEN response is 401 "Token revocado o invalido"

---

### Requirement: Logout with Token Revocation

`POST /api/auth/logout` MUST accept `{refreshToken}`, hash it, and set `revoked=1` in `refresh_tokens`. The endpoint requires authentication (auth=true).

#### Scenario: Logout revokes token

- GIVEN a valid refresh token
- WHEN `POST /api/auth/logout` is called with the token
- THEN the token row has `revoked=1`

---

### Requirement: Auth Me Endpoint

`GET /api/auth/me` MUST return the authenticated user's data (excluding `password_hash`) with the company name resolved from `empresas`. Requires authentication.

#### Scenario: Get current user

- GIVEN a valid access token for user id=5
- WHEN `GET /api/auth/me` is called
- THEN response contains user data without `password_hash` and includes `empresa_nombre`

---

### Requirement: Change Password Endpoint

`PUT /api/auth/password` MUST accept `{currentPassword, newPassword}`, verify current password, enforce minimum 6 chars on new password, and update via `User::updateWithPassword()`. Requires authentication.

#### Scenario: Successful password change

- GIVEN correct `currentPassword` and `newPassword` with 6+ chars
- WHEN `PUT /api/auth/password` is called
- THEN response is 200 "Password actualizado"

#### Scenario: Wrong current password

- GIVEN incorrect `currentPassword`
- WHEN `PUT /api/auth/password` is called
- THEN response is 401 "Password actual incorrecto"

---

### Requirement: First Login Flow

`PUT /api/auth/primer-ingreso` MUST accept `{email, newPassword}`, find user by email, verify `primer_ingreso=1`, update password and set `primer_ingreso=0`, generate tokens, and return them. This endpoint is NOT authenticated (user has no token yet). One-time use only.

#### Scenario: First login completion

- GIVEN a user with `primer_ingreso=1`
- WHEN `PUT /api/auth/primer-ingreso` is called with valid email+password
- THEN `primer_ingreso` is set to 0, tokens are returned

#### Scenario: Already completed first login

- GIVEN a user with `primer_ingreso=0`
- WHEN `PUT /api/auth/primer-ingreso` is called
- THEN response is 400 "Este usuario ya completó su primer ingreso"
