-- ============================================================
-- FASE 2: Asistencia y Gestión de Documentos
-- ============================================================

USE sistema_rh;

-- -----------------------------------------------------------
-- 6. Registros de asistencia (reloj checador)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL COMMENT 'Fecha del registro',
    hora_entrada DATETIME DEFAULT NULL,
    hora_salida DATETIME DEFAULT NULL,
    tipo ENUM('regular', 'extra') NOT NULL DEFAULT 'regular',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY uk_fecha_empleado_tipo (employee_id, fecha, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 7. Documentos de empleados
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS employee_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    tipo_documento VARCHAR(60) NOT NULL COMMENT 'Contrato, INE, Comprobante de domicilio, Acta de nacimiento, Constancia, Certificado, Otro',
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL COMMENT 'Nombre único en el servidor',
    archivo_ruta VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    peso_bytes INT UNSIGNED NOT NULL,
    hash_firma VARCHAR(64) DEFAULT NULL COMMENT 'SHA-256 de aceptación digital',
    fecha_firma DATETIME DEFAULT NULL,
    notas VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 8. Permisos adicionales para Fase 2
-- -----------------------------------------------------------
INSERT IGNORE INTO permissions (clave, nombre) VALUES
('attendance.clock', 'Registrar entrada/salida'),
('attendance.read', 'Ver asistencia'),
('attendance.reports', 'Reportes de asistencia'),
('documents.upload', 'Subir documentos'),
('documents.read', 'Ver documentos'),
('documents.delete', 'Eliminar documentos');

-- Asignar los nuevos permisos al Administrador RH (role_id = 1)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE clave IN ('attendance.clock','attendance.read','attendance.reports','documents.upload','documents.read','documents.delete');

-- Asignar a Gerente RH (role_id = 2)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE clave IN ('attendance.clock','attendance.read','attendance.reports','documents.upload','documents.read','documents.delete');

-- Asignar a Jefe de área (role_id = 3)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE clave IN ('attendance.read','attendance.reports');

-- Asignar a Empleado (role_id = 4)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE clave IN ('attendance.clock','documents.read');

-- Asignar a Dirección (role_id = 5)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE clave IN ('attendance.read','attendance.reports','documents.read');
