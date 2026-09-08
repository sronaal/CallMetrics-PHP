-- ============================================================
-- FASE 6: Expansión de Base de Datos — CallMetrics
-- ============================================================
-- Este script crea las tablas necesarias para el monitoreo
-- telefónico: PBX, extensiones, colas, agentes, CDR y eventos.
-- ============================================================

-- Tabla: pbx (servidores telefónicos)
CREATE TABLE IF NOT EXISTS pbx (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    nombre          VARCHAR(100) NOT NULL,
    ip_address      VARCHAR(45) NOT NULL,
    puerto_ami      INT DEFAULT 5038,
    puerto_http     INT DEFAULT 80,
    tipo            ENUM('ASTERISK','FREPBX','OTRO') DEFAULT 'ASTERISK',
    version         VARCHAR(20) DEFAULT NULL,
    token_agente    VARCHAR(255) NOT NULL, -- Token único para autenticar agente
    estado          ENUM('ONLINE','OFFLINE','ERROR') DEFAULT 'OFFLINE',
    ultimo_heartbeat DATETIME DEFAULT NULL,
    activo          TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pbx_tenant (tenant_id),
    INDEX idx_pbx_estado (estado),
    CONSTRAINT fk_pbx_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla: extensiones (usuarios SIP)
CREATE TABLE IF NOT EXISTS extensiones (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    pbx_id          INT UNSIGNED NOT NULL,
    numero          VARCHAR(20) NOT NULL,
    nombre          VARCHAR(100) DEFAULT NULL,
    tipo            ENUM('PEER','SIP','PJSIP','IAX2') DEFAULT 'SIP',
    estado          ENUM('ONLINE','OFFLINE','BUSY','UNREACHABLE') DEFAULT 'OFFLINE',
    activo          TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ext_pbx_numero (pbx_id, numero),
    INDEX idx_ext_tenant (tenant_id),
    INDEX idx_ext_pbx (pbx_id),
    CONSTRAINT fk_ext_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_ext_pbx FOREIGN KEY (pbx_id) REFERENCES pbx(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla: colas (queues de atención)
CREATE TABLE IF NOT EXISTS colas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    pbx_id          INT UNSIGNED NOT NULL,
    nombre          VARCHAR(100) NOT NULL,
    estrategia      ENUM('RINGALL','LEASTRECENT','FEWESTCALLS','RANDOM','RRMEMORY') DEFAULT 'RINGALL',
    max_waiting     INT DEFAULT 300,
    mus_on_hold     VARCHAR(100) DEFAULT 'default',
    estado          ENUM('ACTIVA','PAUSADA','INACTIVA') DEFAULT 'ACTIVA',
    agentes_activos INT DEFAULT 0,
    llamadas_enespera INT DEFAULT 0,
    activo          TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cola_pbx_nombre (pbx_id, nombre),
    INDEX idx_cola_tenant (tenant_id),
    INDEX idx_cola_pbx (pbx_id),
    CONSTRAINT fk_cola_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_cola_pbx FOREIGN KEY (pbx_id) REFERENCES pbx(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla: agentes (operadores telefónicos)
CREATE TABLE IF NOT EXISTS agentes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    usuario_id      INT UNSIGNED DEFAULT NULL, -- Vinculado a usuario del sistema
    extension_id    INT UNSIGNED DEFAULT NULL,
    cola_id         INT UNSIGNED DEFAULT NULL,
    nombre          VARCHAR(100) NOT NULL,
    estado          ENUM('DISPONIBLE','OCUPADO','DESCONECTADO','EN_LLAMADA') DEFAULT 'DESCONECTADO',
    llamadas_atendidas INT DEFAULT 0,
    tiempo_total_llamadas INT DEFAULT 0, -- segundos
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_agente_tenant (tenant_id),
    INDEX idx_agente_usuario (usuario_id),
    INDEX idx_agente_extension (extension_id),
    INDEX idx_agente_cola (cola_id),
    CONSTRAINT fk_agente_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_agente_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_agente_extension FOREIGN KEY (extension_id) REFERENCES extensiones(id) ON DELETE SET NULL,
    CONSTRAINT fk_agente_cola FOREIGN KEY (cola_id) REFERENCES colas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla: llamadas_cdr (Call Detail Records)
CREATE TABLE IF NOT EXISTS llamadas_cdr (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    pbx_id          INT UNSIGNED NOT NULL,
    callid          VARCHAR(80) NOT NULL, -- Unique ID de la llamada
    extension_origen VARCHAR(20) DEFAULT NULL,
    extension_destino VARCHAR(20) DEFAULT NULL,
    numero_origen   VARCHAR(50) DEFAULT NULL,
    numero_destino  VARCHAR(50) DEFAULT NULL,
    contexto        VARCHAR(50) DEFAULT NULL,
    duracion        INT DEFAULT 0, -- segundos
    billable_seconds INT DEFAULT 0,
    estado          ENUM('ANSWERED','NOANSWER','BUSY','FAILED','CANCELLED') DEFAULT 'FAILED',
    inicio_llamada  DATETIME NOT NULL,
    fin_llamada     DATETIME DEFAULT NULL,
    grabacion_url   VARCHAR(500) DEFAULT NULL,
    channel_origen  VARCHAR(100) DEFAULT NULL,
    channel_destino VARCHAR(100) DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cdr_tenant (tenant_id),
    INDEX idx_cdr_pbx (pbx_id),
    INDEX idx_cdr_fecha (inicio_llamada),
    INDEX idx_cdr_callid (callid),
    INDEX idx_cdr_estado (estado),
    CONSTRAINT fk_cdr_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_cdr_pbx FOREIGN KEY (pbx_id) REFERENCES pbx(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla: eventos (AMI, CEL, monitoreo)
CREATE TABLE IF NOT EXISTS eventos (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    pbx_id          INT UNSIGNED NOT NULL,
    tipo            ENUM('AMI','CEL','HEALTH','SYSTEM') NOT NULL,
    evento          VARCHAR(100) NOT NULL, -- Nombre del evento AMI o tipo CEL
    contenido       JSON NOT NULL, -- Payload completo del evento
    callid          VARCHAR(80) DEFAULT NULL, -- Relación con llamada
    procesado       TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_evento_tenant (tenant_id),
    INDEX idx_evento_pbx (pbx_id),
    INDEX idx_evento_tipo (tipo),
    INDEX idx_evento_nombre (evento),
    INDEX idx_evento_fecha (created_at),
    INDEX idx_evento_callid (callid),
    CONSTRAINT fk_evento_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_evento_pbx FOREIGN KEY (pbx_id) REFERENCES pbx(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla: reglas_alerta
CREATE TABLE IF NOT EXISTS reglas_alerta (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    nombre          VARCHAR(100) NOT NULL,
    tipo            ENUM('LLAMADAS_PERDIDAS','CPU','RAM','COLA_SATURADA','TRONCAL_CAIDA') NOT NULL,
    condicion       ENUM('MAYOR','MENOR','IGUAL') DEFAULT 'MAYOR',
    umbral          DECIMAL(10,2) NOT NULL,
    unidad          VARCHAR(20) DEFAULT NULL, -- '%', 'llamadas/min', etc
    notificar_email TINYINT(1) DEFAULT 1,
    notificar_web   TINYINT(1) DEFAULT 1,
    activo          TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_alerta_tenant (tenant_id),
    INDEX idx_alerta_tipo (tipo),
    INDEX idx_alerta_activo (activo),
    CONSTRAINT fk_alerta_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla: historial_alertas (log de alertas disparadas)
CREATE TABLE IF NOT EXISTS historial_alertas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    regla_id        INT UNSIGNED NOT NULL,
    valor_actual    DECIMAL(10,2) NOT NULL,
    mensaje         TEXT NOT NULL,
    nivel           ENUM('INFO','WARNING','CRITICAL') DEFAULT 'WARNING',
    notificado      TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_histalerta_tenant (tenant_id),
    INDEX idx_histalerta_regla (regla_id),
    INDEX idx_histalerta_fecha (created_at),
    CONSTRAINT fk_histalerta_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_histalerta_regla FOREIGN KEY (regla_id) REFERENCES reglas_alerta(id) ON DELETE CASCADE
) ENGINE=InnoDB;
