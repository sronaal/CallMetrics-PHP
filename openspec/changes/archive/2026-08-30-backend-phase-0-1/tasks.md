# Tasks: Backend Phase 0 (Infrastructure) + Phase 1 (Authentication)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1310 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 2 → PR 3 → PR 4 |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Project foundation: DB schema, config, PDO singleton | PR 1 | `php -r "require 'vendor/autoload.php'; ..."` | Schema creation via MySQL CLI | composer.json, .env, sql/, config/database.php, src/Core/Config.php, src/Core/Database.php |
| 2 | Core HTTP infra: routing, request, response, CORS, entry point | PR 2 | `curl http://localhost/CallMetrics_4TO/public/index.php/` | Apache VirtualHost with rewrite | Router.php, Request.php, Response.php, TenantContext.php, CorsMiddleware.php, Controller.php, index.php |
| 3 | Auth core: JWT, auth middleware, models | PR 3 | `curl -X POST .../api/auth/login` | Full login flow | JwtHelper.php, AuthMiddleware.php, BaseModel.php, User.php, Tenant.php |
| 4 | Auth endpoints + routes wiring | PR 4 | `curl -X POST .../api/auth/login -d '{"email":"admin@callmetrics.com","password":"admin123"}'` | 6 auth endpoint tests | AuthController.php, routes.php |

---

## Phase 1: Project Setup (Foundation)

- [x] 1.1 Create `composer.json` with PSR-4 autoload (`CallMetrics\` → `src/`), require `firebase/php-jwt ^7.0` (advisory blocks v6.10-6.11), `vlucas/phpdotenv ^5.6`, `ext-pdo`, `ext-json`. Run `composer install`. **Acceptance**: `vendor/autoload.php` exists, `composer.lock` created. **~17 lines**. **Deps**: None.

- [x] 1.2 Create `.env` with DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, JWT_SECRET, JWT_ACCESS_EXPIRY (900), JWT_REFRESH_EXPIRY (604800), APP_ENV, APP_DEBUG, APP_URL, LOGIN_MAX_ATTEMPTS (5), LOGIN_LOCKOUT_MINUTES (15). **Acceptance**: `php -r "require 'vendor/autoload.php'; Dotenv\Dotenv::createImmutable('.')->load(); echo $_ENV['DB_HOST'];"` prints `127.0.0.1`. **~13 lines**. **Deps**: 1.1.

- [x] 1.3 Create `sql/schema.sql` with `callmetrics` database, 4 tables (empresas, usuarios, refresh_tokens, login_attempts), indexes, foreign keys, and seed SUPER_ADMIN (`admin@callmetrics.com` / `admin123` bcrypt hash cost 12). **Acceptance**: schema validated; MySQL execution deferred (server not running). **~107 lines**. **Deps**: None.

- [x] 1.4 Create `config/database.php` returning `[host, port, name, user, pass, charset]` from `$_ENV` with defaults. **Acceptance**: `php -r "require 'config/database.php';"` returns array with correct keys. **~11 lines**. **Deps**: 1.2.

- [x] 1.5 Create `src/Core/Config.php` — static `get($key, $default)`, `jwtSecret()`, `jwtAccessExpiry()`, `jwtRefreshExpiry()`, `isDev()`. **Acceptance**: `Config::get('DB_PORT', 3306)` returns correct value from `.env`. **~47 lines**. **Deps**: 1.2.

- [x] 1.6 Create `src/Core/Database.php` — PDO singleton: `getInstance()`, `fetchAll()`, `fetchOne()`, `insert()` (returns lastInsertId), `execute()` (returns rowCount), `count()`. PDO settings: ERRMODE_EXCEPTION, FETCH_ASSOC, EMULATE_PREPARES=false. **Acceptance**: `Database::getInstance()->fetchOne("SELECT 1 as t")` returns `['t' => 1]`. **~104 lines**. **Deps**: 1.4, 1.5.

## Phase 2: Core HTTP Infrastructure

- [x] 2.1 Create `src/Core/Router.php` — `match($method, $path)` with `{param}` placeholder support. Returns `['handler', 'auth', 'role', 'params']` or null. **Acceptance**: Route with `{id}` pattern matches and extracts params; method mismatch returns null. **~50 lines**. **Deps**: None.

- [x] 2.2 Create `src/Core/Request.php` — `fromGlobals()` parsing `$_SERVER`, `$_GET`, `php://input` JSON, normalized headers. Methods: `method()`, `path()`, `query()`, `body()`, `header()`, `input()`, `param()`, `all()`, `setRouteParams()`, `tenantId()`. **Acceptance**: POST with JSON body → `$request->input('email')` returns value. **~80 lines**. **Deps**: 1.5.

- [x] 2.3 Create `src/Core/Response.php` — static `json()`, `ok()`, `created()`, `error()`, `unauthorized()`, `forbidden()`, `notFound()`, `noContent()`, `paginated()`. All call `exit` after output. Paginated adds `meta.totalPages`. **Acceptance**: `Response::paginated([], 2, 10, 25)` outputs JSON with `meta.totalPages=3`. **~80 lines**. **Deps**: None.

- [x] 2.4 Create `src/Core/TenantContext.php` — static `set($tenantId, $userId, $role)`, `get()`, `getUserId()`, `getRole()`, `isSuperAdmin()`, `isAdmin()`, `clear()`. `isAdmin()` returns true for SUPER_ADMIN and ADMIN_TENANT. **Acceptance**: `set(1,1,'SUPER_ADMIN'); isSuperAdmin()` returns true. **~40 lines**. **Deps**: None.

- [x] 2.5 Create `src/Http/Middleware/CorsMiddleware.php` — static `handle()` sets CORS headers, OPTIONS responds 204 + exit. **Acceptance**: OPTIONS request → 204 status, script exits. **~20 lines**. **Deps**: None.

- [x] 2.6 Create `src/Http/Controllers/Controller.php` — abstract base class, no methods. **Acceptance**: `AuthController extends Controller` instantiates without error. **~10 lines**. **Deps**: None.

- [x] 2.7 Create `public/index.php` — Front Controller: autoload → Dotenv → routes → Router → Request → CORS → AuthMiddleware (if auth) → Controller dispatch. Route not found → 404. **Acceptance**: `GET /` via Apache returns JSON response; `GET /api/nonexistent` returns 404. **~40 lines**. **Deps**: 2.1, 2.2, 2.3, 2.5.

## Phase 3: Models + Auth Core

- [x] 3.1 Create `src/Models/BaseModel.php` — abstract with `$table`, `$tenantScoped`. Methods: `findAll()` (auto tenant filter), `count()`, `find()`, `findBy()` (global), `create()`, `update()`, `delete()`, `paginate()` (returns `['data', 'total']`). Tenant filter skipped for SUPER_ADMIN. **Acceptance**: `User::findAll()` with OPERADOR context includes `WHERE tenant_id = :tenant_id`; SUPER_ADMIN context has no filter. **~230 lines**. **Deps**: 1.6, 2.4.

- [x] 3.2 Create `src/Core/JwtHelper.php` — static `generateAccessToken($userId, $tenantId, $role, $email)`, `generateRefreshToken($userId)`, `decode($token)`, `hash($token)` (SHA-256). Access: 15min, Refresh: 7d. Uses `firebase/php-jwt`. **Acceptance**: Generate then decode token → claims match input; `hash()` returns 64-char hex. **~70 lines**. **Deps**: 1.5.

- [x] 3.3 Create `src/Http/Middleware/AuthMiddleware.php` — static `handle($requiredRole)` extracts Bearer via regex, decodes JWT, verifies `type=access`, sets TenantContext, checks role hierarchy (SUPER_ADMIN=4, ADMIN_TENANT=3, SUPERVISOR=2, OPERADOR=1). **Acceptance**: Valid access token → TenantContext set; missing header → 401; wrong type → 401. **~70 lines**. **Deps**: 3.2, 2.3, 2.4.

- [x] 3.4 Create `src/Models/User.php` extends BaseModel — `$table='usuarios'`, `$tenantScoped=true`. Methods: `findByEmail()` (global), `createWithPassword()` (bcrypt cost 12), `updateWithPassword()`, `verifyPassword()`, `countLoginAttempts()`, `logLoginAttempt()`, `cleanOldAttempts()`. **Acceptance**: `createWithPassword(['password'=>'test'])` stores bcrypt hash; `countLoginAttempts()` returns correct count within window. **~100 lines**. **Deps**: 3.1.

- [x] 3.5 Create `src/Models/Tenant.php` extends BaseModel — `$table='empresas'`, `$tenantScoped=false`. Methods: `findByNit()`, `nitExists()`, `countUsers()`. **Acceptance**: `Tenant::find(1)` returns empresa or null. **~40 lines**. **Deps**: 3.1.

## Phase 4: Auth Endpoints + Integration

- [x] 4.1 Create `src/Http/Controllers/AuthController.php` — extends Controller. Methods: `login()` (rate limit, credentials, tokens, store refresh hash), `refresh()` (rotation: revoke old, generate new), `logout()` (revoke refresh token), `me()` (user data + empresa name), `changePassword()` (verify current, min 6 chars), `primerIngreso()` (find by email, verify primer_ingreso=1, update, auto-login). **Acceptance**: Login returns tokens; refresh rotates; me returns user without password_hash; 5 failed logins → 429. **~300 lines**. **Deps**: 3.2, 3.3, 3.4, 3.5, 2.3.

- [x] 4.2 Create `config/routes.php` — 6 auth routes: POST login (auth=false), POST refresh (auth=false), POST logout (auth=true), GET me (auth=true), PUT password (auth=true), PUT primer-ingreso (auth=false). **Acceptance**: Router resolves all 6 routes correctly; `GET /api/auth/me` with valid JWT reaches AuthController@me. **~30 lines**. **Deps**: 2.1, 4.1.

- [x] 4.3 Final integration test — run `curl` against all 6 endpoints: login, refresh, logout, me, password, primer-ingreso. Verify token rotation, rate limiting (6th attempt → 429), role hierarchy, tenant isolation. **Acceptance**: All 6 curl tests pass per spec scenarios. **~0 lines (verification only)**. **Deps**: 4.2. *(Reconciled at archive time — stale checkbox; orchestrator confirmed verification PASS)*

---

## Implementation Order

```
PR 1 (Foundation): 1.1 → 1.2 → 1.3 → 1.4 → 1.5 → 1.6
PR 2 (Core HTTP):  2.1 → 2.2 → 2.3 → 2.4 → 2.5 → 2.6 → 2.7
PR 3 (Auth Core):  3.1 → 3.2 → 3.3 → 3.4 → 3.5
PR 4 (Endpoints):  4.1 → 4.2 → 4.3
```

## Files Created (20 total)

```
composer.json                          (PR 1)
.env                                   (PR 1)
sql/schema.sql                         (PR 1)
config/database.php                    (PR 1)
config/routes.php                      (PR 4)
public/index.php                       (PR 2)
src/Core/Config.php                    (PR 1)
src/Core/Database.php                  (PR 1)
src/Core/Router.php                    (PR 2)
src/Core/Request.php                   (PR 2)
src/Core/Response.php                  (PR 2)
src/Core/TenantContext.php             (PR 2)
src/Core/JwtHelper.php                 (PR 3)
src/Http/Middleware/CorsMiddleware.php (PR 2)
src/Http/Middleware/AuthMiddleware.php (PR 3)
src/Http/Controllers/Controller.php   (PR 2)
src/Http/Controllers/AuthController.php (PR 4)
src/Models/BaseModel.php              (PR 3)
src/Models/User.php                    (PR 3)
src/Models/Tenant.php                  (PR 3)
```
