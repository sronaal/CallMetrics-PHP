-- CDR Report tables: ingestion from agente-collector nested dataset format
-- Each table maps to one of the 5 datasets in the agent's "datos" payload.

-- cdr_llamadas: individual call records (datos.llamadasNormalizadas)
CREATE TABLE IF NOT EXISTS cdr_llamadas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    pbx_id INT UNSIGNED NOT NULL,
    linkedid VARCHAR(80) NOT NULL,
    fecha_inicio DATETIME DEFAULT NULL,
    numero_origen VARCHAR(80) DEFAULT NULL,
    destino_inicial VARCHAR(80) DEFAULT NULL,
    paso_por_cola VARCHAR(3) DEFAULT NULL,
    nombre_cola VARCHAR(128) DEFAULT NULL,
    extension_agente VARCHAR(80) DEFAULT NULL,
    nombre_agente VARCHAR(80) DEFAULT NULL,
    tiempo_conversacion VARCHAR(20) DEFAULT NULL,
    tiempo_timbrado VARCHAR(20) DEFAULT NULL,
    estado_final VARCHAR(20) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cdr_llamadas (tenant_id, linkedid),
    INDEX idx_cdr_ll_tenant (tenant_id),
    INDEX idx_cdr_ll_fecha (fecha_inicio),
    INDEX idx_cdr_ll_agente (extension_agente),
    INDEX idx_cdr_ll_cola (nombre_cola),
    CONSTRAINT fk_cdr_ll_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_cdr_ll_pbx FOREIGN KEY (pbx_id) REFERENCES pbx(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cdr_colas_resumen: queue statistics (datos.colasResumen)
CREATE TABLE IF NOT EXISTS cdr_colas_resumen (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    pbx_id INT UNSIGNED NOT NULL,
    numero_cola VARCHAR(80) NOT NULL,
    total_llamadas INT DEFAULT 0,
    contestadas INT DEFAULT 0,
    no_contestadas INT DEFAULT 0,
    ocupadas INT DEFAULT 0,
    fallidas INT DEFAULT 0,
    porcentaje_efectividad INT DEFAULT 0,
    promedio_espera VARCHAR(20) DEFAULT NULL,
    promedio_duracion VARCHAR(20) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cdr_colas (tenant_id, pbx_id, numero_cola),
    INDEX idx_cdr_cr_tenant (tenant_id),
    CONSTRAINT fk_cdr_cr_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_cdr_cr_pbx FOREIGN KEY (pbx_id) REFERENCES pbx(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cdr_agentes_resumen: agent statistics (datos.agentesResumen)
CREATE TABLE IF NOT EXISTS cdr_agentes_resumen (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    pbx_id INT UNSIGNED NOT NULL,
    extension_agente VARCHAR(80) NOT NULL,
    nombre_agente VARCHAR(80) DEFAULT NULL,
    contestadas INT DEFAULT 0,
    no_contestadas INT DEFAULT 0,
    ocupadas INT DEFAULT 0,
    fallidas INT DEFAULT 0,
    total_llamadas INT DEFAULT 0,
    porcentaje_efectividad INT DEFAULT 0,
    promedio_espera VARCHAR(20) DEFAULT NULL,
    promedio_duracion VARCHAR(20) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cdr_agentes (tenant_id, pbx_id, extension_agente),
    INDEX idx_cdr_ar_tenant (tenant_id),
    CONSTRAINT fk_cdr_ar_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_cdr_ar_pbx FOREIGN KEY (pbx_id) REFERENCES pbx(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cdr_estadisticas_colas: per-call queue stats (datos.estadisticasColas)
CREATE TABLE IF NOT EXISTS cdr_estadisticas_colas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    pbx_id INT UNSIGNED NOT NULL,
    linkedid VARCHAR(80) NOT NULL,
    numero_cola VARCHAR(80) DEFAULT NULL,
    fecha_entrada DATETIME DEFAULT NULL,
    estado_final VARCHAR(20) DEFAULT NULL,
    caller_id VARCHAR(80) DEFAULT NULL,
    agente_asignado VARCHAR(80) DEFAULT NULL,
    espera_seg INT DEFAULT 0,
    duracion_seg INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cdr_ec (tenant_id, linkedid),
    INDEX idx_cdr_ec_tenant (tenant_id),
    INDEX idx_cdr_ec_fecha (fecha_entrada),
    INDEX idx_cdr_ec_cola (numero_cola),
    CONSTRAINT fk_cdr_ec_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_cdr_ec_pbx FOREIGN KEY (pbx_id) REFERENCES pbx(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cdr_llamadas_real: real call records (datos.llamadasReal)
CREATE TABLE IF NOT EXISTS cdr_llamadas_real (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    pbx_id INT UNSIGNED NOT NULL,
    linkedid VARCHAR(80) NOT NULL,
    fecha_inicio DATETIME DEFAULT NULL,
    fecha_fin DATETIME DEFAULT NULL,
    total_segmentos INT DEFAULT 0,
    duracion_total INT DEFAULT 0,
    tiempo_total_conversacion INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cdr_lr (tenant_id, linkedid),
    INDEX idx_cdr_lr_tenant (tenant_id),
    INDEX idx_cdr_lr_fecha (fecha_inicio),
    CONSTRAINT fk_cdr_lr_tenant FOREIGN KEY (tenant_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_cdr_lr_pbx FOREIGN KEY (pbx_id) REFERENCES pbx(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
