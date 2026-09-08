# CallMetrics — Backend PHP Vanilla

API REST multi-tenant para sistema de métricas de llamadas, construida con PHP vanilla (sin frameworks).

## Stack

- **PHP** 8.2+
- **MySQL** 8
- **firebase/php-jwt** ^7.0 (tokens JWT)
- **vlucas/phpdotenv** ^5.6 (variables de entorno)

## Arquitectura

```
peticion HTTP
    │
    ▼
/public/index.php          ← Front Controller (único punto de entrada)
    │
    ▼
/src/Core/Router.php       ← Resuelve método + URI → Controlador@acción
    │
    ▼
/src/Core/Request.php      ← Normaliza $_GET, $_POST, php://input, headers
    │
    ▼
/src/Http/Middleware/
    ├── AuthMiddleware.php  ← Verifica JWT, inyecta tenant_id en contexto
    └── CorsMiddleware.php ← Headers CORS
    │
    ▼
/src/Http/Controllers/     ← Lógica del endpoint
    │
    ▼
/src/Models/               ← Acceso a BD (Active Record ligero)
    │
    ▼
/src/Core/Database.php     ← PDO singleton, prepared statements
    │
    ▼
Respuesta JSON: { success, message, data, meta? }
```

### Multi-Tenancy

Todas las tablas transaccionales tienen columna `tenant_id`. El middleware de autenticación extrae el `tenant_id` del JWT y lo inyecta en un contexto de request. Cada modelo lo usa automáticamente para filtrar.

**Excepciones (datos globales, solo SUPER_ADMIN):**
- `empresas` (tenants) — solo SUPER_ADMIN ve todo
- `usuarios` con `rol = 'SUPER_ADMIN' — sin filtro de tenant

## Estructura de Directorios

```
backend/
├── .env                          ← Variables de entorno (no versionado)
├── .gitignore
├── composer.json
├── composer.lock
├── config/
│   ├── database.php              ← Configuración MySQL (lee de .env)
│   └── routes.php                ← Registro de rutas (17 endpoints)
├── sql/
│   ├── schema.sql                ← Schema MySQL (4 tablas)
│   └── seed.sql                  ← Datos de prueba (3 empresas, 6 usuarios)
├── public/
│   └── index.php                 ← Front Controller
└── src/
    ├── Core/
    │   ├── Config.php            ← Loader de .env
    │   ├── Database.php          ← PDO singleton
    │   ├── Router.php            ← Mapeo URI → Controlador
    │   ├── Request.php           ← Wrapper de superglobals
    │   ├── Response.php          ← Helper respuestas JSON
    │   ├── TenantContext.php     ← Almacena tenant_id por request
    │   └── JwtHelper.php         ← Generar/verificar tokens JWT
    ├── Http/
    │   ├── Middleware/
    │   │   ├── AuthMiddleware.php ← Requiere JWT válido
    │   │   └── CorsMiddleware.php ← Headers CORS
    │   └── Controllers/
    │       ├── Controller.php     ← Clase base abstracta
    │       ├── AuthController.php ← Login, logout, refresh, me
    │       ├── TenantController.php ← CRUD empresas
    │       ├── UserController.php   ← CRUD usuarios
    │       └── DashboardController.php ← KPIs
    └── Models/
        ├── BaseModel.php          ← Active Record base con filtro tenant
        ├── Tenant.php             ← Modelo empresa/tenant
        └── User.php               ← Modelo usuario
```

## Requisitos

- PHP 8.2+ con extensiones: `pdo_mysql`, `json`, `mbstring`
- MySQL 8
- Composer
- LAMPP (Apache + MySQL) o similar

## Instalación

### 1. Clonar e instalar dependencias

```bash
cd backend
composer install
```

### 2. Configurar variables de entorno

```bash
cp .env.example .env
```

Editar `.env` con tus credenciales:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=callmetrics
DB_USER=root
DB_PASS=

JWT_SECRET=tu_clave_secreta_aqui_cambiala
JWT_ACCESS_EXPIRY=900
JWT_REFRESH_EXPIRY=604800

APP_ENV=development
APP_DEBUG=true
```

### 3. Crear base de datos y tablas

```bash
mysql -u root < sql/schema.sql
```

### 4. Cargar datos de prueba (opcional)

```bash
mysql -u root callmetrics < sql/seed.sql
```

### 5. Iniciar servidor de desarrollo

```bash
php -S localhost:8080 -t public/
```

La API estará disponible en `http://localhost:8080`

## Endpoints

### Autenticación

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| POST | `/api/auth/login` | No | Iniciar sesión |
| POST | `/api/auth/refresh` | No | Renovar tokens |
| POST | `/api/auth/logout` | Sí | Cerrar sesión |
| GET | `/api/auth/me` | Sí | Obtener usuario actual |
| PUT | `/api/auth/password` | Sí | Cambiar contraseña |
| PUT | `/api/auth/primer-ingreso` | No | Primer ingreso |

### Empresas (Solo SUPER_ADMIN)

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| GET | `/api/tenants` | SUPER_ADMIN | Listar empresas (paginado) |
| GET | `/api/tenants/{id}` | SUPER_ADMIN | Obtener empresa |
| POST | `/api/tenants` | SUPER_ADMIN | Crear empresa |
| PUT | `/api/tenants/{id}` | SUPER_ADMIN | Actualizar empresa |
| PATCH | `/api/tenants/{id}/toggle` | SUPER_ADMIN | Activar/desactivar |

### Usuarios

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| GET | `/api/usuarios` | Auth | Listar usuarios del tenant |
| GET | `/api/usuarios/{id}` | Auth | Obtener usuario |
| POST | `/api/usuarios` | ADMIN_TENANT | Crear usuario |
| PUT | `/api/usuarios/{id}` | ADMIN_TENANT | Actualizar usuario |
| PATCH | `/api/usuarios/{id}/toggle` | ADMIN_TENANT | Activar/desactivar |

### Dashboard

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| GET | `/api/dashboard/summary` | Auth | KPIs del dashboard |

## Autenticación

Los endpoints protegidos requieren header `Authorization`:

```
Authorization: Bearer <token_jwt>
```

### Estructura del Token JWT

**Access Token (15 min):**
```json
{
  "iss": "callmetrics",
  "iat": 1234567890,
  "exp": 1234568790,
  "sub": 1,
  "tid": 1,
  "role": "OPERADOR",
  "email": "usuario@empresa.com",
  "type": "access"
}
```

**Refresh Token (7 días):**
```json
{
  "iss": "callmetrics",
  "iat": 1234567890,
  "exp": 1235173490,
  "sub": 1,
  "type": "refresh"
}
```

### Jerarquía de Roles

| Rol | Nivel | Permisos |
|-----|-------|----------|
| SUPER_ADMIN | 4 | Acceso total, gestiona empresas y usuarios |
| ADMIN_TENANT | 3 | Gestiona usuarios de su tenant |
| SUPERVISOR | 2 | Supervisión de operadores |
| OPERADOR | 1 | Operaciones básicas |

## Formato de Respuesta

### Éxito

```json
{
  "success": true,
  "message": "Operación exitosa",
  "data": { ... }
}
```

### Con Paginación

```json
{
  "success": true,
  "message": "",
  "data": [ ... ],
  "meta": {
    "page": 0,
    "size": 10,
    "total": 50,
    "totalPages": 5
  }
}
```

### Error

```json
{
  "success": false,
  "message": "Mensaje de error",
  "data": null
}
```

### Error con Validaciones

```json
{
  "success": false,
  "message": "Errores de validación",
  "data": {
    "errors": {
      "email": "El email es inválido",
      "password": "Mínimo 6 caracteres"
    }
  }
}
```

## Datos de Prueba

El archivo `sql/seed.sql` crea:

### Empresas
| Empresa | NIT | Plan |
|---------|-----|------|
| Corporacion Alpha S.A. | 900123456-1 | ENTERPRISE |
| Tecnologia Beta S.L. | 900987654-2 | PRO |
| Grupo Gamma Corp. | 900555123-3 | ENTERPRISE |

### Usuarios (password: `demo123` para todos)

| Usuario | Email | Rol | Tenant |
|---------|-------|-----|--------|
| Carlos Admin | carlos@admin.com | SUPER_ADMIN | Global |
| Jorge Mendoza | jorge@alpha.com | ADMIN_TENANT | Alpha |
| Ana Supervisora | ana@alpha.com | SUPERVISOR | Alpha |
| Pedro Operador | pedro@alpha.com | OPERADOR | Alpha |
| Maria Gerente | maria@beta.com | ADMIN_TENANT | Beta |
| Luis Operador | luis@beta.com | OPERADOR | Beta |

## Ejemplos con cURL

### Login

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"carlos@admin.com","password":"demo123"}'
```

### Listar Empresas (requiere token SUPER_ADMIN)

```bash
curl http://localhost:8080/api/tenants \
  -H "Authorization: Bearer <token>"
```

### Crear Empresa

```bash
curl -X POST http://localhost:8080/api/tenants \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{
    "nombre": "Nueva Empresa S.A.",
    "nit": "900111222-4",
    "email": "contacto@nueva.com",
    "telefono": "+57 601 111 2233",
    "plan": "BASIC"
  }'
```

### Obtener Dashboard

```bash
curl http://localhost:8080/api/dashboard/summary \
  -H "Authorization: Bearer <token>"
```

## Seguridad

- Contraseñas hasheadas con **bcrypt** (cost 12)
- Tokens JWT con expiración (15 min access, 7 días refresh)
- Rate limiting en login (5 intentos / 15 min)
- Rotación de refresh tokens (el anterior se revoca)
- Filtrado automático de tenant_id en queries
- Headers CORS configurados
- Prepared statements para prevenir SQL injection

## Desarrollo

### Ejecutar Tests de Sintaxis

```bash
find src -name "*.php" -exec php -l {} \;
```

### Verificar Rutas

```bash
php -r "
require 'vendor/autoload.php';
\$routes = require 'config/routes.php';
echo count(\$routes) . ' rutas registradas' . PHP_EOL;
"
```

## Licencia

Proyecto privado — CallMetrics 4TO
