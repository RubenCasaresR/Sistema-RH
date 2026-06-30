-- ============================================================
-- Fase 10: Document Version History + Bulk Upload Support
-- ============================================================

USE sistema_rh;

-- -----------------------------------------------------------
-- 1. Tabla de versiones de documentos
--    Cada vez que se sube un nuevo documento del mismo tipo
--    para el mismo empleado, el anterior pasa aquí.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `document_versions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `document_id` INT UNSIGNED NOT NULL,
    `version_number` INT UNSIGNED NOT NULL COMMENT 'Número de versión secuencial',
    `nombre_original` VARCHAR(255) NOT NULL,
    `nombre_archivo` VARCHAR(255) NOT NULL COMMENT 'Nombre único en el servidor',
    `archivo_ruta` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `peso_bytes` INT UNSIGNED NOT NULL,
    `hash_firma` VARCHAR(64) DEFAULT NULL COMMENT 'SHA-256',
    `fecha_firma` DATETIME DEFAULT NULL,
    `subido_por` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`document_id`) REFERENCES `employee_documents`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subido_por`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
