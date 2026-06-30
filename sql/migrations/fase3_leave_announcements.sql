-- ============================================================
-- FASE 3: Vacaciones, Permisos, Incapacidades y Comunicación
-- ============================================================

USE sistema_rh;

-- -----------------------------------------------------------
-- 9. Solicitudes de vacaciones / permisos / incapacidades
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    tipo ENUM('vacaciones', 'permiso_con_goce', 'permiso_sin_goce', 'incapacidad') NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    dias_solicitados INT UNSIGNED NOT NULL,
    motivo TEXT DEFAULT NULL,
    estatus ENUM('pendiente', 'aprobado', 'rechazado', 'cancelado') NOT NULL DEFAULT 'pendiente',
    aprobado_por INT UNSIGNED DEFAULT NULL COMMENT 'ID del usuario que aprobó/rechazó',
    fecha_aprobacion DATETIME DEFAULT NULL,
    comentarios_aprobador TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (aprobado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 10. Saldo de vacaciones por empleado y período
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS leave_balance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    periodo YEAR NOT NULL COMMENT 'Año de ejercicio',
    dias_totales DECIMAL(5,2) NOT NULL DEFAULT 0,
    dias_disfrutados DECIMAL(5,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY uk_empleado_periodo (employee_id, periodo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 11. Anuncios / Comunicados internos
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    contenido TEXT NOT NULL,
    tipo ENUM('aviso', 'circular', 'politica', 'evento') NOT NULL DEFAULT 'aviso',
    publicado_por INT UNSIGNED NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (publicado_por) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Permisos adicionales para Fase 3
-- -----------------------------------------------------------
INSERT IGNORE INTO permissions (clave, nombre) VALUES
('leave.request',  'Solicitar vacaciones/permisos'),
('leave.approve',  'Aprobar vacaciones/permisos'),
('leave.read',     'Ver solicitudes de ausencia'),
('announcements.create', 'Publicar anuncios'),
('announcements.read',   'Ver anuncios'),
('announcements.delete', 'Eliminar anuncios');

-- Administrador RH (1)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE clave IN ('leave.request','leave.approve','leave.read','announcements.create','announcements.read','announcements.delete');

-- Gerente RH (2)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE clave IN ('leave.request','leave.approve','leave.read','announcements.create','announcements.read','announcements.delete');

-- Jefe de área (3)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE clave IN ('leave.request','leave.approve','leave.read','announcements.read');

-- Empleado (4)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE clave IN ('leave.request','leave.read','announcements.read');

-- Dirección (5)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE clave IN ('leave.read','announcements.read');
