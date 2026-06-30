-- ============================================================
-- FASE 10: Mejoras al módulo de Comunicados
-- ============================================================

USE sistema_rh;

-- Agregar columnas: fecha_expiracion, prioridad, updated_by
ALTER TABLE announcements
    ADD COLUMN fecha_expiracion DATE DEFAULT NULL AFTER updated_at,
    ADD COLUMN prioridad ENUM('alta', 'media', 'baja') NOT NULL DEFAULT 'media' AFTER fecha_expiracion,
    ADD COLUMN updated_by INT UNSIGNED DEFAULT NULL AFTER prioridad,
    ADD INDEX idx_anuncios_activo (activo),
    ADD INDEX idx_anuncios_created (created_at),
    ADD INDEX idx_anuncios_tipo (tipo),
    ADD INDEX idx_anuncios_activo_created (activo, created_at);
