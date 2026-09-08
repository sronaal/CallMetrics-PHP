# CallMetrics PHP — Plan de Implementación CRUD + WebSocket

> **Fecha:** 2026-08-29  
> **Estado:** Borrador para revisión  
> **Objetivo:** Conectar el frontend PHP existente (mock) con el backend Spring Boot real + integrar WebSocket para eventos en tiempo real del agente-collector

---

## 1. Arquitectura Actual vs Objetivo

### 1.1 Estado Actual

| Componente | Estado |
|---|---|
| **Frontend PHP** | UI completa con datos mock estáticos, cero llamadas API, cero sesiones, cero WebSocket |
| **Backend Java** | API REST completa (Spring Boot 3.5), PostgreSQL, Redis Pub/Sub, STOMP WebSocket |
| **Agente Collector** | Python asyncio, envía eventos vía HTTP POST + WebSocket a `/ws/agent/realtime` |

### 1.2 Arquitectura Objetivo

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend PHP (LAMPP)                     │
│                                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │ Auth     │  │ CRUD     │  │Dashboard │  │WebSocket │   │
│  │ (JWT)    │  │ Pages    │  │(Chart.js)│  │ Client   │   │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘   │
│       │              │              │              │         │
│  ┌────▼──────────────▼──────────────▼──────────────▼─────┐  │
│  │              ApiClient.php (cURL/guzzle)              │  │
│  │              WebSocketClient.php (Ratchet)             │  │
│  └────────────────────────┬──────────────────────────────┘  │
└───────────────────────────┼─────────────────────────────────┘
                            │ HTTP + WebSocket
                            ▼
┌─────────────────────────────────────────────────────────────┐
│              Backend Spring Boot (localhost:8080)            │
│                                                             │
│  REST API: /api/auth/*  /api/usuarios/*  /api/tenants/*    │
│            /api/pbx/*   /api/cc/*        /api/dashboard/*  │
│            /api/collector/*  /api/asterisk/*               │
│                                                             │
│  WebSocket: /ws/agent/realtime (STOMP/SockJS)              │
│  Redis Pub/Sub: callmetric:calls, alerts, heartbeats, etc  │
└───────────────────────────┬─────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│           Agente Collector (Python asyncio)                 │
│  AMI events → HTTP POST /api/v1/agent/*                    │
│              → WebSocket /ws/agent/realtime                 │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Fases de Implementación

### FASE 0: Infraestructura Base (Pre-requisitos)

**Archivos nuevos:**

| Archivo | Propósito |
|---|---|
| `src/core/ApiClient.php` | Cliente HTTP singleton para llamar al backend |
| `src/core/Session.php` | Gestión de sesiones PHP con JWT |
| `src/core/Config.php` | Configuración centralizada (URLs, timeouts) |
| `src/core/WebSocketClient.php` | Cliente WebSocket para suscribirse a eventos |
| `src/core/AuthMiddleware.php` | Guard de autenticación para páginas protegidas |
| `src/core/Response.php` | Helper para respuestas JSON estandarizadas |
| `composer.json` | Dependencias PHP (ratchet/websockets, firebase/php-jwt) |

**Dependencias PHP:**

```json
{
  "require": {
    "php": ">=8.1",
    "firebase/php-jwt": "^6.0",
    "ratchet/pawl": "^0.4",
    "cboden/ratchet": "^0.4",
    "ext-curl": "*",
    "ext-json": "*",
    "ext-session": "*"
  }
}
```

**Config centralizada (`src/core/Config.php`):**

```php
class Config {
    const BACKEND_URL = 'http://localhost:8080';
    const WS_URL = 'ws://localhost:8080/ws';
    const JWT_SECRET = ''; // Se obtiene del backend o .env
    const SESSION_TIMEOUT = 900; // 15 min
    const API_TIMEOUT = 30;
}
```

**ApiClient (`src/core/ApiClient.php`):**

```php
class ApiClient {
    private static ?ApiClient $instance = null;
    private string $baseUrl;
    private ?string $token = null;

    public static function getInstance(): self { ... }
    
    public function setToken(string $token): void { ... }
    
    public function get(string $path, array $query = []): array { ... }
    
    public function post(string $path, array $data = []): array { ... }
    
    public function put(string $path, array $data = []): array { ... }
    
    public function patch(string $path, array $data = []): array { ... }
    
    public function delete(string $path): array { ... }
    
    // Implementación interna con cURL
    private function request(string $method, string $url, ?array $data, array $query): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $url . '?' . http_build_query($query),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => Config::API_TIMEOUT,
            CURLOPT_HTTPHEADER => array_merge(
                ['Content-Type: application/json'],
                $this->token ? ['Authorization: Bearer ' . $this->token] : []
            ),
        ]);
        // ...
    }
}
```

**Session (`src/core/Session.php`):**

```php
class Session {
    public static function start(): void {
        session_start();
    }
    
    public static function login(string $accessToken, string $refreshToken, array $user): void {
        $_SESSION['access_token'] = $accessToken;
        $_SESSION['refresh_token'] = $refreshToken;
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
    }
    
    public static function isAuthenticated(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) self::start();
        return isset($_SESSION['access_token']) 
            && (time() - $_SESSION['last_activity']) < Config::SESSION_TIMEOUT;
    }
    
    public static function user(): ?array { ... }
    
    public static function token(): ?string { ... }
    
    public static function logout(): void { ... }
    
    public static function refreshIfExpired(): bool { ... }
}
```

---

### FASE 1: Autenticación Real

**Archivos a modificar:**

| Archivo | Cambio |
|---|---|
| `src/pages/auth.php` | Conectar formulario con `POST /api/auth/login` |
| `src/layout/app.php` | Agregar `AuthMiddleware::check()` al inicio |
| `src/core/AuthMiddleware.php` | Nuevo: verificar sesión + refrescar token |

**Flujo de login:**

```
1. Usuario ingresa email + password
2. auth.php envía POST /api/auth/login
3. Backend retorna { accessToken, refreshToken, user }
4. Session::login() guarda tokens + user en $_SESSION
5. Redirect a dashboard.php
6. Cada página verifica AuthMiddleware::check()
7. Si token expirado → intenta refresh con POST /api/auth/refresh
8. Si refresh falla → redirect a auth.php
```

**Endpoint del backend:**
- `POST /api/auth/login` → `{ email, password }` → `{ accessToken, refreshToken, user: { id, email, nombre, rol, tenantId } }`
- `POST /api/auth/refresh` → `{ refreshToken }` → `{ accessToken, refreshToken }`
- `POST /api/auth/logout` → `{ refreshToken }` → blacklists tokens en Redis

** Cambios en `src/pages/auth.php`:**

```php
<?php
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/ApiClient.php';

Session::start();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $api = ApiClient::getInstance();
    $result = $api->post('/api/auth/login', [
        'email' => $email,
        'password' => $password,
    ]);
    
    if ($result['status'] === 200) {
        Session::login(
            $result['data']['accessToken'],
            $result['data']['refreshToken'],
            $result['data']['user']
        );
        header('Location: dashboard.php');
        exit;
    }
    
    $error = $result['data']['message'] ?? 'Credenciales inválidas';
}
```

---

### FASE 2: CRUD de Usuarios

**Archivos a modificar:**

| Archivo | Cambio |
|---|---|
| `src/pages/users.php` | Reemplazar mock con llamadas API reales |
| `assets/js/users.js` | Nuevo: lógica CRUD con fetch() |

**Endpoints del backend:**

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/usuarios?page=0&size=10&search=nombre` | Listar (paginado, tenant-scoped) |
| GET | `/api/usuarios/{id}` | Obtener uno |
| POST | `/api/usuarios` | Crear `{ nombre, email, password, rol, extension }` |
| PUT | `/api/usuarios/{id}` | Actualizar `{ nombre, email, rol, extension }` |
| PATCH | `/api/usuarios/{id}/toggle-activo` | Activar/desactivar |

**Renderizado PHP (servidor) → API (cliente):**

```php
<?php
// src/pages/users.php
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/ApiClient.php';
require_once __DIR__ . '/../core/AuthMiddleware.php';

AuthMiddleware::check();

$api = ApiClient::getInstance();
$api->setToken(Session::token());

$page = (int)($_GET['page'] ?? 0);
$size = 10;
$search = $_GET['search'] ?? '';

$result = $api->get('/api/usuarios', [
    'page' => $page,
    'size' => $size,
    'search' => $search,
]);

$usuarios = $result['data']['content'] ?? [];
$totalPages = $result['data']['totalPages'] ?? 0;
$currentPage = $result['data']['number'] ?? 0;
```

**JavaScript CRUD (`assets/js/users.js`):**

```javascript
(function() {
    'use strict';
    
    const API = '/api/usuarios';
    const token = document.querySelector('meta[name="jwt-token"]')?.content;
    
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
    };
    
    // CREATE
    document.getElementById('cmUsersForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const id = form.querySelector('[name="id"]').value;
        
        const data = {
            nombre: form.querySelector('[name="nombre"]').value,
            email: form.querySelector('[name="email"]').value,
            rol: form.querySelector('[name="rol"]').value,
            extension: form.querySelector('[name="extension"]').value,
        };
        
        if (!id) data.password = form.querySelector('[name="password"]').value;
        
        const url = id ? `${API}/${id}` : API;
        const method = id ? 'PUT' : 'POST';
        
        const res = await fetch(url, { method, headers, body: JSON.stringify(data) });
        const json = await res.json();
        
        if (res.ok) {
            location.reload();
        } else {
            alert(json.message || 'Error al guardar');
        }
    });
    
    // TOGGLE
    window.cmToggleUsuario = async function(id) {
        const res = await fetch(`${API}/${id}/toggle-activo`, { 
            method: 'PATCH', headers 
        });
        if (res.ok) location.reload();
    };
})();
```

---

### FASE 3: CRUD de Empresas (Tenants)

**Archivos a modificar:**

| Archivo | Cambio |
|---|---|
| `src/pages/empresas.php` | Conectar con API real |
| `assets/js/empresas.js` | Nuevo: lógica CRUD |

**Endpoints del backend (solo SUPER_ADMIN):**

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/tenants?page=0&size=10&search=nombre` | Listar |
| GET | `/api/tenants/{id}` | Obtener uno |
| POST | `/api/tenants` | Crear `{ nombre, nit, email, direccion, telefono, plan }` |
| PUT | `/api/tenants/{id}` | Actualizar |
| PATCH | `/api/tenants/{id}/toggle-activo` | Activar/desactivar |

**Nota:** Solo usuarios con `rol === 'SUPER_ADMIN'` pueden acceder. El backend valida esto con JWT.

---

### FASE 4: CRUD de PBX Servers

**Archivos a modificar:**

| Archivo | Cambio |
|---|---|
| `src/pages/pbx.php` | Conectar con API real |
| `src/pages/pbx-detalle.php` | Conectar con API real |
| `assets/js/pbx.js` | Reemplazar simulación con API calls |

**Endpoints del backend:**

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/pbx` | Listar PBX del tenant |
| GET | `/api/pbx/{id}` | Obtener uno |
| POST | `/api/pbx` | Crear `{ nombre, hostname, direccionIp, puerto, tipo }` |
| PUT | `/api/pbx/{id}` | Actualizar |
| DELETE | `/api/pbx/{id}` | Eliminar PBX + agentes asociados |
| GET | `/api/pbx/{id}/agents` | Listar agentes de monitoreo |
| POST | `/api/pbx/{id}/agents/rotate-token` | Rotar token de autenticación |
| GET | `/api/pbx/{pbxId}/heartbeat-history` | Historial de heartbeats |

**Flujo de registro de PBX:**

```
1. Admin crea PBX via POST /api/pbx
2. Backend retorna { id, token, agenteId }
3. PHP muestra modal con credenciales (PBX_ID, TOKEN_REGISTRO, AGENTE_ID)
4. Admin copia credenciales y las ingresa en el agente-collector (.env)
5. Agente se conecta y comienza a enviar heartbeats
```

---

### FASE 5: Dashboard con Datos Reales

**Archivos a modificar:**

| Archivo | Cambio |
|---|---|
| `src/pages/dashboard.php` | Conectar KPIs con API |
| `assets/js/dashboard.js` | Reemplazar datos hardcoded con fetch |
| `src/components/components.php` | Adaptar renderizado |

**Endpoints del backend:**

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/dashboard/summary` | KPIs agregados (ASR, ACD, TMO, activas, alertas) |
| GET | `/api/dashboard/timeseries?from=&to=` | Tendencia horaria de llamadas |
| GET | `/api/dashboard/alerts/timeline?page=0&size=20` | Timeline de alertas |
| GET | `/api/dashboard/calls/timeline?page=0&size=20` | Timeline de llamadas |

**Mapeo de datos:**

```php
// dashboard.php
$summary = $api->get('/api/dashboard/summary');

// KPIs
$llamadasHoy = $summary['data']['llamadasHoy'] ?? 0;
$llamadasActivas = $summary['data']['llamadasActivas'] ?? 0;
$agentesActivos = $summary['data']['agentesActivos'] ?? 0;
$alertasActivas = $summary['data']['alertasActivas'] ?? 0;
$asr = $summary['data']['asr'] ?? 0;  // Answer Seizure Ratio
$acd = $summary['data']['acd'] ?? 0;  // Average Call Duration
$tmo = $summary['data']['tmo'] ?? 0;  // Time Mean Opinion
$nivelServicio = $summary['data']['nivelServicio'] ?? 0;
```

---

### FASE 6: Call Center CRUD

#### 6.1 Colas (Queues)

**Archivos:** `callcenter/colas.php`, `assets/js/colas.js`

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/cc/queues?page=0&size=10` | Listar colas |
| GET | `/api/cc/queues/{id}` | Obtener una cola |
| POST | `/api/cc/queues` | Crear `{ nombre, extension }` |
| PUT | `/api/cc/queues/{id}` | Actualizar |
| PATCH | `/api/cc/queues/{id}/toggle` | Activar/desactivar |

#### 6.2 Agentes CC

**Archivos:** `callcenter/agentes.php`, `assets/js/cc-agentes.js`

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/cc/agents?page=0&size=10` | Listar agentes |
| GET | `/api/cc/agents/{id}` | Obtener uno |
| POST | `/api/cc/agents` | Crear `{ nombre, extension, queueId }` |
| PUT | `/api/cc/agents/{id}` | Actualizar |
| PATCH | `/api/cc/agents/{id}/state` | Cambiar estado `{ estado: "available" }` |
| DELETE | `/api/cc/agents/{id}` | Eliminar |

**Transiciones de estado válidas:**

```
available → on_call → wrap_up → available
available → paused → available
on_call → wrap_up
wrap_up → available
```

#### 6.3 CDR (Call Detail Records)

**Archivos:** `callcenter/cdr.php`, `assets/js/cdr.js`

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/cc/cdr?page=0&size=50&from=&to=&disposition=` | Listar CDR |
| GET | `/api/cc/cdr/summary` | Resumen por disposition |
| GET | `/api/cc/cdr/export?from=&to=` | Exportar CSV |
| GET | `/api/cc/reports/daily?from=&to=` | Reportes diarios |

#### 6.4 Dashboard CC

**Archivos:** `callcenter/dashboard.php`

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/cc/dashboard` | KPIs del call center |

---

### FASE 7: Eventos Asterisk

**Archivos:** `asterisk-events.php`, `assets/js/events.js`

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/asterisk/events?page=0&size=50&severity=&pbxId=&eventType=&from=&to=` | Listar eventos |

---

### FASE 8: WebSocket — Tiempo Real

Este es el componente más crítico. El agente-collector envía eventos al backend vía WebSocket, y el frontend PHP debe suscribirse para recibirlos en tiempo real.

#### 8.1 Arquitectura WebSocket

```
Agente Collector (Python)
    │
    │ WebSocket: ws://backend:8080/ws/agent/realtime
    │ Header: X-Agent-ID: <uuid>
    │ Eventos: call events, heartbeats, system events
    │
    ▼
Backend Spring Boot
    │
    │ Redis Pub/Sub → RealtimeWebSocketBridge
    │ Canales: callmetric:calls, callmetric:alerts, etc.
    │
    │ STOMP/SockJS: /ws/app/**
    │ Topic: /topic/calls, /topic/alerts, /topic/heartbeats
    │
    ▼
Frontend PHP
    │
    │ JavaScript SockJS + STOMP client
    │ Se suscribe a /topic/calls, /topic/alerts, etc.
    │ Actualiza DOM en tiempo real
```

#### 8.2 WebSocket en el Backend (ya implementado)

El backend Spring Boot ya tiene:

- **`RealtimeWebSocketBridge`**: Escucha Redis Pub/Sub y retransmite a WebSocket STOMP
- **Canales Redis**: `callmetric:calls`, `callmetric:alerts`, `callmetric:heartbeats`, `callmetric:callcenter`, `callmetric:asterisk-events`, `callmetric:agent-status`, `callmetric:pbx-update`
- **Topics STOMP**: `/topic/calls`, `/topic/alerts`, `/topic/heartbeats`, `/topic/asterisk-events`, `/topic/agent-status`, `/topic/pbx-update`
- **SockJS endpoint**: `/ws` con fallback HTTP long-polling

#### 8.3 WebSocket en el Frontend PHP

**NO necesitamos un servidor WebSocket en PHP.** El backend Spring Boot ya expone STOMP/SockJS. El frontend se conecta directamente desde JavaScript usando SockJS + STOMP.

**Archivos nuevos:**

| Archivo | Propósito |
|---|---|
| `src/core/websocket.php` | Template de configuración WebSocket para el layout |
| `assets/js/websocket-client.js` | Cliente SockJS/STOMP reutilizable |
| `assets/js/realtime-dashboard.js` | Dashboard en tiempo real |

**Cliente WebSocket (`assets/js/websocket-client.js`):**

```javascript
/**
 * CallMetrics WebSocket Client
 * Se conecta al backend STOMP/SockJS para recibir eventos en tiempo real
 */
const CMWebSocket = (function() {
    'use strict';
    
    let stompClient = null;
    let reconnectAttempts = 0;
    const MAX_RECONNECT = 10;
    const RECONNECT_DELAY = 3000;
    
    const subscriptions = {};
    
    function connect(token) {
        const wsUrl = 'http://localhost:8080/ws';
        const socket = new SockJS(wsUrl);
        
        stompClient = Stomp.over(socket);
        stompClient.debug = null; // Desactivar debug en producción
        
        stompClient.connect({
            'Authorization': `Bearer ${token}`
        }, function(frame) {
            console.log('[WS] Conectado al backend');
            reconnectAttempts = 0;
            
            // Re-suscribir a topics previos
            Object.keys(subscriptions).forEach(topic => {
                subscribe(topic, subscriptions[topic].callback);
            });
        }, function(error) {
            console.error('[WS] Error de conexión:', error);
            reconnect();
        });
    }
    
    function reconnect() {
        if (reconnectAttempts >= MAX_RECONNECT) {
            console.error('[WS] Máximo de reconexiones alcanzado');
            return;
        }
        reconnectAttempts++;
        setTimeout(() => connect(CMWebSocket.token), RECONNECT_DELAY * reconnectAttempts);
    }
    
    function subscribe(topic, callback) {
        subscriptions[topic] = { callback };
        
        if (stompClient && stompClient.connected) {
            stompClient.subscribe(topic, function(message) {
                const data = JSON.parse(message.body);
                callback(data);
            });
        }
    }
    
    function disconnect() {
        if (stompClient) {
            stompClient.disconnect();
        }
    }
    
    return {
        connect,
        subscribe,
        disconnect,
        token: null
    };
})();
```

#### 8.4 Dashboard en Tiempo Real (`assets/js/realtime-dashboard.js`)

```javascript
/**
 * Dashboard Real-Time
 * Se suscribe a los topics STOMP del backend y actualiza el DOM
 */
(function() {
    'use strict';
    
    const token = document.querySelector('meta[name="jwt-token"]')?.content;
    if (!token) return;
    
    CMWebSocket.token = token;
    CMWebSocket.connect(token);
    
    // Suscribirse a llamadas activas
    CMWebSocket.subscribe('/topic/calls', function(data) {
        updateActiveCallsTable(data);
        updateCallCount(data);
    });
    
    // Suscribirse a alertas
    CMWebSocket.subscribe('/topic/alerts', function(data) {
        addAlertToTimeline(data);
        incrementAlertCount();
    });
    
    // Suscribirse a heartbeats
    CMWebSocket.subscribe('/topic/heartbeats', function(data) {
        updatePbxStatus(data);
    });
    
    // Suscribirse a eventos Asterisk
    CMWebSocket.subscribe('/topic/asterisk-events', function(data) {
        addEventToStream(data);
    });
    
    // Suscribirse a estado de agentes
    CMWebSocket.subscribe('/topic/agent-status', function(data) {
        updateAgentStatus(data);
    });
    
    // Suscribirse a actualizaciones PBX
    CMWebSocket.subscribe('/topic/pbx-update', function(data) {
        updatePbxInfo(data);
    });
    
    // --- Funciones de actualización del DOM ---
    
    function updateActiveCallsTable(call) {
        const tbody = document.getElementById('cmLiveCallsBody');
        if (!tbody) return;
        
        if (call.estado === 'Activa') {
            // Agregar o actualizar fila
            let row = tbody.querySelector(`[data-unique-id="${call.uniqueId}"]`);
            if (row) {
                row.querySelector('.cm-duration').textContent = formatDuration(call.duracion);
            } else {
                const tr = createCallRow(call);
                tbody.insertBefore(tr, tbody.firstChild);
            }
        } else {
            // Eliminar fila (llamada terminada)
            const row = tbody.querySelector(`[data-unique-id="${call.uniqueId}"]`);
            if (row) row.remove();
        }
        
        // Actualizar KPI
        const count = tbody.querySelectorAll('tr').length;
        document.getElementById('cmActiveCallsKpi').textContent = count;
    }
    
    function addAlertToTimeline(alert) {
        const timeline = document.getElementById('cmAlertsTimeline');
        if (!timeline) return;
        
        const div = document.createElement('div');
        div.className = `cm-alert-item cm-alert-${alert.severidad.toLowerCase()}`;
        div.innerHTML = `
            <span class="cm-alert-severity">${alert.severidad}</span>
            <span class="cm-alert-message">${escapeHtml(alert.mensaje)}</span>
            <span class="cm-alert-time">${formatTime(alert.timestamp)}</span>
        `;
        timeline.insertBefore(div, timeline.firstChild);
    }
    
    function addEventToStream(event) {
        const container = document.getElementById('cmEventsStream');
        if (!container) return;
        
        const div = document.createElement('div');
        div.className = `cm-event-item cm-event-${event.severity?.toLowerCase() || 'info'}`;
        div.innerHTML = `
            <span class="cm-event-type">${escapeHtml(event.eventType)}</span>
            <span class="cm-event-subtype">${escapeHtml(event.subtype || '')}</span>
            <button class="cm-event-toggle" onclick="this.nextElementSibling.classList.toggle('show')">
                Ver payload
            </button>
            <pre class="cm-event-payload">${JSON.stringify(event.payload, null, 2)}</pre>
            <span class="cm-event-time">${formatTime(event.timestamp)}</span>
        `;
        container.insertBefore(div, container.firstChild);
    }
    
    function formatDuration(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }
    
    function formatTime(isoString) {
        return new Date(isoString).toLocaleTimeString('es-CO');
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
```

#### 8.5 Integración en el Layout

**Modificar `src/layout/head.php` para agregar dependencias WebSocket:**

```html
<!-- Después de Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/sockjs-client@1/dist/sockjs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/stompjs@2.3.3/lib/stomp.min.js"></script>

<!-- Token JWT para WebSocket -->
<?php if (Session::isAuthenticated()): ?>
<meta name="jwt-token" content="<?= htmlspecialchars(Session::token()) ?>">
<?php endif; ?>
```

**Agregar en páginas que necesiten tiempo real:**

```php
<?php $extraJs = ['websocket-client.js', 'realtime-dashboard.js']; ?>
```

---

### FASE 9: Datos de Sesión en Layout

**Modificar `src/layout/app.php` para usar datos reales del backend:**

```php
<?php
require_once __DIR__ . '/../core/Session.php';
Session::start();

$user = Session::user();
$nombre = $user['nombre'] ?? 'Usuario';
$email = $user['email'] ?? '';
$rol = $user['rol'] ?? 'OPERADOR';
$esAdmin = in_array($rol, ['SUPER_ADMIN', 'ADMIN_TENANT']);
```

---

## 3. Tabla Resumen: Archivos a Crear/Modificar

### 3.1 Archivos Nuevos

| Archivo | Propósito | Fase |
|---|---|---|
| `composer.json` | Dependencias PHP | 0 |
| `src/core/Config.php` | Configuración centralizada | 0 |
| `src/core/ApiClient.php` | Cliente HTTP al backend | 0 |
| `src/core/Session.php` | Gestión de sesiones PHP | 0 |
| `src/core/AuthMiddleware.php` | Guard de autenticación | 1 |
| `src/core/Response.php` | Helper respuestas JSON | 0 |
| `src/api/*.php` | Endpoints PHP proxy (si se usa enfoque BFF) | Opcional |
| `assets/js/websocket-client.js` | Cliente SockJS/STOMP | 8 |
| `assets/js/realtime-dashboard.js` | Dashboard tiempo real | 8 |
| `assets/js/users.js` | CRUD usuarios | 2 |
| `assets/js/empresas.js` | CRUD empresas | 3 |
| `assets/js/colas.js` | CRUD colas CC | 6.1 |
| `assets/js/cc-agentes.js` | CRUD agentes CC | 6.2 |

### 3.2 Archivos a Modificar

| Archivo | Cambio | Fase |
|---|---|---|
| `src/pages/auth.php` | Login real contra API | 1 |
| `src/layout/head.php` | Agregar SockJS/STOMP + meta token | 8 |
| `src/layout/app.php` | Datos de sesión reales + auth guard | 9 |
| `src/pages/users.php` | Datos de API + form con JS externo | 2 |
| `src/pages/empresas.php` | Datos de API + form con JS externo | 3 |
| `src/pages/pbx.php` | Datos de API + form con JS externo | 4 |
| `src/pages/pbx-detalle.php` | Datos de API (heartbeats) | 4 |
| `src/pages/dashboard.php` | KPIs de API + WebSocket | 5, 8 |
| `src/pages/asterisk-events.php` | Datos de API + WebSocket stream | 7, 8 |
| `callcenter/colas.php` | Datos de API | 6.1 |
| `callcenter/agentes.php` | Datos de API | 6.2 |
| `callcenter/cdr.php` | Datos de API + export real | 6.3 |
| `callcenter/dashboard.php` | KPIs de API | 6.4 |
| `assets/js/dashboard.js` | Fetch data + WebSocket handlers | 5, 8 |
| `assets/js/events.js` | API data + WebSocket stream | 7, 8 |
| `assets/js/pbx.js` | API CRUD real | 4 |
| `assets/js/cdr.js` | API export real | 6.3 |

### 3.3 Archivos sin Cambio

| Archivo | Razón |
|---|---|
| `src/data/mock.php` | Se mantiene como fallback/desarrollo |
| `src/components/components.php` | Reutilizable, solo ajustes menores |
| `src/layout/auth.php` | Shell de login, sin cambios |
| `src/layout/public.php` | Landing, sin cambios |
| `src/pages/lading.php` | Landing, sin cambios |
| `assets/css/*` | CSS existente es compatible |

---

## 4. Orden de Implementación Recomendado

```
Fase 0  ──► Fase 1  ──► Fase 2  ──► Fase 3  ──► Fase 4
(Infra)      (Auth)      (Users)     (Tenants)    (PBX)
                                                 │
                                                 ▼
Fase 8  ◄── Fase 7  ◄── Fase 6  ◄── Fase 5
(WS)         (Events)    (CC CRUD)   (Dashboard)
```

**Justificación:**
1. **Fase 0 primero**: Sin infra (ApiClient, Session, Config) nada funciona
2. **Fase 1 después**: Sin auth real, no se puede probar nada contra el backend
3. **Fases 2-4 en paralelo**: CRUD independientes, se pueden desarrollar en paralelo
4. **Fase 5 después de 2-4**: Dashboard necesita datos de usuarios/PBX/tenants
5. **Fase 6 después de 5**: CC CRUD es independiente pero requiere dashboard base
6. **Fase 7 después de 6**: Eventos son read-only, se prueban con CC activo
7. **Fase 8 al final**: WebSocket es el overlay de tiempo real sobre todo lo anterior

---

## 5. Decisiones Técnicas Clave

### 5.1 ¿BFF (Backend-for-Frontend) en PHP o llamadas directas?

**Opción A: Llamadas directas desde JS (Recomendada)**
- El JS del navegador llama directamente al backend Spring Boot
- PHP solo sirve templates y verifica sesión
- Ventaja: Simple, el backend ya tiene JWT
- Riesgo: CORS, el token JWT se expone en el navegador

**Opción B: BFF proxy en PHP**
- PHP recibe las peticiones del JS y las proxy al backend
- El token JWT se mantiene en el servidor PHP
- Ventaja: Seguridad (token nunca sale del servidor)
- Desventaje: Más complejo, doble salt

**Decisión recomendada: Opción A** con CORS bien configurado en el backend, ya que el backend ya soporta CORS para `localhost:5173` (Vite). Se agrega `localhost` (LAMPP) al CORS.

### 5.2 WebSocket: ¿Servidor propio en PHP?

**NO.** El backend Spring Boot ya tiene STOMP/SockJS implementado con Redis Pub/Sub. El frontend se conecta directamente vía JavaScript SockJS client. No se necesita Ratchet ni servidor WebSocket en PHP.

### 5.3 Mock como fallback

Se mantiene `src/data/mock.php` como fallback. Si el backend no está disponible, las páginas pueden degradarse gracefully mostrando datos mock con un indicador "Sin conexión al backend".

### 5.4 Multi-tenancy

El backend maneja multi-tenancy via JWT (el `tenantId` está en el token). El frontend PHP no necesita lógica de tenant — simplemente pasa el token y el backend filtra automáticamente.

---

## 6. Configuración del Backend para PHP

Se requieren estos cambios en el backend Spring Boot:

### 6.1 CORS

Agregar `http://localhost` y `http://localhost:80` a los orígenes permitidos:

```java
// WebConfig.java
.allowedOrigins("http://localhost:5173", "http://localhost:4173", "http://localhost", "http://localhost:80")
```

### 6.2 WebSocket Authentication

El endpoint STOMP `/ws` necesita aceptar conexiones autenticadas via header `Authorization: Bearer <token>`. Verificar que `WebSocketConfig.java` permita el header en los endpoints STOMP.

---

## 7. Estructura Final del Proyecto

```
CallMetrics_4TO/frontend/
├── composer.json                          # NUEVO
├── vendor/                                # NUEVO (composer install)
├── index.php
├── app.php
├── auth.php
├── dashboard.php
├── users.php
├── empresas.php
├── agents.php
├── pbx.php
├── pbx-detalle.php
├── asterisk-events.php
├── callcenter/
│   ├── dashboard.php
│   ├── colas.php
│   ├── agentes.php
│   └── cdr.php
├── src/
│   ├── config.php
│   ├── core/                              # NUEVO
│   │   ├── Config.php
│   │   ├── ApiClient.php
│   │   ├── Session.php
│   │   ├── AuthMiddleware.php
│   │   └── Response.php
│   ├── data/
│   │   └── mock.php                       # Se mantiene
│   ├── components/
│   │   └── components.php
│   ├── layout/
│   │   ├── head.php                       # MODIFICADO
│   │   ├── footer.php
│   │   ├── app.php                        # MODIFICADO
│   │   ├── auth.php
│   │   └── public.php
│   └── pages/
│       ├── lading.php
│       ├── auth.php                       # MODIFICADO
│       ├── dashboard.php                  # MODIFICADO
│       ├── users.php                      # MODIFICADO
│       ├── empresas.php                   # MODIFICADO
│       ├── agents.php                     # MODIFICADO
│       ├── pbx.php                        # MODIFICADO
│       ├── pbx-detalle.php                # MODIFICADO
│       ├── asterisk-events.php            # MODIFICADO
│       └── callcenter/
│           ├── dashboard.php              # MODIFICADO
│           ├── colas.php                  # MODIFICADO
│           ├── agentes.php                # MODIFICADO
│           └── cdr.php                    # MODIFICADO
└── assets/
    ├── css/                               # Sin cambios significativos
    └── js/
        ├── dashboard.js                   # MODIFICADO
        ├── pbx.js                         # MODIFICADO
        ├── events.js                      # MODIFICADO
        ├── cc.js                          # MODIFICADO
        ├── cdr.js                         # MODIFICADO
        ├── landing.js
        ├── websocket-client.js            # NUEVO
        ├── realtime-dashboard.js          # NUEVO
        ├── users.js                       # NUEVO
        ├── empresas.js                    # NUEVO
        ├── colas.js                       # NUEVO
        └── cc-agentes.js                  # NUEVO
```

---

## 8. Riesgos y Mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Backend no está corriendo | Alto | Fallback a mock data con indicador visual |
| CORS bloquea peticiones | Alto | Configurar CORS en backend antes de empezar |
| JWT tokens expiran rápido (15 min) | Medio | Implementar refresh automático en Session.php |
| WebSocket se desconecta | Medio | Reconnect automático con backoff exponencial |
| LAMPP no soporta某些 headers | Bajo | Verificar mod_headers habilitado en Apache |
| POSTGRES no está corriendo | Alto | Verificar infra del backend antes de integrar |
