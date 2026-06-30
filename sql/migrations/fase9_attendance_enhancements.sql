-- ============================================================
-- FASE 9: Mejoras del Módulo Asistencia
--   - IP address en marcajes
--   - Justificación y estatus de incidencias
--   - Correcciones auditadas
--   - Nuevos permisos
-- ============================================================

USE sistema_rh;

-- -----------------------------------------------------------
-- 1. Columnas adicionales en attendance_logs
-- -----------------------------------------------------------
ALTER TABLE attendance_logs
  ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER hora_salida,
  ADD COLUMN justificacion VARCHAR(255) DEFAULT NULL AFTER ip_address,
  ADD COLUMN estatus ENUM('regular','justificado','incidencia') NOT NULL DEFAULT 'regular' AFTER justificacion;

-- -----------------------------------------------------------
-- 2. Tabla de correcciones auditadas (solo RH)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance_corrections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_log_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    campo_modificado VARCHAR(50) NOT NULL COMMENT 'hora_entrada|hora_salida|justificacion|estatus',
    valor_anterior VARCHAR(255) DEFAULT NULL,
    valor_nuevo VARCHAR(255) DEFAULT NULL,
    motivo VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attendance_log_id) REFERENCES attendance_logs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 3. Nuevos permisos
-- -----------------------------------------------------------
INSERT IGNORE INTO permissions (clave, nombre) VALUES
('attendance.correct', 'Corregir registros de asistencia'),
('attendance.export', 'Exportar reportes de asistencia');

-- Administrador RH (role_id = 1)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE clave IN ('attendance.correct','attendance.export');

-- Gerente RH (role_id = 2)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE clave IN ('attendance.correct','attendance.export');

-- Dirección (role_id = 5)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE clave IN ('attendance.export');
