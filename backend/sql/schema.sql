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
-- Password: admin123 (bcrypt cost 12)
-- Hash generado con: password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12])
-- -----------------------------------------------------------
INSERT INTO usuarios (tenant_id, nombre, email, password_hash, rol, activo, primer_ingreso)
VALUES (
    NULL,
    'Super Administrador',
    'admin@callmetrics.com',
    '$2y$12$7wtsI3OxazT9r4yUW7lGHuctInmNNb5VPvlGQ7l3MjphHyBjsgvYG',
    'SUPER_ADMIN',
    1,
    0
);
