# CallMetric Pro

Plataforma SaaS de observabilidad, análisis y monitoreo para infraestructuras PBX basadas en Asterisk/FreePBX.

## Descripción

CallMetric Pro opera como una capa de inteligencia operacional sobre la infraestructura telefónica existente. Transforma datos crudos (CDR, CEL, eventos AMI) en dashboards, métricas, alertas y reportes en tiempo real.

**No reemplaza la central telefónica ni interviene en el enrutamiento de llamadas.**

## Arquitectura

```
┌─────────────────┐     WebSocket      ┌──────────────────┐
│  Agente Collector│ ──────────────────▶│  Backend PHP     │
│  (Python)        │                    │  (Ratchet WS)    │
└─────────────────┘                    └────────┬─────────┘
                                                │
                                       ┌────────▼─────────┐
                                       │    MySQL/MariaDB  │
                                       └──────────────────┘
```

### Componentes

| Componente | Tecnología | Descripción |
|------------|------------|-------------|
| **Backend** | PHP 8.3 + Ratchet | API REST + WebSocket server para tiempo real |
| **Frontend** | PHP + JS | Dashboard web multi-tenant |
| **Agente Collector** | Python | Captura eventos AMI/CEL/CDR desde Asterisk |
| **Base de datos** | MySQL/MariaDB | Almacenamiento persistente |

## Requisitos

- PHP >= 8.1
- Composer
- MySQL/MariaDB
- Node.js (para desarrollo frontend)

## Instalación

### Backend

```bash
cd backend
composer install
cp .env.example .env  # configurar variables de entorno
```

### Configurar Base de Datos

```bash
mysql -u root -p < sql/seed.sql
```

### Variables de Entorno (backend/.env)

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=callmetrics
DB_USER=root
DB_PASS=

JWT_SECRET=tu-secreto-aqui

WS_HOST=0.0.0.0
WS_PORT=8081
```

### Iniciar Servicios

```bash
# Backend API (puerto 8080)
cd backend && php -S localhost:8080 -t public/

# WebSocket Server (puerto 8081)
cd backend && php websocket-server.php
```

## Estructura del Proyecto

```
CallMetrics_4TO/
├── backend/
│   ├── src/
│   │   ├── Core/           # Config, Database, Router, JWT
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   └── Middleware/
│   │   ├── Models/         # User, Pbx, Agent, CallRecord, etc.
│   │   ├── Services/       # AlertEngine, Business Logic
│   │   └── WebSocket/      # Server, EventBridge
│   ├── tests/
│   │   ├── Unit/
│   │   └── Integration/
│   ├── config/routes.php
│   └── websocket-server.php
├── frontend/
│   ├── src/
│   │   ├── pages/
│   │   ├── layout/
│   │   └── core/
│   └── assets/
└── sql/
    └── seed.sql
```

## API Endpoints

### Autenticación

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/auth/login` | Login |
| POST | `/api/auth/refresh` | Refrescar JWT |

### Recursos

| Método | Ruta | Rol Mínimo |
|--------|------|------------|
| GET | `/api/pbx` | SUPERVISOR |
| GET | `/api/agentes` | SUPERVISOR |
| GET | `/api/colas` | SUPERVISOR |
| GET | `/api/call-records` | SUPERVISOR |
| GET | `/api/events` | SUPERVISOR |
| GET | `/api/dashboard` | SUPERVISOR |

### WebSocket

Conectar a `ws://localhost:8081` con header `X-Agent-ID: {id}` para agentes, o sin header para clientes frontend.

**Canales de suscripción:**
- `tenant_{id}` — Eventos del tenant
- `pbx_{id}` — Salud del PBX
- `colas_{id}` — Estado de colas
- `dashboard` — KPIs globales

## Roles

| Rol | Permisos |
|-----|----------|
| SUPER_ADMIN | Acceso total |
| ADMIN_TENANT | CRUD de su tenant |
| SUPERVISOR | Lectura + reportes |
| OPERADOR | Solo lectura básica |

## Testing

```bash
cd backend
./vendor/bin/phpunit tests/Unit/
./vendor/bin/phpunit tests/Integration/
```

## License

Propietario — Uso interno SENA/CallMetrics
