# Proposal: Backend Phase 0 (Infrastructure) + Phase 1 (Authentication)

## Intent

Greenfield PHP vanilla backend for CallMetrics. Phases 0-1 establish the core infrastructure (Front Controller, Router, DB, CORS) and the JWT authentication system with multi-tenant isolation. No backend code exists yet — only the frontend PHP at `/frontend`. The frontend currently uses mock data; this backend will replace those mocks with real API endpoints.

## Scope

### In Scope
- **composer.json** with firebase/php-jwt, vlucas/phpdotenv
- **.env** with DB, JWT, App config
- **sql/schema.sql** (MySQL 8): empresas, usuarios, refresh_tokens, login_attempts
- **public/index.php** (Front Controller)
- **Core**: Config, Database (PDO singleton), Router, Request, Response, TenantContext
- **Middleware**: CorsMiddleware, AuthMiddleware (JWT + role hierarchy)
- **Models**: BaseModel (Active Record + auto tenant filtering), User (findByEmail, createWithPassword, verifyPassword, rate limiting)
- **Controllers**: AuthController (login, refresh, logout, me, changePassword, primerIngreso)
- **config/**: database.php, routes.php
- **JwtHelper**: generate access/refresh tokens, decode, SHA-256 hash

### Out of Scope
- Phase 2-5: Tenant CRUD, User CRUD, Dashboard, Call Logs, Call Metrics
- AdminMiddleware (will come in Phase 2)
- Frontend integration (frontend remains untouched in this phase)
- Automated test suite (manual curl-based testing)

## Capabilities

### New Capabilities
- `backend-infrastructure`: Front Controller routing, PDO database singleton, CORS handling, request/response abstraction, multi-tenant context, env-based configuration
- `backend-auth-jwt`: JWT authentication with access/refresh token rotation, bcrypt password hashing, role-based access control (SUPER_ADMIN > ADMIN_TENANT > SUPERVISOR > OPERADOR), rate limiting (5 attempts/15min), first-login password change flow

### Modified Capabilities
None — greenfield project, no existing specs.

## Approach

Follow the architecture defined in `BACKEND_PHP_PLAN.md` exactly. Front Controller pattern with PSR-4 autoloading under `CallMetrics\` namespace. Multi-tenancy via `tenant_id` column — BaseModel auto-filters queries except for SUPER_ADMIN. JWT access tokens (15min) + refresh tokens (7d) with rotation on each refresh. Rate limiting via `login_attempts` table with IP+email tracking.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `composer.json` | New | Dependencies and PSR-4 autoload config |
| `.env` | New | Environment configuration |
| `sql/schema.sql` | New | MySQL 8 schema with 4 tables |
| `public/index.php` | New | Front Controller entry point |
| `src/Core/` | New | Config, Database, Router, Request, Response, TenantContext, JwtHelper |
| `src/Http/Middleware/` | New | CorsMiddleware, AuthMiddleware |
| `src/Http/Controllers/` | New | AuthController |
| `src/Models/` | New | BaseModel, User |
| `config/` | New | database.php, routes.php |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| LAMPP MySQL version mismatch with schema syntax | Low | Schema uses standard MySQL 8 features only |
| JWT secret hardcoded in .env defaults | Medium | Ship with clear placeholder; enforce env override |
| Rate limiting table grows unbounded | Low | User::cleanOldAttempts() runs on login; can add cron later |
| TenantContext static state leaks across requests | None | PHP per-request lifecycle — statics reset naturally |

## Rollback Plan

1. Remove all created files (the `src/`, `public/`, `config/`, `sql/`, `.env`, `composer.json`)
2. Run `rm -rf vendor/` to remove Composer dependencies
3. No data loss — database schema is additive; DROP TABLE statements can be run manually
4. Frontend remains untouched — no integration risk

## Dependencies

- PHP 8.1+ with PDO MySQL extension
- MySQL 8 (via LAMPP)
- Composer for dependency installation
- `composer install` required after file creation

## Success Criteria

- [ ] `composer install` succeeds without errors
- [ ] `POST /api/auth/login` with valid credentials returns access + refresh tokens
- [ ] `POST /api/auth/refresh` rotates tokens correctly
- [ ] `GET /api/auth/me` with valid JWT returns user data
- [ ] `PUT /api/auth/password` changes password
- [ ] `PUT /api/auth/primer-ingreso` completes first-login flow
- [ ] Rate limiting blocks after 5 failed attempts within 15 minutes
- [ ] Multi-tenant isolation: user A cannot see user B's data via BaseModel
- [ ] CORS headers present on all responses
- [ ] 401 returned for missing/invalid JWT on protected routes
