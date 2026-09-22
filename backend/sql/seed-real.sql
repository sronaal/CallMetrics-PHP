-- ============================================================
-- CallMetrics — Seed completo para tenant_id=14, pbx_id=10
-- Genera datos realistas para que el frontend muestre todo
-- desde la base de datos (sin datos hardcodeados).
-- ============================================================
-- Ejecutar: /opt/lampp/bin/mysql -u root -h 127.0.0.1 -P 33060 callmetrics < seed-real.sql
-- ============================================================

SET @today = CURDATE();
SET @now   = NOW();
SET @tid   = 14;
SET @pbx   = 10;

-- ────────────────────────────────────────────────────────────
-- 1. Llamadas CDR de HOY (dashboard: llamadasHoy)
--    Mezcla de estados para que el dashboard tenga datos reales
-- ────────────────────────────────────────────────────────────
INSERT INTO llamadas_cdr (tenant_id, pbx_id, callid, extension_origen, extension_destino, numero_origen, numero_destino, contexto, duracion, billable_seconds, estado, inicio_llamada, fin_llamada, channel_origen, channel_destino) VALUES
-- Llamadas terminadas (hoy mañana)
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-001'), '1001', '1002', '3001234567', '3009876543', 'default', 245, 240, 'ANSWERED', CONCAT(@today, ' 08:15:00'), CONCAT(@today, ' 08:19:05'), 'PJSIP/1001-00000001', 'PJSIP/1002-00000002'),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-002'), '1003', '1001', '3005551234', '3001234567', 'default', 89, 85, 'ANSWERED', CONCAT(@today, ' 08:30:00'), CONCAT(@today, ' 08:31:29'), 'PJSIP/1003-00000003', 'PJSIP/1001-00000004'),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-003'), '1002', '1004', '3009876543', '3004445555', 'default', 0, 0, 'NOANSWER', CONCAT(@today, ' 09:00:00'), NULL, 'PJSIP/1002-00000005', NULL),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-004'), '1005', '1001', '3007778888', '3001234567', 'default', 512, 510, 'ANSWERED', CONCAT(@today, ' 09:15:00'), CONCAT(@today, ' 09:23:32'), 'PJSIP/1005-00000006', 'PJSIP/1001-00000007'),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-005'), '1001', '1003', '3001234567', '3005551234', 'default', 32, 30, 'BUSY', CONCAT(@today, ' 09:45:00'), CONCAT(@today, ' 09:45:32'), 'PJSIP/1001-00000008', 'PJSIP/1003-00000009'),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-006'), '1004', '1002', '3004445555', '3009876543', 'default', 180, 175, 'ANSWERED', CONCAT(@today, ' 10:00:00'), CONCAT(@today, ' 10:03:00'), 'PJSIP/1004-0000000a', 'PJSIP/1002-0000000b'),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-007'), '1002', '1005', '3009876543', '3007778888', 'default', 0, 0, 'FAILED', CONCAT(@today, ' 10:20:00'), NULL, 'PJSIP/1002-0000000c', NULL),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-008'), '1003', '1004', '3005551234', '3004445555', 'default', 420, 415, 'ANSWERED', CONCAT(@today, ' 10:30:00'), CONCAT(@today, ' 10:37:00'), 'PJSIP/1003-0000000d', 'PJSIP/1004-0000000e'),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-009'), '1005', '1001', '3007778888', '3001234567', 'default', 15, 10, 'NOANSWER', CONCAT(@today, ' 11:00:00'), NULL, 'PJSIP/1005-0000000f', NULL),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-010'), '1001', '1002', '3001234567', '3009876543', 'default', 95, 90, 'ANSWERED', CONCAT(@today, ' 11:15:00'), CONCAT(@today, ' 11:16:35'), 'PJSIP/1001-00000010', 'PJSIP/1002-00000011'),
-- Llamadas terminadas (hoy tarde)
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-011'), '1004', '1003', '3004445555', '3005551234', 'default', 270, 265, 'ANSWERED', CONCAT(@today, ' 13:00:00'), CONCAT(@today, ' 13:04:30'), 'PJSIP/1004-00000012', 'PJSIP/1003-00000013'),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-012'), '1002', '1005', '3009876543', '3007778888', 'default', 45, 40, 'BUSY', CONCAT(@today, ' 13:30:00'), CONCAT(@today, ' 13:30:45'), 'PJSIP/1002-00000014', 'PJSIP/1005-00000015'),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-013'), '1001', '1004', '3001234567', '3004445555', 'default', 0, 0, 'CANCELLED', CONCAT(@today, ' 14:00:00'), CONCAT(@today, ' 14:00:05'), 'PJSIP/1001-00000016', NULL),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-014'), '1005', '1003', '3007778888', '3005551234', 'default', 600, 595, 'ANSWERED', CONCAT(@today, ' 14:15:00'), CONCAT(@today, ' 14:25:00'), 'PJSIP/1005-00000017', 'PJSIP/1003-00000018'),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-015'), '1003', '1001', '3005551234', '3001234567', 'default', 0, 0, 'NOANSWER', CONCAT(@today, ' 14:45:00'), NULL, 'PJSIP/1003-00000019', NULL),
-- Llamadas activas AHORA (fin_llamada IS NULL)
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-016'), '1001', '1005', '3001234567', '3007778888', 'default', 120, 0, 'ANSWERED', CONCAT(@today, ' 15:00:00'), NULL, 'PJSIP/1001-0000001a', 'PJSIP/1005-0000001b'),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-017'), '1002', '1004', '3009876543', '3004445555', 'default', 45, 0, 'ANSWERED', CONCAT(@today, ' 15:05:00'), NULL, 'PJSIP/1002-0000001c', 'PJSIP/1004-0000001d'),
(@tid, @pbx, CONCAT('cm-', UNIX_TIMESTAMP(), '-018'), '1004', '1001', '3004445555', '3001234567', 'default', 10, 0, 'ANSWERED', CONCAT(@today, ' 15:08:00'), NULL, 'PJSIP/1004-0000001e', 'PJSIP/1001-0000001f');

-- ────────────────────────────────────────────────────────────
-- 2. Reglas de alerta (dashboard: alertasActivas)
-- ────────────────────────────────────────────────────────────
INSERT INTO reglas_alerta (tenant_id, nombre, tipo, condicion, umbral, unidad, notificar_email, notificar_web, activo) VALUES
(@tid, 'Llamadas perdidas alto volumen', 'LLAMADAS_PERDIDAS', 'MAYOR', 10, 'llamadas/hora', 1, 1, 1),
(@tid, 'CPU del servidor alto', 'CPU', 'MAYOR', 85, '%', 1, 1, 1),
(@tid, 'Memoria RAM baja', 'RAM', 'MAYOR', 90, '%', 1, 1, 1),
(@tid, 'Cola saturada', 'COLA_SATURADA', 'MAYOR', 15, 'llamadas en espera', 1, 1, 1),
(@tid, 'Troncal caída', 'TRONCAL_CAIDA', 'IGUAL', 0, 'activas', 1, 1, 1);

-- ────────────────────────────────────────────────────────────
-- 3. Historial de alertas (dashboard: alertasActivas, sección alerts)
-- ────────────────────────────────────────────────────────────
INSERT INTO historial_alertas (tenant_id, regla_id, valor_actual, mensaje, nivel, notificado, created_at) VALUES
(@tid, 1, 15.00, 'Llamadas perdidas: 15 en la última hora (umbral: 10)', 'WARNING', 0, CONCAT(@today, ' 10:30:00')),
(@tid, 2, 92.50, 'CPU al 92.5% — riesgo de degradación', 'CRITICAL', 0, CONCAT(@today, ' 11:00:00')),
(@tid, 4, 18.00, 'Cola "Ventas" con 18 llamadas en espera (umbral: 15)', 'WARNING', 0, CONCAT(@today, ' 11:15:00')),
(@tid, 3, 88.00, 'RAM al 88% — cerca del umbral crítico', 'INFO', 1, CONCAT(@today, ' 09:45:00')),
(@tid, 1, 12.00, 'Llamadas perdidas: 12 en la última hora (umbral: 10)', 'WARNING', 1, CONCAT(@today, ' 14:00:00')),
(@tid, 5, 0.00, 'Troncal principal caída detectada', 'CRITICAL', 0, CONCAT(@today, ' 14:30:00'));

-- ────────────────────────────────────────────────────────────
-- 4. Asegurar que los agentes tengan estados variados
--    (para que agentesActivos en dashboard muestre algo)
-- ────────────────────────────────────────────────────────────
UPDATE agentes SET estado = 'DISPONIBLE' WHERE id IN (1, 3, 5) AND tenant_id = @tid;
UPDATE agentes SET estado = 'OCUPADO'    WHERE id IN (2)      AND tenant_id = @tid;
UPDATE agentes SET estado = 'DESCONECTADO' WHERE id IN (4)    AND tenant_id = @tid;

-- ────────────────────────────────────────────────────────────
-- 5. Asegurar que las colas tengan estado ACTIVA
-- ────────────────────────────────────────────────────────────
UPDATE colas SET estado = 'ACTIVA', agentes_activos = 2 WHERE tenant_id = @tid;
UPDATE colas SET llamadas_enespera = 5 WHERE id = 1 AND tenant_id = @tid;
UPDATE colas SET llamadas_enespera = 2 WHERE id = 2 AND tenant_id = @tid;
UPDATE colas SET llamadas_enespera = 0 WHERE id = 3 AND tenant_id = @tid;

-- ────────────────────────────────────────────────────────────
-- 6. Actualizar contadores de agentes con datos realistas
-- ────────────────────────────────────────────────────────────
UPDATE agentes SET llamadas_atendidas = 45, tiempo_total_llamadas = 12600 WHERE id = 1 AND tenant_id = @tid;
UPDATE agentes SET llamadas_atendidas = 38, tiempo_total_llamadas = 10200 WHERE id = 2 AND tenant_id = @tid;
UPDATE agentes SET llamadas_atendidas = 52, tiempo_total_llamadas = 15800 WHERE id = 3 AND tenant_id = @tid;
UPDATE agentes SET llamadas_atendidas = 29, tiempo_total_llamadas = 8400  WHERE id = 4 AND tenant_id = @tid;
UPDATE agentes SET llamadas_atendidas = 41, tiempo_total_llamadas = 11400 WHERE id = 5 AND tenant_id = @tid;

-- ────────────────────────────────────────────────────────────
-- 7. Verificación final
-- ────────────────────────────────────────────────────────────
SELECT '=== VERIFICACIÓN DE DATOS ===' AS titulo;
SELECT 'empresas' AS tabla, COUNT(*) AS total FROM empresas WHERE id = @tid
UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios WHERE tenant_id = @tid
UNION ALL SELECT 'pbx', COUNT(*) FROM pbx WHERE tenant_id = @tid
UNION ALL SELECT 'extensiones', COUNT(*) FROM extensiones WHERE tenant_id = @tid
UNION ALL SELECT 'colas', COUNT(*) FROM colas WHERE tenant_id = @tid
UNION ALL SELECT 'agentes', COUNT(*) FROM agentes WHERE tenant_id = @tid
UNION ALL SELECT 'llamadas_cdr (hoy)', COUNT(*) FROM llamadas_cdr WHERE tenant_id = @tid AND DATE(inicio_llamada) = CURDATE()
UNION ALL SELECT 'llamadas_cdr (activas)', COUNT(*) FROM llamadas_cdr WHERE tenant_id = @tid AND fin_llamada IS NULL
UNION ALL SELECT 'reglas_alerta', COUNT(*) FROM reglas_alerta WHERE tenant_id = @tid
UNION ALL SELECT 'historial_alertas (sin notificar)', COUNT(*) FROM historial_alertas WHERE tenant_id = @tid AND notificado = 0
UNION ALL SELECT 'cdr_llamadas', COUNT(*) FROM cdr_llamadas WHERE tenant_id = @tid
UNION ALL SELECT 'cdr_agentes_resumen', COUNT(*) FROM cdr_agentes_resumen WHERE tenant_id = @tid
UNION ALL SELECT 'cdr_colas_resumen', COUNT(*) FROM cdr_colas_resumen WHERE tenant_id = @tid;
