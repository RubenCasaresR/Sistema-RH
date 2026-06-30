-- ============================================================
-- FASE 6: Seguridad y Auditoría
-- Rate limiting, cambio de contraseña forzado, auditoría
-- ============================================================

USE sistema_rh;

-- -----------------------------------------------------------
-- 20. Intentos de inicio de sesión (rate limiting)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(50) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_username (ip_address, username),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 21. Forzar cambio de contraseña en primer inicio
-- -----------------------------------------------------------
ALTER TABLE users
    ADD COLUMN password_change_required TINYINT(1) NOT NULL DEFAULT 0 AFTER activo,
    ADD COLUMN force_logout TINYINT(1) NOT NULL DEFAULT 0 AFTER password_change_required;

-- Marcar al admin por defecto para que cambie contraseña
UPDATE users SET password_change_required = 1 WHERE username = 'admin';

-- -----------------------------------------------------------
-- 22. Registro de auditoría
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL COMMENT 'create, update, delete, approve, calculate, login, etc.',
    entity_type VARCHAR(50) NOT NULL COMMENT 'employee, document, leave, payroll, etc.',
    entity_id INT UNSIGNED DEFAULT NULL,
    details TEXT DEFAULT NULL COMMENT 'JSON con datos relevantes del cambio',
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
