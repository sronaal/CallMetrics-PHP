# Documentación Completa — CallMetric Pro

**Versión:** MVP  
**Fecha:** Septiembre 2026  
**Stack:** PHP 8.2+ Vanilla, MySQL 8, JavaScript, Python (Agente Collector)

---

## Tabla de Contenidos

1. [Descripción del Proyecto](#1-descripción-del-proyecto)
2. [Arquitectura General](#2-arquitectura-general)
3. [Componentes del Sistema](#3-componentes-del-sistema)
4. [Estructura del Proyecto](#4-estructura-del-proyecto)
5. [Guía de Instalación](#5-guía-de-instalación)
6. [Base de Datos](#6-base-de-datos)
7. [Backend PHP](#7-backend-php)
8. [Frontend](#8-frontend)
9. [API REST](#9-api-rest)
10. [WebSocket — Tiempo Real](#10-websocket--tiempo-real)
11. [Autenticación y Seguridad](#11-autenticación-y-seguridad)
12. [Multi-Tenancy](#12-multi-tenancy)
13. [Roles y Permisos](#13-roles-y-permisos)
14. [Testing](#14-testing)
15. [Troubleshooting](#15-troubleshooting)
16. [Próximos Pasos](#16-próximos-pasos)

---

## 1. Descripción del Proyecto

**CallMetric Pro** es una plataforma SaaS de observabilidad, análisis y monitoreo para infraestructuras PBX basadas en Asterisk/FreePBX.

### ¿Qué hace?

- **Captura** eventos telefónicos (CDR, CEL, AMI) desde servidores PBX
- **Procesa** y calcula métricas en tiempo real (ASR, ACD, TMO, etc.)
- **Visualiza** dashboards interactivos con gráficos y KPIs
- **Alerta** cuando se superan umbrales configurables
- **Exporta** reportes en formato CSV

### ¿Qué NO hace?

- No reemplaza la central telefónica
- No interviene en el enrutamiento de llamadas
- No graba conversaciones
- No procesa voz

---

## 2. Arquitectura General

### Diagrama de Arquitectura

```
┌─────────────────────────────────────────────────────────────────┐
│                    Frontend PHP (LAMPP)                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐       │
│  │ Auth     │  │ CRUD     │  │Dashboard │  │WebSocket │       │
│  │ (JWT)    │  │ Pages    │  │(Chart.js)│  │ Client   │       │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘       │
│       └──────────────┴──────────────┴──────────────┘            │
└───────────────────────────┬─────────────────────────────────────┘
                            │ HTTP + WebSocket
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│              Backend PHP Vanilla (localhost:8080)                │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐            │
│  │ Controllers │  │   Models    │  │  Services   │            │
│  └─────────────┘  └─────────────┘  └─────────────┘            │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐            │
│  │   Router    │  │  Database   │  │  WebSocket  │            │
│  └─────────────┘  └─────────────┘  └─────────────┘            │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    MySQL/MariaDB                                │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐            │
│  │   empresas  │  │  usuarios   │  │ call_records │           │
│  └─────────────┘  └─────────────┘  └─────────────┘            │
└─────────────────────────────────────────────────────────────────┘
                            ▲
                            │
┌─────────────────────────────────────────────────────────────────┐
│           Agente Collector (Python asyncio)                     │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐            │
│  │ AMI Events  │  │   CDR/CEL   │  │  Heartbeat  │            │
│  └─────────────┘  └─────────────┘  └─────────────┘            │
└─────────────────────────────────────────────────────────────────┘
```

### Flujo de Datos

```
1. Agente Python → Conecta a Asterisk AMI/CDR/CEL
2. Agente Python → Envía eventos HTTP POST al Backend PHP
3. Backend PHP → Procesa y almacena en MySQL
4. Backend PHP → Emite eventos via WebSocket al Frontend
5. Frontend PHP → Actualiza dashboards en tiempo real
```

---

## 3. Componentes del Sistema

### 3.1 Backend PHP

| Componente | Tecnología | Descripción |
|------------|------------|-------------|
| **Servidor Web** | PHP 8.2+ Built-in Server | API REST en puerto 8080 |
| **WebSocket** | Ratchet | Servidor de eventos en tiempo real |
| **Base de Datos** | MySQL 8 / MariaDB | Almacenamiento persistente |
| **Autenticación** | JWT (firebase/php-jwt) | Tokens de acceso y refresh |
| **Dependency Injection** | Composer | Autoload PSR-4 |

### 3.2 Frontend PHP

| Componente | Tecnología | Descripción |
|------------|------------|-------------|
| **Motor de Plantillas** | PHP Vanilla | Renderizado server-side |
| **CSS Framework** | Bootstrap 5 | UI responsive |
| **Gráficos** | Chart.js | Visualización de datos |
| **WebSocket Client** | SockJS + STOMP | Conexión tiempo real |
| **JavaScript** | Vanilla JS | Interactividad |

### 3.3 Agente Collector

| Componente | Tecnología | Descripción |
|------------|------------|-------------|
| **Lenguaje** | Python 3.10+ | Asyncio para concurrencia |
| **Conexión AMI** | Pyst2 / Asterisk AMI | Eventos en tiempo real |
| **HTTP Client** | aiohttp | Envío de datos al backend |
| **WebSocket Client** | websockets | Conexión persistente |

### 3.4 Base de Datos

| Componente | Tecnología | Descripción |
|------------|------------|-------------|
| **Motor** | MySQL 8.0+ / MariaDB 10.6+ | InnoDB, utf8mb4 |
| **Charset** | utf8mb4_unicode_ci | Soporte completo Unicode |
| **Mecanismo** | Transactions ACID | Integridad de datos |

---

## 4. Estructura del Proyecto

```
CallMetrics_4TO/
├── backend/                          # Backend PHP
│   ├── src/
│   │   ├── Core/                     # Componentes fundamentales
│   │   │   ├── Config.php           # Constantes y .env loader
│   │   │   ├── Database.php         # PDO singleton
│   │   │   ├── Router.php           # Mapeo URI → Controller
│   │   │   ├── Request.php          # Wrapper de superglobals
│   │   │   ├── Response.php         # Helper respuestas JSON
│   │   │   ├── JwtHelper.php        # Generar/verificar JWT
│   │   │   └── TenantContext.php    # Almacena tenant_id por request
│   │   │
│   │   ├── Http/
│   │   │   ├── Controllers/         # Lógica de endpoints
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── UserController.php
│   │   │   │   ├── TenantController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   └── AgentController.php
│   │   │   └── Middleware/
│   │   │       ├── AuthMiddleware.php    # Verifica JWT
│   │   │       ├── CorsMiddleware.php    # Headers CORS
│   │   │       └── AdminMiddleware.php   # Requiere SUPER_ADMIN
│   │   │
│   │   ├── Models/                   # Active Record ligero
│   │   │   ├── BaseModel.php         # CRUD genérico
│   │   │   ├── User.php             # Modelo usuario
│   │   │   ├── Tenant.php           # Modelo empresa/tenant
│   │   │   ├── CallRecord.php       # Modelo CDR
│   │   │   └── Event.php            # Modelo eventos
│   │   │
│   │   ├── Services/                 # Lógica de negocio
│   │   │   └── AlertEngine.php      # Motor de alertas
│   │   │
│   │   └── WebSocket/               # Tiempo real
│   │       ├── Server.php           # Servidor Ratchet
│   │       └── EventBridge.php      # Puente de eventos
│   │
│   ├── config/
│   │   ├── routes.php               # Registro de rutas
│   │   └── database.php             # Credenciales BD
│   │
│   ├── sql/
│   │   ├── schema.sql               # Script creación BD
│   │   ├── seed.sql                 # Datos iniciales
│   │   └── migrations/              # Migraciones
│   │
│   ├── tests/
│   │   ├── Unit/                    # Pruebas unitarias
│   │   └── Integration/             # Pruebas de integración
│   │
│   ├── public/
│   │   └── index.php                # Front Controller
│   │
│   ├── composer.json                # Dependencias PHP
│   ├── websocket-server.php         # Inicio WS server
│   └── .env                         # Variables de entorno
│
├── frontend/                         # Frontend PHP
│   ├── src/
│   │   ├── core/                    # Componentes compartidos
│   │   │   ├── Config.php           # Configuración
│   │   │   ├── ApiClient.php        # Cliente HTTP
│   │   │   ├── Session.php          # Gestión sesiones
│   │   │   └── AuthMiddleware.php   # Guard autenticación
│   │   │
│   │   ├── layout/                  # Plantillas base
│   │   │   ├── head.php             # HTML head
│   │   │   ├── app.php              # Layout principal
│   │   │   ├── footer.php           # Footer
│   │   │   └── auth.php             # Layout login
│   │   │
│   │   ├── pages/                   # Páginas PHP
│   │   │   ├── auth.php             # Login
│   │   │   ├── dashboard.php        # Dashboard principal
│   │   │   ├── users.php            # Gestión usuarios
│   │   │   ├── empresas.php         # Gestión empresas
│   │   │   ├── pbx.php              # Lista PBX
│   │   │   ├── pbx-detalle.php      # Detalle PBX
│   │   │   ├── agents.php           # Agentes
│   │   │   └── asterisk-events.php  # Eventos Asterisk
│   │   │
│   │   ├── components/              # Componentes reutilizables
│   │   │   └── components.php
│   │   │
│   │   └── data/                    # Datos mock
│   │       └── mock.php
│   │
│   ├── assets/
│   │   ├── css/                     # Estilos
│   │   └── js/                      # JavaScript
│   │       ├── dashboard.js
│   │       ├── users.js
│   │       ├── empresas.js
│   │       ├── pbx.js
│   │       ├── websocket-client.js  # Cliente STOMP
│   │       └── realtime-dashboard.js
│   │
│   ├── callcenter/                  # Módulo Call Center
│   │   ├── dashboard.php
│   │   ├── colas.php
│   │   ├── agentes.php
│   │   └── cdr.php
│   │
│   ├── composer.json
│   └── .env
│
├── openspec/                         # Documentación SDD
│   ├── config.yaml
│   ├── specs/
│   └── changes/
│
├── sql/                              # Scripts SQL globales
│
├── ESPECIFICACIONES.md               # Especificación de requisitos
├── IMPLEMENTATION_PLAN.md            # Plan de implementación
├── BACKEND_PHP_PLAN.md               # Arquitectura backend PHP
├── BUGFIXES_SUMMARY.md               # Reporte de bugs corregidos
└── README.md                         # Readme principal
```

---

## 5. Guía de Instalación

### 5.1 Requisitos Previos

| Componente | Versión Mínima | Comando Verificación |
|------------|----------------|---------------------|
| PHP | 8.1+ | `php -v` |
| Composer | 2.0+ | `composer --version` |
| MySQL/MariaDB | 8.0+ / 10.6+ | `mysql --version` |
| Python | 3.10+ | `python3 --version` |
| Node.js | 16+ (opcional) | `node -v` |

### 5.2 Instalación del Backend

```bash
# 1. Navegar al directorio backend
cd CallMetrics_4TO/backend

# 2. Instalar dependencias PHP
composer install

# 3. Configurar variables de entorno
cp .env.example .env
# Editar .env con tus credenciales

# 4. Crear base de datos
mysql -u root -p < sql/schema.sql

# 5. Cargar datos iniciales
mysql -u root -p callmetrics < sql/seed.sql

# 6. Iniciar servidor de desarrollo
php -S localhost:8080 -t public/

# 7. (Opcional) Iniciar WebSocket server
php websocket-server.php
```

### 5.3 Instalación del Frontend

```bash
# 1. Navegar al directorio frontend
cd CallMetrics_4TO/frontend

# 2. Instalar dependencias PHP
composer install

# 3. Configurar variables de entorno
cp .env.example .env
# Editar .env con la URL del backend

# 4. El frontend se sirve desde LAMPP
# Acceder a http://localhost/CallMetrics_4TO/frontend/
```

### 5.4 Variables de Entorno

#### Backend (`backend/.env`)

```env
# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=callmetrics
DB_USER=root
DB_PASS=tu_password

# JWT
JWT_SECRET=tu_clave_secreta_minimo_32_caracteres_aqui
JWT_ACCESS_EXPIRY=900        # 15 minutos
JWT_REFRESH_EXPIRY=604800    # 7 días

# App
APP_ENV=development
APP_DEBUG=1
APP_URL=http://localhost

# Rate limiting
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15

# WebSocket
WS_HOST=0.0.0.0
WS_PORT=8081
```

#### Frontend (`frontend/.env`)

```env
# Backend API URL
API_BASE_URL=http://localhost:8080

# WebSocket URL
WS_URL=ws://localhost:8081

# Session
SESSION_TIMEOUT=900
```

---

## 6. Base de Datos

### 6.1 Diagrama Entity-Relationship

```
┌─────────────────────┐
│      empresas        │
├─────────────────────┤
│ id         (PK)     │
│ nombre              │
│ nit        (UQ)     │
│ email               │
│ telefono            │
│ direccion           │
│ plan                │
│ activo              │
│ created_at          │
│ updated_at          │
└──────────┬──────────┘
           │ 1
           │
           │ N
┌──────────▼──────────┐
│      usuarios        │
├─────────────────────┤
│ id         (PK)     │
│ tenant_id  (FK)     │
│ nombre              │
│ email               │
│ password_hash       │
│ rol                 │
│ extension           │
│ activo              │
│ primer_ingreso      │
│ ultimo_login        │
│ created_at          │
│ updated_at          │
└──────────┬──────────┘
           │ 1
           │
           │ N
┌──────────▼──────────┐
│   refresh_tokens    │
├─────────────────────┤
│ id         (PK)     │
│ user_id    (FK)     │
│ token_hash  (UQ)    │
│ expires_at          │
│ revoked             │
│ created_at          │
└─────────────────────┘

┌─────────────────────┐
│   login_attempts    │
├─────────────────────┤
│ id         (PK)     │
│ email               │
│ ip_address          │
│ attempted_at        │
└─────────────────────┘
```

### 6.2 Tablas Principales

#### `empresas` (Tenants)

```sql
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
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

#### `usuarios`

```sql
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
    
    UNIQUE KEY uk_usuario_tenant_email (tenant_id, email),
    INDEX idx_usuario_email (email),
    INDEX idx_usuario_rol (rol),
    
    CONSTRAINT fk_usuario_tenant
        FOREIGN KEY (tenant_id) REFERENCES empresas(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;
```

### 6.3 Datos Iniciales

El archivo `sql/seed.sql` crea:

- **SUPER_ADMIN** por defecto:
  - Email: `admin@callmetrics.com`
  - Password: `admin123` (DEBE ser cambiado en producción)
  - Rol: `SUPER_ADMIN`
  - Tenant: `NULL` (global)

---

## 7. Backend PHP

### 7.1 Patrón Arquitectónico

El backend sigue un patrón **MVC Ligero** con **Front Controller**:

```
Petición HTTP
    │
    ▼
/public/index.php          ← Front Controller (entrada única)
    │
    ▼
/src/Core/Router.php       ← Resuelve método + URI → Controller@acción
    │
    ▼
/src/Core/Request.php      ← Normaliza $_GET, $_POST, headers
    │
    ▼
/src/Http/Middleware/      ← Auth, CORS, etc.
    │
    ▼
/src/Http/Controllers/     ← Lógica del endpoint
    │
    ▼
/src/Models/               ← Acceso a BD (Active Record)
    │
    ▼
Respuesta JSON: { success, data, message, errors }
```

### 7.2 Componentes Core

#### Router (`src/Core/Router.php`)

Resuelve peticiones HTTP contra el registro de rutas:

```php
// Ejemplo de ruta
['POST', '/api/auth/login', 'AuthController@login', false]

// Soporta parámetros dinámicos
['GET', '/api/tenants/{id}', 'TenantController@show', true, 'SUPER_ADMIN']
```

#### Database (`src/Core/Database.php`)

PDO singleton con prepared statements:

```php
$db = Database::getInstance();

// Fetch all
$users = $db->fetchAll("SELECT * FROM usuarios WHERE activo = 1");

// Fetch one
$user = $db->fetchOne("SELECT * FROM usuarios WHERE id = :id", [':id' => 1]);

// Insert
$id = $db->insert("INSERT INTO usuarios (nombre) VALUES (:nombre)", [':nombre' => 'Juan']);

// Execute (UPDATE/DELETE)
$affected = $db->execute("UPDATE usuarios SET activo = 0 WHERE id = :id", [':id' => 1]);
```

#### TenantContext (`src/Core/TenantContext.php`)

Almacena el contexto del tenant autenticado durante la request:

```php
// Establecido por AuthMiddleware después de validar JWT
TenantContext::set($tenantId, $userId, $role);

// Uso en modelos
$tid = TenantContext::get();
$userId = TenantContext::getUserId();
$role = TenantContext::getRole();

// Verificaciones
TenantContext::isSuperAdmin();
TenantContext::isAdmin();
```

#### JwtHelper (`src/Core/JwtHelper.php`)

Generación y verificación de tokens JWT:

```php
// Generar access token (corta duración)
$token = JwtHelper::generateAccessToken(
    userId: 1,
    tenantId: 10,
    role: 'SUPERVISOR',
    email: 'user@example.com'
);

// Generar refresh token (larga duración)
$refreshToken = JwtHelper::generateRefreshToken(userId: 1);

// Decodificar y validar
$payload = JwtHelper::decode($token);

// Hash para almacenar en BD
$hash = JwtHelper::hash($token); // SHA-256
```

### 7.3 Modelos (Active Record)

#### BaseModel (`src/Models/BaseModel.php`)

Base genérica con CRUD automático y filtro de tenant:

```php
class User extends BaseModel
{
    protected static string $table = 'usuarios';
    protected static bool $tenantScoped = true; // Filtra por tenant_id
}

// Uso
$users = User::findAll(['activo' => 1]);
$user = User::find(1);
$id = User::create(['nombre' => 'Juan', 'email' => 'juan@test.com']);
User::update(1, ['nombre' => 'Juan Pérez']);
User::delete(1);

// Paginación
$result = User::paginate(
    page: 0,
    size: 10,
    conditions: ['rol' => 'OPERADOR'],
    search: 'juan',
    searchColumns: ['nombre', 'email']
);
```

### 7.4 Controladores

#### AuthController (`src/Http/Controllers/AuthController.php`)

```php
// POST /api/auth/login
public function login(Request $request): void
{
    $email = $request->input('email');
    $password = $request->input('password');
    
    // Rate limiting
    // Verificar credenciales
    // Generar tokens
    // Retornar { accessToken, refreshToken, user }
}

// POST /api/auth/refresh
public function refresh(Request $request): void
{
    // Validar refresh token
    // Revocar token anterior
    // Generar nuevos tokens (rotation)
}

// POST /api/auth/logout
public function logout(Request $request): void
{
    // Revocar refresh token
}
```

---

## 8. Frontend

### 8.1 Estructura de Páginas

| Página | Archivo | Descripción |
|--------|---------|-------------|
| Login | `src/pages/auth.php` | Formulario de autenticación |
| Dashboard | `src/pages/dashboard.php` | Panel principal con KPIs |
| Usuarios | `src/pages/users.php` | CRUD de usuarios |
| Empresas | `src/pages/empresas.php` | CRUD de empresas (SUPER_ADMIN) |
| PBX | `src/pages/pbx.php` | Lista de servidores PBX |
| Detalle PBX | `src/pages/pbx-detalle.php` | Detalle y métricas PBX |
| Agentes | `src/pages/agents.php` | Gestión de agentes |
| Eventos | `src/pages/asterisk-events.php` | Eventos Asterisk |

### 8.2 Componentes del Layout

#### `src/layout/head.php`

```html
<!-- Meta tags -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'CallMetric Pro' ?></title>

<!-- CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">

<!-- WebSocket Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/sockjs-client@1/dist/sockjs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/stompjs@2.3.3/lib/stomp.min.js"></script>

<!-- JWT Token para WebSocket -->
<?php if (Session::isAuthenticated()): ?>
<meta name="jwt-token" content="<?= htmlspecialchars(Session::token()) ?>">
<?php endif; ?>
```

#### `src/layout/app.php`

```php
<?php
require_once __DIR__ . '/../core/Session.php';
Session::start();

if (!Session::isAuthenticated()) {
    header('Location: auth.php');
    exit;
}

$user = Session::user();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require 'head.php'; ?>
</head>
<body>
    <!-- Sidebar -->
    <?php require 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Navbar -->
        <?php require 'navbar.php'; ?>
        
        <!-- Page Content -->
        <div class="container-fluid">
            <?php require $pageContent; ?>
        </div>
    </main>
    
    <!-- Footer -->
    <?php require 'footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (!empty($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="assets/js/<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
```

### 8.3 JavaScript CRUD

#### Ejemplo: `assets/js/users.js`

```javascript
(function() {
    'use strict';
    
    const API = '/api/usuarios';
    const token = document.querySelector('meta[name="jwt-token"]')?.content;
    
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
    };
    
    // CREATE / UPDATE
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
        
        if (!id) {
            data.password = form.querySelector('[name="password"]').value;
        }
        
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
    
    // TOGGLE ACTIVE
    window.cmToggleUsuario = async function(id) {
        const res = await fetch(`${API}/${id}/toggle`, { 
            method: 'PATCH', 
            headers 
        });
        if (res.ok) location.reload();
    };
    
    // DELETE
    window.cmDeleteUsuario = async function(id) {
        if (!confirm('¿Estás seguro de eliminar este usuario?')) return;
        
        const res = await fetch(`${API}/${id}`, { 
            method: 'DELETE', 
            headers 
        });
        if (res.ok) location.reload();
    };
})();
```

---

## 9. API REST

### 9.1 Autenticación

| Método | Ruta | Descripción | Auth |
|--------|------|-------------|------|
| POST | `/api/auth/login` | Iniciar sesión | No |
| POST | `/api/auth/refresh` | Renovar tokens | No |
| POST | `/api/auth/logout` | Cerrar sesión | Sí |
| GET | `/api/auth/me` | Obtener usuario actual | Sí |
| PUT | `/api/auth/password` | Cambiar contraseña | Sí |
| PUT | `/api/auth/primer-ingreso` | Primer ingreso | No |

### 9.2 Empresas (SUPER_ADMIN)

| Método | Ruta | Descripción | Rol Mínimo |
|--------|------|-------------|------------|
| GET | `/api/tenants` | Listar (paginado) | SUPER_ADMIN |
| GET | `/api/tenants/{id}` | Obtener una | SUPER_ADMIN |
| POST | `/api/tenants` | Crear | SUPER_ADMIN |
| PUT | `/api/tenants/{id}` | Actualizar | SUPER_ADMIN |
| PATCH | `/api/tenants/{id}/toggle` | Activar/Desactivar | SUPER_ADMIN |

### 9.3 Usuarios

| Método | Ruta | Descripción | Rol Mínimo |
|--------|------|-------------|------------|
| GET | `/api/usuarios` | Listar (paginado) | SUPERVISOR |
| GET | `/api/usuarios/{id}` | Obtener uno | SUPERVISOR |
| POST | `/api/usuarios` | Crear | ADMIN_TENANT |
| PUT | `/api/usuarios/{id}` | Actualizar | ADMIN_TENANT |
| PATCH | `/api/usuarios/{id}/toggle` | Activar/Desactivar | ADMIN_TENANT |

### 9.4 Dashboard

| Método | Ruta | Descripción | Rol Mínimo |
|--------|------|-------------|------------|
| GET | `/api/dashboard/summary` | KPIs agregados | SUPERVISOR |
| GET | `/api/dashboard/timeseries` | Tendencia horaria | SUPERVISOR |
| GET | `/api/dashboard/alerts/timeline` | Timeline alertas | SUPERVISOR |

### 9.5 PBX

| Método | Ruta | Descripción | Rol Mínimo |
|--------|------|-------------|------------|
| GET | `/api/pbx` | Listar PBX | SUPERVISOR |
| GET | `/api/pbx/{id}` | Obtener uno | SUPERVISOR |
| POST | `/api/pbx` | Crear | ADMIN_TENANT |
| PUT | `/api/pbx/{id}` | Actualizar | ADMIN_TENANT |
| DELETE | `/api/pbx/{id}` | Eliminar | ADMIN_TENANT |

### 9.6 Call Center

| Método | Ruta | Descripción | Rol Mínimo |
|--------|------|-------------|------------|
| GET | `/api/cc/queues` | Listar colas | SUPERVISOR |
| POST | `/api/cc/queues` | Crear cola | ADMIN_TENANT |
| GET | `/api/cc/agents` | Listar agentes | SUPERVISOR |
| POST | `/api/cc/agents` | Crear agente | ADMIN_TENANT |
| GET | `/api/cc/cdr` | Listar CDR | SUPERVISOR |
| GET | `/api/cc/cdr/export` | Exportar CSV | SUPERVISOR |

### 9.7 Formato de Respuesta

```json
{
    "success": true,
    "message": "Operación exitosa",
    "data": {
        // Datos aquí
    },
    "meta": {
        "page": 0,
        "size": 10,
        "total": 100,
        "totalPages": 10
    }
}
```

### 9.8 Errores

```json
{
    "success": false,
    "message": "Mensaje de error",
    "data": {
        "errors": [
            "Detalle del error 1",
            "Detalle del error 2"
        ]
    }
}
```

Códigos de estado HTTP comunes:

| Código | Significado |
|--------|-------------|
| 200 | Éxito |
| 201 | Creado |
| 400 | Bad Request (datos inválidos) |
| 401 | No autenticado |
| 403 | Sin permisos |
| 404 | No encontrado |
| 429 | Rate limit excedido |
| 500 | Error del servidor |

---

## 10. WebSocket — Tiempo Real

### 10.1 Arquitectura

```
Agente Collector (Python)
    │
    │ HTTP POST → Backend PHP
    │
    ▼
Backend PHP
    │
    │ Procesa evento
    │ Almacena en MySQL
    │ Emite via WebSocket
    │
    ▼
Frontend JavaScript
    │
    │ SockJS + STOMP Client
    │ Se suscribe a topics
    │ Actualiza DOM en tiempo real
```

### 10.2 Topics de Suscripción

| Topic | Descripción | Datos |
|-------|-------------|-------|
| `/topic/calls` | Llamadas activas | CDR en tiempo real |
| `/topic/alerts` | Alertas del sistema | Severidad, mensaje |
| `/topic/heartbeats` | Salud de PBX | CPU, RAM, disco |
| `/topic/asterisk-events` | Eventos Asterisk | AMI, CEL |
| `/topic/agent-status` | Estado de agentes | Disponible, ocupado |
| `/topic/pbx-update` | Actualizaciones PBX | Estado, métricas |

### 10.3 Cliente WebSocket (`assets/js/websocket-client.js`)

```javascript
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

### 10.4 Dashboard en Tiempo Real

```javascript
// assets/js/realtime-dashboard.js
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
    
    function updateActiveCallsTable(call) {
        const tbody = document.getElementById('cmLiveCallsBody');
        if (!tbody) return;
        
        if (call.estado === 'Activa') {
            let row = tbody.querySelector(`[data-unique-id="${call.uniqueId}"]`);
            if (row) {
                row.querySelector('.cm-duration').textContent = formatDuration(call.duracion);
            } else {
                const tr = createCallRow(call);
                tbody.insertBefore(tr, tbody.firstChild);
            }
        } else {
            const row = tbody.querySelector(`[data-unique-id="${call.uniqueId}"]`);
            if (row) row.remove();
        }
        
        const count = tbody.querySelectorAll('tr').length;
        document.getElementById('cmActiveCallsKpi').textContent = count;
    }
})();
```

---

## 11. Autenticación y Seguridad

### 11.1 Flujo de Autenticación

```
1. Usuario ingresa email + password
2. Frontend envía POST /api/auth/login
3. Backend verifica:
   a. Rate limiting (máx 5 intentos por IP)
   b. Credenciales correctas
   c. Usuario activo
4. Backend genera:
   a. Access Token (15 min)
   b. Refresh Token (7 días)
5. Frontend guarda tokens en sesión PHP
6. Cada petición incluye header: Authorization: Bearer <token>
```

### 11.2 JWT Tokens

#### Access Token (Corta duración)

```json
{
    "iss": "callmetrics",
    "iat": 1694000000,
    "exp": 1694000900,
    "sub": 1,
    "tid": 10,
    "role": "SUPERVISOR",
    "email": "user@example.com",
    "type": "access"
}
```

#### Refresh Token (Larga duración)

```json
{
    "iss": "callmetrics",
    "iat": 1694000000,
    "exp": 1694604800,
    "sub": 1,
    "type": "refresh"
}
```

### 11.3 Rate Limiting

```php
// Configuración en .env
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15

// Implementación
if (User::countLoginAttempts($email, $ip) >= $maxAttempts) {
    Response::error('Demasiados intentos. Intenta en 15 minutos.', 429);
    exit;
}
```

### 11.4 Hashing de Contraseñas

```php
// Crear usuario
$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Verificar
if (password_verify($input, $user['password_hash'])) {
    // Credenciales correctas
}
```

### 11.5 Protección CORS

```php
// CorsMiddleware.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
```

### 11.6 Prepared Statements

```php
// NUNCA hacer esto (vulnerable a SQL Injection):
$sql = "SELECT * FROM usuarios WHERE email = '$email'";

// SIEMPRE hacer esto:
$db->fetchOne(
    "SELECT * FROM usuarios WHERE email = :email",
    [':email' => $email]
);
```

---

## 12. Multi-Tenancy

### 12.1 Concepto

Cada tenant (empresa) solo puede ver sus propios datos. El sistema garantiza aislamiento mediante:

1. **JWT Token**: Contiene `tenant_id` del usuario
2. **TenantContext**: Almacena `tenant_id` durante la request
3. **BaseModel**: Filtra automáticamente por `tenant_id`

### 12.2 Flujo

```
1. Usuario hace login → JWT contiene tenant_id
2. AuthMiddleware extrae tenant_id del JWT
3. TenantContext::set($tenantId, $userId, $role)
4. Cada modelo filtra: WHERE tenant_id = :tenant_id
5. Datos de otros tenants son INACCESIBLES
```

### 12.3 Excepciones

Algunas tablas son globales (sin filtro de tenant):

- `empresas`: Solo SUPER_ADMIN ve todas
- `usuarios` con `rol = 'SUPER_ADMIN'`: Sin filtro

```php
class Tenant extends BaseModel
{
    protected static string $table = 'empresas';
    protected static bool $tenantScoped = false; // Datos globales
}
```

---

## 13. Roles y Permisos

### 13.1 Jerarquía de Roles

```
SUPER_ADMIN (Nivel 4)
    │
    ├── Acceso total a todo
    ├── Gestiona empresas/tenants
    └── Ve todos los datos sin filtro
    
ADMIN_TENANT (Nivel 3)
    │
    ├── CRUD de su tenant
    ├── Gestiona usuarios de su tenant
    └── Configura PBX y alertas
    
SUPERVISOR (Nivel 2)
    │
    ├── Lectura + reportes
    ├── Monitorea colas y agentes
    └── Genera reportes
    
OPERADOR (Nivel 1)
    │
    ├── Solo lectura básica
    ├── Ve métricas personales
    └── Consulta extensiones autorizadas
```

### 13.2 Verificación de Permisos

```php
// En AuthMiddleware.php
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

// En routes.php
['GET', '/api/tenants', 'TenantController@index', true, 'SUPER_ADMIN']
```

### 13.3 Matriz de Permisos

| Recurso | SUPER_ADMIN | ADMIN_TENANT | SUPERVISOR | OPERADOR |
|---------|-------------|--------------|------------|----------|
| Empresas | CRUD | Solo lectura (propia) | ❌ | ❌ |
| Usuarios | CRUD | CRUD (su tenant) | Solo lectura | Solo lectura |
| PBX | CRUD | CRUD (su tenant) | Solo lectura | Solo lectura |
| Dashboard | Todo | Su tenant | Su tenant | Solo personal |
| Reportes | Todo | Todo (su tenant) | Solo lectura | ❌ |
| Alertas | CRUD | CRUD (su tenant) | Solo lectura | ❌ |

---

## 14. Testing

### 14.1 Pruebas Unitarias

```bash
cd backend
./vendor/bin/phpunit tests/Unit/
```

### 14.2 Pruebas de Integración

```bash
cd backend
./vendor/bin/phpunit tests/Integration/
```

### 14.3 Verificación de Sintaxis PHP

```bash
# Verificar todos los archivos PHP
for f in $(find backend -name "*.php"); do php -l "$f" 2>&1; done
```

### 14.4 Pruebas Recomendadas

| Tipo | Herramienta | Cobertura |
|------|-------------|-----------|
| Unitarias | PHPUnit | Modelos, Helpers |
| Integración | PHPUnit | Controllers + BD |
| E2E | Selenium/Playwright | Flujo completo |
| API | Postman/Newman | Todos los endpoints |
| Seguridad | OWASP ZAP | Vulnerabilidades |

---

## 15. Troubleshooting

### 15.1 Errores Comunes

#### "No se pudo conectar a la BD"

```bash
# Verificar que MySQL esté corriendo
sudo systemctl status mysql

# Verificar credenciales en .env
cat backend/.env | grep DB_

# Probar conexión manual
mysql -u root -p callmetrics
```

#### "Token JWT inválido"

```bash
# Verificar JWT_SECRET en .env
cat backend/.env | grep JWT_SECRET

# Asegurar que coincida entre frontend y backend
```

#### "CORS bloqueado"

```bash
# Verificar headers CORS en el backend
curl -I -X OPTIONS http://localhost:8080/api/auth/login

# Debería retornar headers CORS
```

#### "WebSocket no conecta"

```bash
# Verificar que el WebSocket server esté corriendo
php backend/websocket-server.php

# Verificar puerto
netstat -tlnp | grep 8081
```

### 15.2 Logs

```bash
# Logs de Apache (LAMPP)
tail -f /opt/lampp/logs/error_log

# Logs de PHP
tail -f /var/log/php_errors.log

# Logs de MySQL
tail -f /opt/lampp/logs/mysql_error_log.err
```

---

## 16. Próximos Pasos

### 16.1 Bugs Pendientes (Alta Prioridad)

| Bug | Descripción | Esfuerzo |
|-----|-------------|----------|
| #5 | Validación de jerarquía de roles en UserController | 2h |
| #6 | Deduplicación de eventos en AgentIngestController | 3h |
| #10 | Sistema de logging (Logger.php) | 4h |

### 16.2 Mejoras Planeadas

| Mejora | Descripción | Prioridad |
|--------|-------------|-----------|
| Tests automatizados | Cobertura >80% | Alta |
| CI/CD Pipeline | GitHub Actions | Alta |
| Docker | Contenedores para despliegue | Media |
| Kubernetes | Orquestación | Baja |
| Monitoring | Prometheus + Grafana | Media |

### 16.3 Checklist Pre-Producción

- [ ] Cambiar passwords por defecto
- [ ] Configurar SSL/TLS
- [ ] Implementar logging
- [ ] Configurar backups automáticos
- [ ] Pruebas de carga
- [ ] Documentación de API (Swagger)
- [ ] Runbooks de operaciones

---

## Apéndice A: Comandos Útiles

```bash
# Iniciar backend
cd backend && php -S localhost:8080 -t public/

# Iniciar WebSocket
cd backend && php websocket-server.php

# Ejecutar tests
cd backend && ./vendor/bin/phpunit

# Verificar sintaxis PHP
php -l backend/src/Models/BaseModel.php

# Conectar a MySQL
mysql -u root -p callmetrics

# Ver logs
tail -f /opt/lampp/logs/error_log
```

## Apéndice B: Glosario

| Término | Definición |
|---------|------------|
| **AMI** | Asterisk Manager Interface - Interfaz de gestión de Asterisk |
| **CDR** | Call Detail Record - Registro detallado de llamadas |
| **CEL** | Channel Event Logging - Registro de eventos de canal |
| **JWT** | JSON Web Token - Token de autenticación |
| **PBX** | Private Branch Exchange - Central telefónica privada |
| **SaaS** | Software as a Service - Software como servicio |
| **STOMP** | Simple Text Oriented Messaging Protocol |
| **WebSocket** | Protocolo de comunicación bidireccional |
| **Tenant** | Cliente o empresa dentro de la plataforma SaaS |

---

**Documento generado:** Septiembre 2026  
**Última actualización:** Septiembre 2026  
**Maintainer:** Equipo de Desarrollo CallMetrics
