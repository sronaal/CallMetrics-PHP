-- ============================================================
-- MIGRATION: Agregar columna agente_id a tabla pbx
-- ============================================================
-- Compatible con agente Python (X-Agent-ID header) y futuro
-- agente Spring/Java. El agente_id es un UUID único que
-- identifica cada instancia del agente collector.
-- ============================================================

-- Agregar columna agente_id (UUID del agente collector)
ALTER TABLE pbx
    ADD COLUMN agente_id VARCHAR(36) DEFAULT NULL
    AFTER token_agente;

-- Índice único para búsquedas rápidas por agente_id
-- UNIQUE porque cada agente collector instance = 1 PBX
ALTER TABLE pbx
    ADD UNIQUE INDEX idx_pbx_agente_id (agente_id);

-- ============================================================
-- NOTA: Para PBX existentes, generar un UUID y asignarlo:
-- UPDATE pbx SET agente_id = UUID() WHERE agente_id IS NULL;
-- ============================================================
