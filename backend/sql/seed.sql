-- CallMetrics Seed Data
-- Password for all users: demo123

-- Empresas de prueba
INSERT INTO empresas (nombre, nit, email, telefono, plan, activo) VALUES
('Corporacion Alpha S.A.', '900123456-1', 'alpha@corporacion.com', '+57 601 234 5678', 'ENTERPRISE', 1),
('Tecnologia Beta S.L.',   '900987654-2', 'beta@tech.com',        '+57 601 876 5432', 'PRO', 1),
('Grupo Gamma Corp.',      '900555123-3', 'gamma@grupo.com',      '+57 601 555 1234', 'ENTERPRISE', 1);

-- Usuarios de prueba (password: demo123 para todos)
-- Hash: $2y$12$KEYQGh5Xd//STipop1vAhe6bAOFr7QRAPOxmtdKBr.WlutBpD6WBu
INSERT INTO usuarios (tenant_id, nombre, email, password_hash, rol, activo, primer_ingreso) VALUES
-- SUPER_ADMIN global
(NULL, 'Carlos Admin',    'carlos@admin.com',    '$$CAMBiar_EN_PRODUCCION$$' -- ⚠️ CAMBIAR INMEDIATAMENTE DESPUÉS DEL DEPLOY, 'SUPER_ADMIN', 1, 0),
-- Tenant 1: Alpha
(1, 'Jorge Mendoza',     'jorge@alpha.com',     '$$CAMBiar_EN_PRODUCCION$$' -- ⚠️ CAMBIAR INMEDIATAMENTE DESPUÉS DEL DEPLOY, 'ADMIN_TENANT', 1, 0),
(1, 'Ana Supervisora',   'ana@alpha.com',        '$$CAMBiar_EN_PRODUCCION$$' -- ⚠️ CAMBIAR INMEDIATAMENTE DESPUÉS DEL DEPLOY, 'SUPERVISOR', 1, 0),
(1, 'Pedro Operador',    'pedro@alpha.com',      '$$CAMBiar_EN_PRODUCCION$$' -- ⚠️ CAMBIAR INMEDIATAMENTE DESPUÉS DEL DEPLOY, 'OPERADOR', 1, 1),
-- Tenant 2: Beta
(2, 'Maria Gerente',     'maria@beta.com',       '$$CAMBiar_EN_PRODUCCION$$' -- ⚠️ CAMBIAR INMEDIATAMENTE DESPUÉS DEL DEPLOY, 'ADMIN_TENANT', 1, 0),
(2, 'Luis Operador',     'luis@beta.com',        '$$CAMBiar_EN_PRODUCCION$$' -- ⚠️ CAMBIAR INMEDIATAMENTE DESPUÉS DEL DEPLOY, 'OPERADOR', 1, 0);

-- PBX de prueba (tokens hasheados con SHA-256 — nunca almacenar en texto plano)
-- Token original: callmetrics-agent-token-2026
-- Token original: callmetrics-agent-token-backup
-- Token original: beta-agent-token-2026
INSERT INTO pbx (tenant_id, nombre, ip_address, puerto_ami, puerto_http, tipo, version, token_agente, estado, activo) VALUES
(1, 'Alpha PBX Principal', '192.168.1.10', 5038, 80, 'ASTERISK', '20.18', '5a5328a4ab0faef1398b29b332422a64620163a77fce0f58c44ff2b845f2972d', 'ONLINE', 1),
(1, 'Alpha PBX Backup',    '192.168.1.11', 5038, 80, 'ASTERISK', '20.18', '6ff5f16c1ed94d129334617000fe3ed087b993c7a1fc2e1b8615c4af4789342d', 'OFFLINE', 1),
(2, 'Beta PBX Central',    '10.0.0.5',     5038, 80, 'FREPBX',   '17.15', '2b712620608fca595af4a1a2d6e56d06ac8b541550762256952ac9d6fa701a12', 'ONLINE', 1);
