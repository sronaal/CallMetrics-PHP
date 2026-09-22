# CallMetrics — Documentación Técnica Completa

> Plataforma de observabilidad y monitoreo para servidores PBX Asterisk.
> Arquitectura multi-tenant con backend PHP 8.3, frontend server-rendered, WebSocket en tiempo real.

---

## Tabla de Contenidos

1. [Arquitectura General](#1-arquitectura-general)
2. [Stack Tecnológico](#2-stack-tecnológico)
3. [Estructura del Proyecto](#3-estructura-del-proyecto)
4. [Base de Datos](#4-base-de-datos)
5. [Backend — API REST](#5-backend--api-rest)
6. [Autenticación y Autorización](#6-autenticación-y-autorización)
7. [Multi-Tenancy](#7-multi-tenancy)
8. [WebSocket — Tiempo Real](#8-websocket--tiempo-real)
9. [Frontend — UI Server-Rendered](#9-frontend--ui-server-rendered)
10. [Ingesta de Datos (Agent Collector)](#10-ingesta-de-datos-agent-collector)
11. [Motor de Alertas](#11-motor-de-alertas)
12. [Endpoints API — Referencia Completa](#12-endpoints-api--referencia-completa)
13. [Modelos de Datos (ORM)](#13-modelos-de-datos-orm)
14. [Endpoints Frontend — Referencia](#14-endpoints-frontend--referencia)
15. [JavaScript — Módulos del Cliente](#15-javascript--módulos-del-cliente)
16. [Configuración y Despliegue](#16-configuración-y-despliegue)
17. [Seguridad](#17-seguridad)
18. [Guía de Desarrollo](#18-guía-de-desarrollo)

---

## 1. Arquitectura General

```
┌─────────────────────────────────────────────────────────────┐
│                        BROWSER                              │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────────────┐  │
│  │ Landing  │  │ Dashboard│  │ Call Center / CDR / Events│  │
│  └────┬─────┘  └────┬─────┘  └────────────┬─────────────┘  │
│       │              │                      │                │
│       │    api-proxy.php (reverse proxy)    │                │
│       │         ┌─────┴──────┐              │                │
│       │         │  JS fetch  │◄─────────────┘                │
│       │         └─────┬──────┘                               │
│       │               │                                      │
│       │    ws://localhost:8081 (WebSocket)                   │
│       │         ┌─────┴──────┐                               │
│       │         │  ws-client │                               │
└───────┼─────────┼────────────┼───────────────────────────────┘
        │         │            │
        ▼         ▼            ▼
┌─────────────────────────────────────────────────────────────┐
│                    APACHE (XAMPP :80)                        │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  frontend/ — Server-rendered PHP pages               │   │
│  │  ├─ AuthMiddleware (session + JWT refresh)           │   │
│  │  ├─ ApiClient → cURL → http://localhost:8080/api/*   │   │
│  │  └─ Layout/ob_start rendering                        │   │
│  └──────────────────────────────────────────────────────┘   │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              PHP BUILT-IN SERVER (:8080)                     │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  backend/public/index.php — Front Controller         │   │
│  │  ├─ CorsMiddleware                                   │   │
│  │  ├─ Router::match()                                  │   │
│  │  ├─ AuthMiddleware (JWT validation)                  │   │
│  │  └─ Controller->action(Request)                      │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  WebSocket Server (Ratchet :8081)                    │   │
│  │  ├─ Agent collectors (X-Agent-ID auth)               │   │
│  │  ├─ Frontend clients (channel subscribe)             │   │
│  │  └─ Pub/Sub: tenant, pbx, queue, dashboard channels  │   │
│  └──────────────────────────────────────────────────────┘   │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                  MariaDB (127.0.0.1:33060)                   │
│  callmetrics — 17 tablas, multi-tenant con tenant_id FK     │
└─────────────────────────────────────────────────────────────┘
```

### Flujo de una petición

1. **Browser** envía XHR/fetch → `api-proxy.php` (same-origin)
2. **api-proxy.php** reescribe URL → `http://localhost:8080/api/{path}` y transfiere headers/body
3. **index.php** (Front Controller) recibe la petición
4. **CorsMiddleware** maneja preflight OPTIONS y setea headers CORS
5. **Router** resuelve la ruta, extrae parámetros `{id}`
6. **AuthMiddleware** valida JWT Bearer, popula TenantContext, verifica rol
7. **Controller** ejecuta la lógica, consulta DB via BaseModel/Database
8. **Response** emite JSON con envelope `{ success, message, data, meta }`

---

## 2. Stack Tecnológico

| Capa | Tecnología | Versión |
|------|-----------|---------|
| **Backend API** | PHP (built-in server) | 8.3.6 |
| **Frontend Server** | Apache (XAMPP) | 2.4.58 |
| **Base de Datos** | MariaDB | 10.4+ |
| **Autenticación** | JWT (firebase/php-jwt) | v7, HS256 |
| **WebSocket** | Ratchet | PHP library |
| **Frontend UI** | PHP server-rendered + Bootstrap 5.3 | vanilla JS |
| **Gráficas** | Chart.js | 4.4.1 |
| **ORM** | Custom Active Record (BaseModel) | propio |
| **Routing** | Custom Router (regex) | propio |
| **Agent Collector** | Python / Spring (externo) | independiente |

---

## 3. Estructura del Proyecto

```
CallMetrics_4TO/
├── backend/
│   ├── config/
│   │   ├── database.php          # Configuración DB (host, port, name, user, pass)
│   │   └── routes.php            # Registro de 66 rutas API
│   ├── public/
│   │   ├── index.php             # Front Controller (entry point)
│   │   ├── .htaccess             # Apache URL rewriting
│   │   └── docs/
│   │       ├── index.html        # Swagger UI (spec embebido como JSON)
│   │       └── openapi.yaml      # OpenAPI 3.0.3 (51 endpoints, 62 schemas)
│   ├── sql/
│   │   ├── schema.sql            # Schema base (empresas, usuarios, auth)
│   │   ├── schema-phase6.sql     # Schema telephony (pbx, colas, agentes, CDR, eventos)
│   │   ├── schema-cdr-report.sql # Tablas CDR normalizadas
│   │   ├── seed.sql              # Datos demo iniciales
│   │   ├── seed-real.sql         # Datos demo realistas (18 llamadas, 5 reglas)
│   │   └── seed-cdr-report.sql   # 50 registros CDR report
│   ├── src/
│   │   ├── Core/
│   │   │   ├── Config.php        # Facade para .env
│   │   │   ├── Database.php      # Singleton PDO con prepared statements
│   │   │   ├── JwtHelper.php     # JWT generation, decode, hash
│   │   │   ├── Request.php       # HTTP request wrapper
│   │   │   ├── Response.php      # JSON response formatter
│   │   │   ├── Router.php        # Route matching con {param} extraction
│   │   │   └── TenantContext.php  # Per-request tenant/user/role state
│   │   ├── Http/
│   │   │   ├── Controllers/      # 13 controladores
│   │   │   └── Middleware/
│   │   │       ├── AuthMiddleware.php  # JWT + role hierarchy
│   │   │       └── CorsMiddleware.php  # CORS whitelist
│   │   ├── Models/               # 9 modelos Active Record
│   │   ├── Services/
│   │   │   └── AlertEngine.php   # Evaluación de reglas de alerta
│   │   └── WebSocket/
│   │       ├── Server.php        # Ratchet WebSocket server
│   │       └── EventBridge.php   # HTTP→WS singleton bridge
│   └── vendor/                   # Composer dependencies
│
├── frontend/
│   ├── index.php                 # Entry point → landing page
│   ├── api-proxy.php             # Reverse proxy: browser → backend API
│   ├── src/
│   │   ├── config.php            # Path constants, BASE_URL auto-detection
│   │   ├── core/
│   │   │   ├── Config.php        # Backend URL constants
│   │   │   ├── ApiClient.php     # Singleton cURL client
│   │   │   ├── ApiClientHelpers.php  # 35 convenience functions
│   │   │   ├── AuthMiddleware.php     # Session auth guard + auto-refresh
│   │   │   ├── Session.php            # PHP session + JWT storage
│   │   │   ├── helpers.php            # Formatting utilities
│   │   │   └── components.php         # Reusable HTML renderers
│   │   └── pages/                # 13 page views
│   │       ├── auth.php          # Login
│   │       ├── dashboard.php     # Main dashboard
│   │       ├── landing.php       # Marketing landing
│   │       ├── empresas.php      # Tenant CRUD
│   │       ├── users.php         # User CRUD
│   │       ├── agents.php        # Agent listing
│   │       ├── pbx.php           # PBX CRUD
│   │       ├── pbx-detalle.php   # PBX detail + heartbeats
│   │       ├── asterisk-events.php  # Event log viewer
│   │       └── callcenter/
│   │           ├── dashboard.php # CC operational dashboard
│   │           ├── cdr.php       # CDR reports (4 tabs)
│   │           ├── colas.php     # Queue listing
│   │           └── agentes.php   # CC agent listing
│   └── assets/
│       ├── css/                  # Theme, dashboard, callcenter styles
│       └── js/
│           ├── ws-client.js      # WebSocket client (auto-reconnect)
│           ├── dashboard.js      # Charts, live updates, KPI refresh
│           ├── cc.js             # Call Center tab switching + filter
│           ├── cdr.js            # CDR tabs + CSV export
│           ├── events.js         # Real-time event stream + fallback
│           ├── landing.js        # Landing animations
│           └── pbx.js            # PBX form validation + UUID reveal
│
├── openspec/                     # SDD artifacts (optional)
├── node_modules/                 # npm (js-yaml for docs build)
├── DOCUMENTACION_TECNICA.md      # Este archivo
└── .gitignore
```

---

## 4. Base de Datos

### 4.1 Tablas del Sistema (17 tablas)

| Tabla | Propósito | Tenant Scoped | Modelo |
|-------|-----------|:-------------:|--------|
| `empresas` | Tenants/empresas | ❌ | Tenant |
| `usuarios` | Usuarios del sistema | ✅ | User |
| `refresh_tokens` | Tokens de refresco JWT | ❌ | — |
| `login_attempts` | Rate limiting de login | ❌ | User (métodos) |
| `pbx` | Servidores PBX | ✅ | Pbx |
| `extensiones` | Extensiones SIP | ✅ | Extension |
| `colas` | Colas de llamadas | ✅ | Queue |
| `agentes` | Operadores telefónicos | ✅ | Agent |
| `llamadas_cdr` | CDR raw de Asterisk | ✅ | CallRecord |
| `eventos` | Eventos AMI/CEL/Health | ✅ | Event |
| `reglas_alerta` | Reglas de alerta | ✅ | AlertRule |
| `historial_alertas` | Log de alertas disparadas | ✅ | — |
| `cdr_llamadas` | CDR normalizado (reporte) | ✅ | — |
| `cdr_llamadas_real` | CDR con tiempos exactos | ✅ | — |
| `cdr_colas_resumen` | Estadísticas por cola | ✅ | — |
| `cdr_agentes_resumen` | Estadísticas por agente | ✅ | — |
| `cdr_estadisticas_colas` | Stats por llamada/cola | ✅ | — |

### 4.2 Diagrama de Relaciones

```
empresas (tenants)
  │
  ├── usuarios (tenant_id FK)
  │     └── refresh_tokens (user_id FK)
  │
  ├── pbx (tenant_id FK)
  │     ├── extensiones (tenant_id + pbx_id FK)
  │     │     └── agentes.extension_id FK
  │     ├── colas (tenant_id + pbx_id FK)
  │     │     └── agentes.cola_id FK
  │     ├── llamadas_cdr (tenant_id + pbx_id FK)
  │     ├── eventos (tenant_id + pbx_id FK)
  │     ├── cdr_llamadas (tenant_id + pbx_id FK)
  │     ├── cdr_llamadas_real (tenant_id + pbx_id FK)
  │     ├── cdr_colas_resumen (tenant_id + pbx_id FK)
  │     ├── cdr_agentes_resumen (tenant_id + pbx_id FK)
  │     └── cdr_estadisticas_colas (tenant_id + pbx_id FK)
  │
  ├── agentes (tenant_id FK, usuario_id FK, extension_id FK, cola_id FK)
  │
  ├── reglas_alerta (tenant_id FK)
  │     └── historial_alertas (tenant_id + regla_id FK)
  │
  └── login_attempts (sin FK — solo email/IP tracking)
```

### 4.3 Esquemas de Tablas Clave

#### `empresas` (Tenants)

```sql
CREATE TABLE empresas (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(150) NOT NULL,
    nit          VARCHAR(20) NOT NULL UNIQUE,
    email        VARCHAR(150) NOT NULL,
    telefono     VARCHAR(30) NULL,
    direccion    VARCHAR(255) NULL,
    plan         ENUM('FREE','BASIC','PRO','ENTERPRISE') DEFAULT 'FREE',
    activo       TINYINT(1) DEFAULT 1,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### `usuarios`

```sql
CREATE TABLE usuarios (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id      INT UNSIGNED NULL,
    nombre         VARCHAR(150) NOT NULL,
    email          VARCHAR(150) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    rol            ENUM('SUPER_ADMIN','ADMIN_TENANT','SUPERVISOR','OPERADOR') DEFAULT 'OPERADOR',
    extension      VARCHAR(20) NULL,
    activo         TINYINT(1) DEFAULT 1,
    primer_ingreso TINYINT(1) DEFAULT 1,
    ultimo_login   DATETIME NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuario_tenant_email (tenant_id, email),
    FOREIGN KEY fk_usuario_tenant (tenant_id) REFERENCES empresas(id) ON DELETE SET NULL
);
```

#### `llamadas_cdr` (Call Detail Records)

```sql
CREATE TABLE llamadas_cdr (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id          INT UNSIGNED NOT NULL,
    pbx_id             INT UNSIGNED NOT NULL,
    callid             VARCHAR(80) NOT NULL,
    extension_origen   VARCHAR(20) NULL,
    extension_destino  VARCHAR(20) NULL,
    numero_origen      VARCHAR(50) NULL,
    numero_destino     VARCHAR(50) NULL,
    contexto           VARCHAR(50) NULL,
    duracion           INT DEFAULT 0,
    billable_seconds   INT DEFAULT 0,
    estado             ENUM('ANSWERED','NOANSWER','BUSY','FAILED','CANCELLED') DEFAULT 'FAILED',
    inicio_llamada     DATETIME NOT NULL,
    fin_llamada        DATETIME NULL,
    grabacion_url      VARCHAR(500) NULL,
    channel_origen     VARCHAR(100) NULL,
    channel_destino    VARCHAR(100) NULL,
    created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cdr_tenant (tenant_id),
    INDEX idx_cdr_pbx (pbx_id),
    INDEX idx_cdr_callid (callid),
    INDEX idx_cdr_estado (estado),
    INDEX idx_cdr_inicio (inicio_llamada)
);
```

#### `reglas_alerta`

```sql
CREATE TABLE reglas_alerta (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    nombre          VARCHAR(100) NOT NULL,
    tipo            ENUM('LLAMADAS_PERDIDAS','CPU','RAM','COLA_SATURADA','TRONCAL_CAIDA') NOT NULL,
    condicion       ENUM('MAYOR','MENOR','IGUAL') DEFAULT 'MAYOR',
    umbral          DECIMAL(10,2) NOT NULL,
    unidad          VARCHAR(20) NULL,
    notificar_email TINYINT(1) DEFAULT 1,
    notificar_web   TINYINT(1) DEFAULT 1,
    activo          TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### `cdr_llamadas` (CDR Report Normalizado)

```sql
CREATE TABLE cdr_llamadas (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id             INT UNSIGNED NOT NULL,
    pbx_id                INT UNSIGNED NOT NULL,
    linkedid              VARCHAR(80) NOT NULL,
    fecha_inicio          DATETIME NULL,
    numero_origen         VARCHAR(80) NULL,
    destino_inicial       VARCHAR(80) NULL,
    paso_por_cola         VARCHAR(3) NULL,
    nombre_cola           VARCHAR(128) NULL,
    extension_agente      VARCHAR(80) NULL,
    nombre_agente         VARCHAR(80) NULL,
    tiempo_conversacion   VARCHAR(20) NULL,
    tiempo_timbrado       VARCHAR(20) NULL,
    estado_final          VARCHAR(20) NULL,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cdr_llamadas (tenant_id, linkedid)
);
```

---

## 5. Backend — API REST

### 5.1 Entry Point (`backend/public/index.php`)

```php
// Flujo de ejecución:
1. require vendor/autoload.php
2. Dotenv::createImmutable(__DIR__ . '/')  // carga .env
3. $routes = require config/routes.php
4. $request = Request::fromGlobals()
5. CorsMiddleware::handle()                 // OPTIONS preflight + CORS headers
6. Sirve /docs y /docs/openapi.yaml estáticos
7. $match = $router->match($method, $path)  // resuelve ruta con {params}
8. AuthMiddleware::handle($role)             // JWT + role check
9. Controller->$action($request)            // dispatch
```

### 5.2 Configuración (`.env`)

```env
# Database
DB_HOST=127.0.0.1
DB_PORT=33060
DB_NAME=callmetrics
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

# JWT
JWT_SECRET=tu-secreto-aqui
JWT_ACCESS_EXPIRY=900        # 15 minutos
JWT_REFRESH_EXPIRY=604800    # 7 días

# App
APP_ENV=development
APP_PORT=8080
```

### 5.3 Router

El router convierte patrones de ruta con `{param}` a regex named capture groups:

```php
// Input:  /api/tenants/{id}
// Output: #^/api/tenants/(?P<id>[^/]+)$#

$match = $router->match('GET', '/api/tenants/42');
// Retorna:
[
    'handler' => 'TenantController@show',
    'auth'    => true,
    'role'    => 'SUPER_ADMIN',
    'params'  => ['id' => '42']
]
```

### 5.4 Response Format

Todas las respuestas siguen el envelope estándar:

```json
{
    "success": true,
    "message": "Operación exitosa",
    "data": { ... },
    "meta": {
        "page": 0,
        "size": 10,
        "total": 150,
        "totalPages": 15
    }
}
```

Códigos de respuesta:
| Código | Significado |
|--------|------------|
| 200 | OK |
| 201 | Creado |
| 204 | Sin contenido |
| 400 | Bad request / validación |
| 401 | No autenticado |
| 403 | Sin permisos |
| 404 | No encontrado |
| 500 | Error interno |

---

## 6. Autenticación y Autorización

### 6.1 JWT Dual-Token Architecture

| Token | TTL | Uso | Almacenamiento |
|-------|-----|-----|----------------|
| Access Token | 15 min | Peticiones API | `$_SESSION['access_token']` + `Authorization: Bearer` header |
| Refresh Token | 7 días | Renovación automática | `$_SESSION['refresh_token']` + tabla `refresh_tokens` (hash SHA-256) |

### 6.2 Access Token Claims

```json
{
    "iss": "callmetrics",
    "iat": 1790054575,
    "exp": 1790055475,
    "sub": 17,
    "tid": 0,
    "role": "SUPER_ADMIN",
    "email": "admin@callmetrics.com",
    "type": "access"
}
```

### 6.3 Jerarquía de Roles

```
SUPER_ADMIN (4) > ADMIN_TENANT (3) > SUPERVISOR (2) > OPERADOR (1)
```

Un usuario con rol superior tiene acceso automático a todos los endpoints de roles inferiores.

### 6.4 Endpoints de Auth

| Método | Ruta | Auth | Descripción |
|--------|------|:----:|-------------|
| POST | `/api/auth/login` | ❌ | Login con rate limiting (5 intentos/15 min) |
| POST | `/api/auth/refresh` | ❌ | Rotación de refresh token (single-use) |
| POST | `/api/auth/logout` | ✅ | Revoca refresh token |
| GET | `/api/auth/me` | ✅ | Perfil del usuario actual |
| PUT | `/api/auth/password` | ✅ | Cambio de contraseña |
| PUT | `/api/auth/primer-ingreso` | ❌ | Primer login (configura contraseña) |

### 6.5 Flujo de Login

```
1. Browser envía POST /api/auth/login {email, password}
2. AuthController verifica rate limiting (login_attempts table)
3. User::findByEmail() busca en TODOS los tenants (sin filtro)
4. User::verifyPassword() valida bcrypt hash
5. Se generan access + refresh tokens
6. Se guarda refresh token hash en refresh_tokens table
7. Se retorna {accessToken, refreshToken, user}
8. Frontend Session::login() almacena en $_SESSION
9. Subsequent requests: ApiClient adjunta Authorization: Bearer {token}
10. Auto-refresh: AuthMiddleware::refreshIfNeeded() renueva antes de expirar
```

---

## 7. Multi-Tenancy

### 7.1 Estrategia

El multi-tenancy se implementa a nivel de base de datos con `tenant_id` en cada tabla (excepto `empresas`, `refresh_tokens`, `login_attempts`). El `BaseModel` inyecta automáticamente `WHERE tenant_id = :tenant_id` en todas las queries.

### 7.2 TenantContext (Per-Request State)

```php
TenantContext::set($tenantId, $userId, $role);  // Seteado por AuthMiddleware

// Lectura:
TenantContext::get();           // tenant_id (int|null)
TenantContext::getUserId();     // user_id
TenantContext::getRole();       // role string
TenantContext::isSuperAdmin();  // role === 'SUPER_ADMIN'

// Resolución para SUPER_ADMIN (tid = 0):
TenantContext::resolveTenantId($request);
// Busca: body['tenant_id'] → query['tenant_id'] → null (global)

TenantContext::resolveTenantIdForWrite($request);
// Requiere: body['tenant_id'] > 0 o query['tenant_id'] > 0
```

### 7.3 BaseModel Tenant Scoping

```php
// En BaseModel::applyTenantFilter():
if ($this->tenantScoped && !$isSuperAdmin && $tenantId !== null) {
    $wheres[] = 'tenant_id = :tenant_id';
    $params[':tenant_id'] = $tenantId;
}

// Excepción: findBy() NO aplica tenant filter
// (necesario para login, auth de agentes, etc.)
```

### 7.4 Comportamiento por Rol

| Rol | Visibilidad | Escritura |
|-----|-------------|-----------|
| SUPER_ADMIN | Todos los tenants (global) | Cualquier tenant (requiere `tenant_id`) |
| ADMIN_TENANT | Solo su tenant | Solo su tenant |
| SUPERVISOR | Solo su tenant (lectura) | Solo su tenant (limitado) |
| OPERADOR | Solo su tenant (lectura) | Solo su tenant (limitado) |

---

## 8. WebSocket — Tiempo Real

### 8.1 Arquitectura

```
┌──────────────────┐     ┌──────────────────────┐
│  Agent Collector │────▶│  WebSocket Server    │
│  (Python/Spring) │     │  (Ratchet :8081)     │
│  X-Agent-ID auth │     │                      │
└──────────────────┘     │  ┌────────────────┐  │
                         │  │ Pub/Sub Engine  │  │
┌──────────────────┐     │  │  ├─ tenant_{id} │  │
│  Frontend Client │◀────│  │  ├─ pbx_{id}    │  │
│  (ws-client.js)  │     │  │  ├─ colas_{id}  │  │
│  subscribe/unsub │     │  │  └─ dashboard   │  │
└──────────────────┘     │  └────────────────┘  │
                         └──────────────────────┘
```

### 8.2 Canales

| Canal | Propósito | Suscriptores |
|-------|-----------|-------------|
| `tenant_{id}` | Updates generales del tenant | Frontend clients |
| `pbx_{id}` | Métricas y salud del PBX | Frontend clients |
| `colas_{id}` | Estado de colas | Frontend clients |
| `dashboard` | KPIs globales | Frontend clients |
| `agent_{id}` | Canal privado del agente (ACKs) | Agent collector |

### 8.3 Autenticación de Agentes

```php
// En Server::onOpen():
$agentId = $conn->httpRequest->getHeader('X-Agent-ID');
$pbx = Pbx::findByAgenteId($agentId);
if (!$pbx || !$pbx['activo']) {
    $conn->close();  // Rechaza conexión
    return;
}
// Auto-suscribe a canal agent_{id}
```

### 8.4 Mensajes del Agente

| `tipo` | Handler | Acción |
|--------|---------|--------|
| `evento_llamada` / `llamada_completa` | `processAgentCallEvent()` | Almacena + broadcast a tenant |
| `evento_queue` | `processAgentQueueEvent()` | Almacena + broadcast a colas + tenant |
| `cdr_completo` | `processAgentCdrEvent()` | Almacena + broadcast `call_ended` |
| `heartbeat` | `processAgentHeartbeat()` | Actualiza `ultimo_heartbeat` + broadcast health |
| `evento_sip` / `sip_log` / `sistema` | `storeAgentEvent()` | Solo almacena |

### 8.5 EventBridge (HTTP→WS Bridge)

Los controllers HTTP usan `EventBridge` para enviar eventos al WebSocket sin importar `Server` directamente:

```php
EventBridge::getInstance()->broadcastCallEvent($tenantId, 'call_started', $data);
EventBridge::getInstance()->broadcastPbxHealth($pbxId, $metrics);
EventBridge::getInstance()->broadcastQueueUpdate($queueId, $data);
EventBridge::getInstance()->broadcastDashboard($kpiData);
```

---

## 9. Frontend — UI Server-Rendered

### 9.1 Patrón de Rendering

El frontend usa **PHP server-rendered** con el patrón `ob_start()`/`ob_get_clean()` para composición de layouts:

```php
// Cada página:
ob_start();
// ... HTML/PHP logic ...
$content = ob_get_clean();
require LAYOUT_PATH . '/main.php';  // inyecta $content
```

### 9.2 Flujo de Datos

```
┌─────────────────────────────────────────────────────┐
│  PHP Page (server-side)                             │
│  ├─ AuthMiddleware::check()  → redirect si no auth  │
│  ├─ api_get_dashboard()      → cURL a backend       │
│  ├─ api_get_llamadas(0, 50)  → cURL a backend       │
│  └─ Render HTML con datos PHP embebidos             │
│                                                     │
│  <script>                                           │
│  ├─ ws-client.js  → WebSocket real-time             │
│  ├─ dashboard.js  → Chart.js + live updates         │
│  └─ fetch() → api-proxy.php → backend (for updates) │
│  </script>                                          │
└─────────────────────────────────────────────────────┘
```

### 9.3 Gestión de Sesión

```php
Session::start();                    // session_start()
Session::login($access, $refresh, $user);  // Almacena en $_SESSION
Session::isAuthenticated();          // Token existe + dentro de timeout
Session::token();                    // Access token para cURL
Session::refreshToken();             // Refresh token
Session::user();                     // Array del usuario
Session::logout();                   // session_destroy()
```

**Timeout:** 15 minutos de inactividad (`SESSION_TIMEOUT = 900`).

### 9.4 Auto-Refresh de Token

```php
// AuthMiddleware::refreshIfNeeded() se ejecuta en cada página protegida:
if (tokenExpiraEnMenosDe(5 minutos)) {
    $response = api_post('/auth/refresh', ['refreshToken' => $refreshToken]);
    if ($response['success']) {
        Session::login($nuevoAccessToken, $nuevoRefreshToken, $user);
    } else {
        Session::logout();
        redirect('auth.php');
    }
}
```

### 9.5 api-proxy.php (Reverse Proxy)

El proxy permite que el browser JS haga fetch a la API sin problemas de CORS/mixed-content:

```
Browser JS fetch('/api-proxy.php/dashboard/summary')
    → api-proxy.php reescribe a http://localhost:8080/api/dashboard/summary
    → Transfiere headers (Authorization, Content-Type)
    → Retorna respuesta con status code y headers originales
```

**Seguridad del proxy:**
- Bloquea `auth/login`, `auth/refresh`, `auth/primer-ingreso` (403)
- Valida `Content-Type: application/json` en POST/PUT/PATCH (415)
- Elimina headers hop-by-hop (transfer-encoding, connection, content-encoding)

---

## 10. Ingesta de Datos (Agent Collector)

### 10.1 Arquitectura

```
┌─────────────────┐     HTTP POST      ┌──────────────────┐
│  Agent Python   │ ──────────────────▶ │  AgentIngestCtrl │
│  (asterisk-agi) │  /api/agent/cdr    │                  │
│                 │  /api/agent/events  │  Valida, almacena│
│  cada 30s       │  /api/agent/metrics│  Evalúa alertas  │
│  heartbeat      │  /api/agent/heartbe│  Broadcast WS    │
└─────────────────┘                    └──────────────────┘
```

### 10.2 Endpoints de Ingesta (Sin Auth JWT)

| Método | Ruta | Propósito |
|--------|------|-----------|
| POST | `/api/agent/heartbeat` | Heartbeat del PBX (actualiza estado) |
| POST | `/api/agent/cdr` | Batch de CDR raw (llamadas individuales) |
| POST | `/api/agent/cdr-report` | Reporte CDR anidado (5 datasets) |
| POST | `/api/agent/events` | Batch de eventos AMI/CEL |
| POST | `/api/agent/metrics` | Métricas de salud (CPU, RAM, disco) |

Rutas duplicadas con prefijo `/api/v1/agent/` para compatibilidad con agentes Spring.

### 10.3 Autenticación del Agente

```php
// AgentIngestController::authenticateAgent():
$agentId = $request->header('X-Agent-ID');
$pbx = Pbx::findByAgenteId($agentId);
if (!$pbx || !$pbx['activo']) {
    Response::error('Agente no autorizado', 401);
}
// Setea TenantContext con el tenant del PBX
```

### 10.4 Payload del CDR Report (`/api/agent/cdr-report`)

```json
{
    "pbx_id": 10,
    "tenant_id": 14,
    "llamadas": [
        {
            "linkedid": "abc123",
            "fecha_inicio": "2026-09-22 10:30:00",
            "numero_origen": "3001234567",
            "destino_inicial": "3009876543",
            "paso_por_cola": "yes",
            "nombre_cola": "Ventas",
            "extension_agente": "1001",
            "nombre_agente": "Carlos",
            "tiempo_conversacion": "120",
            "tiempo_timbrado": "15",
            "estado_final": "ANSWERED"
        }
    ],
    "colas_resumen": [ ... ],
    "agentes_resumen": [ ... ],
    "estadisticas_colas": [ ... ],
    "llamadas_real": [ ... ]
}
```

---

## 11. Motor de Alertas

### 11.1 Tipos de Regla

| `tipo` | Métrica | Fuente |
|--------|---------|--------|
| `LLAMADAS_PERDIDAS` | Count de llamadas NO contestadas en última hora | `llamadas_cdr` |
| `CPU` | Porcentaje de uso CPU | `eventos` (HEALTH/metrics) |
| `RAM` | Porcentaje de uso memoria | `eventos` (HEALTH/metrics) |
| `COLA_SATURADA` | Promedio de llamadas en espera | `colas` |
| `TRONCAL_CAIDA` | Estado de troncal | Pendiente |

### 11.2 Condiciones

| Condición | Evaluación |
|-----------|-----------|
| `MAYOR` | `valor > umbral` |
| `MENOR` | `valor < umbral` |
| `IGUAL` | `abs(valor - umbral) < 0.001` |

### 11.3 Severidad

```php
$nivel = ($valor > ($umbral * 1.5)) ? 'CRITICAL' : 'WARNING';
// CRITICAL si el valor supera el umbral en 50%, WARNING si solo lo supera
```

### 11.4 Evaluación

```php
$engine = new AlertEngine();
$alertas = $engine->evaluate($tenantId);
// Retorna: [{id, regla, valor, nivel, mensaje}, ...]
```

---

## 12. Endpoints API — Referencia Completa

### 12.1 Auth (6 rutas)

| Método | Ruta | Auth | Rol Mín. | Descripción |
|--------|------|:----:|----------|-------------|
| POST | `/api/auth/login` | ❌ | — | Login (rate limited) |
| POST | `/api/auth/refresh` | ❌ | — | Renovar tokens |
| POST | `/api/auth/logout` | ✅ | — | Cerrar sesión |
| GET | `/api/auth/me` | ✅ | — | Perfil actual |
| PUT | `/api/auth/password` | ✅ | — | Cambiar contraseña |
| PUT | `/api/auth/primer-ingreso` | ❌ | — | Primer login |

### 12.2 Tenants (6 rutas)

| Método | Ruta | Auth | Rol Mín. | Descripción |
|--------|------|:----:|----------|-------------|
| GET | `/api/tenants` | ✅ | SUPER_ADMIN | Lista paginada + search |
| GET | `/api/tenants/{id}` | ✅ | SUPER_ADMIN | Detalle |
| POST | `/api/tenants` | ✅ | SUPER_ADMIN | Crear |
| PUT | `/api/tenants/{id}` | ✅ | SUPER_ADMIN | Actualizar |
| DELETE | `/api/tenants/{id}` | ✅ | SUPER_ADMIN | Eliminar |
| PATCH | `/api/tenants/{id}/toggle` | ✅ | SUPER_ADMIN | Toggle activo |

### 12.3 Usuarios (6 rutas)

| Método | Ruta | Auth | Rol Mín. | Descripción |
|--------|------|:----:|----------|-------------|
| GET | `/api/usuarios` | ✅ | Cualquiera | Lista paginada + search |
| GET | `/api/usuarios/{id}` | ✅ | Cualquiera | Detalle |
| POST | `/api/usuarios` | ✅ | ADMIN_TENANT | Crear |
| PUT | `/api/usuarios/{id}` | ✅ | ADMIN_TENANT | Actualizar |
| DELETE | `/api/usuarios/{id}` | ✅ | ADMIN_TENANT | Eliminar |
| PATCH | `/api/usuarios/{id}/toggle` | ✅ | ADMIN_TENANT | Toggle activo |

### 12.4 Dashboard (1 ruta)

| Método | Ruta | Auth | Rol Mín. | Descripción |
|--------|------|:----:|----------|-------------|
| GET | `/api/dashboard/summary` | ✅ | Cualquiera | KPIs consolidados |

**Respuesta:**
```json
{
    "totalEmpresas": 2,
    "totalUsuarios": 5,
    "usuariosActivos": 5,
    "llamadasHoy": 18,
    "llamadasActivas": 7,
    "agentesActivos": 3,
    "agentesTotal": 5,
    "alertasActivas": 4,
    "pbxOnline": 1,
    "tasaASR": 61.1,
    "acdPromedio": "3:55"
}
```

### 12.5 PBX (6 rutas)

| Método | Ruta | Auth | Rol Mín. | Filtros |
|--------|------|:----:|----------|---------|
| GET | `/api/pbx` | ✅ | ADMIN_TENANT | `search` (nombre, ip) |
| GET | `/api/pbx/{id}` | ✅ | ADMIN_TENANT | — |
| POST | `/api/pbx` | ✅ | ADMIN_TENANT | Auto-genera UUIDs |
| PUT | `/api/pbx/{id}` | ✅ | ADMIN_TENANT | — |
| DELETE | `/api/pbx/{id}` | ✅ | ADMIN_TENANT | FK CASCADE |
| PATCH | `/api/pbx/{id}/toggle` | ✅ | ADMIN_TENANT | — |

### 12.6 Extensiones (5 rutas)

| Método | Ruta | Auth | Rol Mín. | Filtros |
|--------|------|:----:|----------|---------|
| GET | `/api/extensiones` | ✅ | ADMIN_TENANT | `search` (numero, nombre), `pbx_id` |
| GET | `/api/extensiones/{id}` | ✅ | ADMIN_TENANT | — |
| POST | `/api/extensiones` | ✅ | ADMIN_TENANT | Valida PBX |
| PUT | `/api/extensiones/{id}` | ✅ | ADMIN_TENANT | — |
| PATCH | `/api/extensiones/{id}/toggle` | ✅ | ADMIN_TENANT | — |

### 12.7 Colas (5 rutas)

| Método | Ruta | Auth | Rol Mín. | Filtros |
|--------|------|:----:|----------|---------|
| GET | `/api/colas` | ✅ | SUPERVISOR | `search` (nombre), `pbx_id` |
| GET | `/api/colas/{id}` | ✅ | ADMIN_TENANT | — |
| POST | `/api/colas` | ✅ | ADMIN_TENANT | Defaults: RINGALL, 300s |
| PUT | `/api/colas/{id}` | ✅ | ADMIN_TENANT | — |
| PATCH | `/api/colas/{id}/toggle` | ✅ | ADMIN_TENANT | — |

### 12.8 Agentes (5 rutas)

| Método | Ruta | Auth | Rol Mín. | Filtros |
|--------|------|:----:|----------|---------|
| GET | `/api/agentes` | ✅ | SUPERVISOR | `search` (nombre), `cola_id` |
| GET | `/api/agentes/{id}` | ✅ | ADMIN_TENANT | — |
| POST | `/api/agentes` | ✅ | ADMIN_TENANT | Default: DESCONECTADO |
| PUT | `/api/agentes/{id}` | ✅ | ADMIN_TENANT | — |
| PATCH | `/api/agentes/{id}/toggle` | ✅ | ADMIN_TENANT | DESCONECTADO ↔ DISPONIBLE |

### 12.9 Llamadas CDR (4 rutas)

| Método | Ruta | Auth | Rol Mín. | Filtros |
|--------|------|:----:|----------|---------|
| GET | `/api/llamadas` | ✅ | SUPERVISOR | `fecha_inicio`, `fecha_fin`, `estado`, `pbx_id` |
| GET | `/api/llamadas/{id}` | ✅ | SUPERVISOR | — |
| GET | `/api/llamadas/stats` | ✅ | SUPERVISOR | SUPER_ADMIN: global |
| GET | `/api/llamadas/export` | ✅ | SUPERVISOR | Mismos filtros, CSV |

### 12.10 Eventos (2 rutas)

| Método | Ruta | Auth | Rol Mín. | Filtros |
|--------|------|:----:|----------|---------|
| GET | `/api/eventos` | ✅ | SUPERVISOR | `tipo`, `evento` (LIKE), `pbx_id`, `fecha_inicio`, `fecha_fin` |
| GET | `/api/eventos/{id}` | ✅ | SUPERVISOR | — |

### 12.11 Alertas (6 rutas)

| Método | Ruta | Auth | Rol Mín. | Filtros |
|--------|------|:----:|----------|---------|
| GET | `/api/alertas` | ✅ | ADMIN_TENANT | `search` (nombre) |
| GET | `/api/alertas/{id}` | ✅ | ADMIN_TENANT | — |
| POST | `/api/alertas` | ✅ | ADMIN_TENANT | Valida `tipo` y `condicion` |
| PUT | `/api/alertas/{id}` | ✅ | ADMIN_TENANT | — |
| PATCH | `/api/alertas/{id}/toggle` | ✅ | ADMIN_TENANT | — |
| GET | `/api/alertas/{id}/historial` | ✅ | ADMIN_TENANT | Paginado por regla |

### 12.12 CDR Report (6 rutas)

| Método | Ruta | Auth | Rol Mín. | Filtros |
|--------|------|:----:|----------|---------|
| GET | `/api/cdr-report/llamadas` | ✅ | SUPERVISOR | `fecha_inicio`, `fecha_fin`, `nombre_cola`, `extension_agente`, `estado_final` |
| GET | `/api/cdr-report/colas` | ✅ | SUPERVISOR | Sin paginación |
| GET | `/api/cdr-report/agentes` | ✅ | SUPERVISOR | Sin paginación |
| GET | `/api/cdr-report/estadisticas` | ✅ | SUPERVISOR | `fecha_inicio`, `fecha_fin`, `numero_cola` |
| GET | `/api/cdr-report/real` | ✅ | SUPERVISOR | `fecha_inicio`, `fecha_fin` |
| GET | `/api/cdr-report/stats` | ✅ | SUPERVISOR | KPIs globales |

### 12.13 Agent Ingestion (10 rutas, sin auth JWT)

| Método | Ruta | Auth | Descripción |
|--------|------|:----:|-------------|
| POST | `/api/agent/heartbeat` | X-Agent-ID | Heartbeat del PBX |
| POST | `/api/agent/cdr` | X-Agent-ID | Batch CDR raw |
| POST | `/api/agent/cdr-report` | X-Agent-ID | Reporte CDR anidado |
| POST | `/api/agent/events` | X-Agent-ID | Batch eventos AMI/CEL |
| POST | `/api/agent/metrics` | X-Agent-ID | Métricas salud |
| POST | `/api/v1/agent/heartbeat` | X-Agent-ID | (compatibilidad) |
| POST | `/api/v1/agent/cdr` | X-Agent-ID | (compatibilidad) |
| POST | `/api/v1/agent/cdr-report` | X-Agent-ID | (compatibilidad) |
| POST | `/api/v1/agent/events` | X-Agent-ID | (compatibilidad) |
| POST | `/api/v1/agent/metrics` | X-Agent-ID | (compatibilidad) |

---

## 13. Modelos de Datos (ORM)

### 13.1 BaseModel (Abstract)

```php
abstract class BaseModel {
    protected static string $table = '';      // Override en subclases
    protected static bool $tenantScoped = true;

    // CRUD
    static find(int $id): ?array                    // PK + tenant filter
    static findBy(string $col, mixed $val): ?array  // Sin tenant filter
    static findAll(array $cond, string $order, int $limit): array
    static count(array $cond): int
    static create(array $data): int                  // Retorna lastInsertId
    static update(int $id, array $data): int         // Row count
    static delete(int $id): int                      // Row count

    // Paginación
    static paginate(int $page, int $size, array $cond, string $search, array $searchCols): array
    // Retorna: ['data' => [...], 'total' => int]
}
```

### 13.2 Modelos Concretos

| Modelo | Tabla | Métodos Especiales |
|--------|-------|-------------------|
| `User` | `usuarios` | `findByEmail()`, `createWithPassword()`, `verifyPassword()`, `emailExistsInTenant()`, `countLoginAttempts()`, `logLoginAttempt()` |
| `Tenant` | `empresas` | `findByNit()`, `nitExists()`, `countUsers()` |
| `Pbx` | `pbx` | `hashToken()`, `findByToken()`, `findByAgenteId()`, `countExtensions()`, `countCallsToday()` |
| `Extension` | `extensiones` | Override `paginate()` — search en `numero` + `nombre` |
| `Queue` | `colas` | `countAgents()`, Override `paginate()` — search en `nombre` |
| `Agent` | `agentes` | `updateStats()`, Override `paginate()` — search en `nombre` |
| `CallRecord` | `llamadas_cdr` | `paginateFiltered()` (fecha, estado, pbx), `getStatsToday()`, `getStatsGlobal()` |
| `Event` | `eventos` | `paginateFiltered()` (tipo, evento LIKE, pbx, fechas) |
| `AlertRule` | `reglas_alerta` | `findActive()`, Override `paginate()` — search en `nombre` |

---

## 14. Endpoints Frontend — Referencia

### 14.1 Funciones API Helper (`ApiClientHelpers.php`)

| Función | HTTP | Backend Endpoint | Notas |
|---------|------|-----------------|-------|
| `api_get_empresas($page, $size, $search)` | GET | `/tenants` | |
| `api_create_empresa($data)` | POST | `/tenants` | |
| `api_update_empresa($id, $data)` | PUT | `/tenants/{id}` | |
| `api_toggle_empresa($id)` | PATCH | `/tenants/{id}/toggle` | |
| `api_delete_empresa($id)` | DELETE | `/tenants/{id}` | |
| `api_get_usuarios($page, $size, $search)` | GET | `/usuarios` | |
| `api_get_usuario($id)` | GET | `/usuarios/{id}` | |
| `api_create_usuario($data)` | POST | `/usuarios` | |
| `api_update_usuario($id, $data)` | PUT | `/usuarios/{id}` | |
| `api_toggle_usuario($id)` | PATCH | `/usuarios/{id}/toggle` | |
| `api_get_pbx($page, $size, $search)` | GET | `/pbx` | |
| `api_create_pbx($data)` | POST | `/pbx` | |
| `api_update_pbx($id, $data)` | PUT | `/pbx/{id}` | |
| `api_toggle_pbx($id)` | PATCH | `/pbx/{id}/toggle` | |
| `api_get_extensiones($page, $size, $pbxId)` | GET | `/extensiones` | `pbx_id` opcional |
| `api_get_colas($page, $size, $pbxId)` | GET | `/colas` | `pbx_id` opcional |
| `api_get_agentes($page, $size, $colaId)` | GET | `/agentes` | `cola_id` opcional |
| `api_get_llamadas($page, $size, $filters)` | GET | `/llamadas` | Filtros genéricos |
| `api_get_llamadas_stats()` | GET | `/llamadas/stats` | |
| `api_export_llamadas_csv($filters)` | GET | `/llamadas/export` | Stream CSV, call `exit` |
| `api_get_eventos($page, $size, $filters)` | GET | `/eventos` | |
| `api_get_alertas($page, $size)` | GET | `/alertas` | |
| `api_create_alerta($data)` | POST | `/alertas` | |
| `api_update_alerta($id, $data)` | PUT | `/alertas/{id}` | |
| `api_toggle_alerta($id)` | PATCH | `/alertas/{id}/toggle` | |
| `api_get_dashboard()` | GET | `/dashboard/summary` | |
| `api_get_pbx_detail($id)` | GET | `/pbx/{id}` | |
| `api_get_eventos_pbx($pbxId, $page, $size)` | GET | `/eventos` | tipo=HEALTH |
| `api_get_llamadas_activas($page, $size)` | GET | `/llamadas` | activas=true |
| `api_get_cdr_llamadas($page, $size, $filters)` | GET | `/cdr-report/llamadas` | |
| `api_get_cdr_colas()` | GET | `/cdr-report/colas` | |
| `api_get_cdr_agentes()` | GET | `/cdr-report/agentes` | |
| `api_get_cdr_estadisticas($page, $size, $filters)` | GET | `/cdr-report/estadisticas` | |
| `api_get_cdr_real($page, $size, $filters)` | GET | `/cdr-report/real` | |
| `api_get_cdr_stats()` | GET | `/cdr-report/stats` | |

### 14.2 Componentes HTML Reutilizables (`components.php`)

| Función | Propósito |
|---------|-----------|
| `cm_badge_token($estado)` | Mapea estado a token de color (ok/warn/bad/info) |
| `cm_render_badge($estado)` | Badge HTML con label en español |
| `cm_render_table_headers($cols)` | `<thead>` con columnas |
| `cm_render_pagination($page, $total, $base)` | Paginación Bootstrap |
| `cm_render_stat_card($label, $value, $color, $icon)` | Tarjeta KPI |
| `cm_render_queue_card($cola)` | Tarjeta de monitoreo de cola |
| `cm_render_empty_state($title, $msg, $url, $text, $icon)` | Estado vacío |
| `cm_initials($nombre)` | Iniciales de un nombre |
| `cm_hex_to_rgba($hex, $alpha)` | Conversión de color |

---

## 15. JavaScript — Módulos del Cliente

### 15.1 ws-client.js — WebSocket Client

```javascript
const ws = new CallMetricsWS({
    tenantId: 14,
    wsUrl: 'ws://localhost:8081'
});

ws.on('call_event', (data) => { /* ... */ });
ws.on('queue_update', (data) => { /* ... */ });
ws.on('pbx_health', (data) => { /* ... */ });
ws.connect();
```

**Estrategia de reconexión:**
- Backoff exponencial: `1s × 2^(attempt-1)`, máximo 30s
- Jitter: ±25%
- Máximo 50 intentos
- Auto-suscribe a `tenant_{id}` y `dashboard` al conectar

### 15.2 dashboard.js — Dashboard

- **Timer de duración:** Incrementa cada 1s para llamadas activas
- **API refresh:** Fetch `/dashboard/summary` cada 60s como fallback
- **Chart.js:**
  - Gráfica de concurrencia (line chart, 24h, gradiente)
  - Gráfica de colas (bar chart, Atendidas vs En espera)
- **WebSocket handlers:** call_event, queue_update, pbx_health, agent_status

### 15.3 events.js — Eventos Asterisk

- **Payload expand/collapse:** Click para ver JSON formateado
- **Streaming real-time:** Eventos WebSocket mapeados a formato display
- **Fallback sintético:** Si no hay WS después de 5s, genera eventos cada 5s
- **Buffer:** Máximo 200 eventos, elimina los más antiguos
- **Autoscroll:** Pausable

### 15.4 cc.js — Call Center

- **Table filter:** `bindFilter(inputId, tbodyId, cellIndexes)` — filtrado por texto
- **Tab switching:** `.cm-tab` / `.cm-tab-panel` toggle
- **Autoscroll:** Pausable para tabla de llamadas activas

### 15.5 cdr.js — CDR

- **Tab switching:** 4 paneles (Llamadas/Colas/Agentes/Global)
- **CSV export:** Lee DOM `#cdrTable`, construye CSV con BOM UTF-8 para Excel

---

## 16. Configuración y Despliegue

### 16.1 Requisitos

| Componente | Versión mínima |
|-----------|---------------|
| PHP | 8.1+ (recomendado: 8.3) |
| MariaDB/MySQL | 10.4+ |
| Apache (XAMPP) | 2.4+ |
| Composer | 2.0+ |
| Node.js (solo para docs build) | 18+ |

### 16.2 Inicialización

```bash
# 1. Clonar repositorio
git clone https://github.com/sronaal/CallMetrics-PHP.git
cd CallMetrics_4TO

# 2. Instalar dependencias PHP
cd backend && composer install

# 3. Configurar base de datos
cp .env.example .env
# Editar .env con credenciales de DB

# 4. Crear schema
mysql -u root -p callmetrics < sql/schema.sql
mysql -u root -p callmetrics < sql/schema-phase6.sql
mysql -u root -p callmetrics < sql/schema-cdr-report.sql

# 5. Cargar datos demo
mysql -u root -p callmetrics < sql/seed.sql
mysql -u root -p callmetrics < sql/seed-real.sql
mysql -u root -p callmetrics < sql/seed-cdr-report.sql

# 6. Iniciar backend
php -S 0.0.0.0:8080 -t backend/public

# 7. Frontend se sirve via Apache en :80
# Acceder a http://localhost/CallMetrics_4TO/frontend/
```

### 16.3 Usuarios Demo

| Email | Password | Rol | Tenant |
|-------|----------|-----|--------|
| admin@callmetrics.com | admin123 | SUPER_ADMIN | Global |
| carlos@admin.com | admin123 | SUPER_ADMIN | Global |
| steven@gmail.com | admin123 | ADMIN_TENANT | IPNETWORK (14) |
| ronal@gmail.com | admin123 | SUPERVISOR | IPNETWORK (14) |
| ana@alpha.com | admin123 | SUPERVISOR | IPNETWORK (14) |

### 16.4 Puertos

| Servicio | Puerto | Proceso |
|----------|--------|---------|
| Apache (XAMPP) | 80 | httpd |
| Backend API | 8080 | php -S |
| WebSocket | 8081 | php websocket-server.php |
| MariaDB | 33060 | mysqld |

---

## 17. Seguridad

### 17.1 Controles Implementados

| Control | Implementación |
|---------|---------------|
| **JWT** | HS256, access 15min, refresh 7d, single-use refresh |
| **Rate Limiting** | 5 intentos/15min por email+IP en login |
| **Password Hashing** | bcrypt cost 12 |
| **CSRF** | Token en formularios login (`bin2hex(random_bytes(32))`) |
| **Multi-Tenancy** | Automático en BaseModel, SUPER_ADMIN bypass |
| **Role Hierarchy** | Numérica: SUPER_ADMIN(4) > ADMIN(3) > SUPERVISOR(2) > OPERADOR(1) |
| **Input Validation** | Request class, Controller validation, DB prepared statements |
| **CORS** | Whitelist de orígenes permitidos |
| **Session Timeout** | 15 minutos de inactividad |
| **Agent Auth** | X-Agent-ID header + UUID lookup en PBX table |
| **Direct Access Guard** | Bloquea acceso directo a archivos PHP en /src/pages/ |

### 17.2 Headers de Seguridad (Apache)

```apache
# .htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

### 17.3 Preparación de Statements

```php
// Database.php — PDO configuration
PDO::ATTR_EMULATE_PREPARES => false  // Real prepared statements
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
```

---

## 18. Guía de Desarrollo

### 18.1 Agregar Nuevo Endpoint

1. **Crear modelo** en `backend/src/Models/` extendiendo `BaseModel`
2. **Crear controller** en `backend/src/Http/Controllers/` extendiendo `Controller`
3. **Agregar ruta** en `backend/config/routes.php`:
   ```php
   ['GET', '/api/nuevo-recurso', 'NuevoController@index', true, 'SUPERVISOR'],
   ```
4. **Crear helper** en `frontend/src/core/ApiClientHelpers.php`:
   ```php
   function api_get_nuevos(int $page = 0, int $size = 10): array {
       return ApiClient::getInstance()->get('/nuevo-recurso', ['page' => $page, 'size' => $size]);
   }
   ```
5. **Crear página** en `frontend/src/pages/` usando el patrón de layout existente

### 18.2 Patrón de Controller

```php
class NuevoController extends Controller {
    public function index(Request $request): void {
        $page = max(0, (int) ($request->query()['page'] ?? 0));
        $size = max(1, min(100, (int) ($request->query()['size'] ?? 10)));
        $search = $request->query()['search'] ?? '';

        $result = NuevoModel::paginate($page, $size, [], $search, ['nombre', 'email']);
        Response::paginated($result['data'], $page, $size, $result['total']);
    }

    public function show(Request $request): void {
        $id = (int) $request->param('id');
        $item = NuevoModel::find($id);
        if (!$item) Response::notFound('No encontrado');
        Response::ok($item);
    }

    public function store(Request $request): void {
        $data = $request->body();
        // Validación...
        $id = NuevoModel::create($data);
        Response::created(['id' => $id]);
    }
}
```

### 18.3 Patrón de Página Frontend

```php
<?php
require_once __DIR__ . '/../../src/config.php';
require_once SRC_PATH . '/core/AuthMiddleware.php';
require_once SRC_PATH . '/core/ApiClientHelpers.php';

AuthMiddleware::check();

$page = max(1, (int) ($_GET['page'] ?? 1));
$search = $_GET['search'] ?? '';
$response = api_get_datos($page - 1, 10, $search);
$datos = $response['data'] ?? [];
$total = $response['meta']['total'] ?? 0;

ob_start();
?>
<!-- HTML content -->
<?php
$content = ob_get_clean();
require LAYOUT_PATH . '/main.php';
?>
```

### 18.4 Patrón de WebSocket Event

```javascript
// Frontend — suscribirse
ws.on('call_event', (data) => {
    if (data.event === 'call_started') {
        addRowToTable(data);
    }
});

// Backend — broadcast (desde controller)
EventBridge::getInstance()->broadcastCallEvent($tenantId, 'call_started', $callData);
```

---

## API Reference Quick Card

```bash
# Login
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@callmetrics.com","password":"admin123"}'

# Dashboard (con token)
curl http://localhost:8080/api/dashboard/summary \
  -H "Authorization: Bearer {token}"

# Llamadas paginadas
curl "http://localhost:8080/api/llamadas?page=0&size=10&estado=ANSWERED" \
  -H "Authorization: Bearer {token}"

# Agent ingestion
curl -X POST http://localhost:8080/api/agent/heartbeat \
  -H "Content-Type: application/json" \
  -H "X-Agent-ID: {uuid}" \
  -d '{"uptime":"1d 2h","cpu_usage":45.2,"memory_usage":68.1}'
```

---

*Documentación generada el 2026-09-22. CallMetrics v1.0 — Observabilidad PBX en tiempo real.*
