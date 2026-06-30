-- ============================================================
-- FASE 5: Nómina y Dashboard
-- ============================================================

USE sistema_rh;

-- -----------------------------------------------------------
-- 18. Períodos de nómina
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS payroll_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    periodo VARCHAR(20) NOT NULL UNIQUE COMMENT 'Ej: 2026-06',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estatus ENUM('abierto','calculado','cerrado') NOT NULL DEFAULT 'abierto',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 19. Items de nómina por empleado
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS payroll_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    salario_base DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_bonos DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_deducciones DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_incidencias DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Descuentos por faltas/retardos',
    sueldo_bruto DECIMAL(12,2) NOT NULL DEFAULT 0,
    sueldo_neto DECIMAL(12,2) NOT NULL DEFAULT 0,
    dias_trabajados INT UNSIGNED NOT NULL DEFAULT 0,
    faltas INT UNSIGNED NOT NULL DEFAULT 0,
    retardos INT UNSIGNED NOT NULL DEFAULT 0,
    horas_extras DECIMAL(5,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY uk_periodo_empleado (period_id, employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Permisos para Fase 5
-- -----------------------------------------------------------
INSERT IGNORE INTO permissions (clave, nombre) VALUES
('payroll.read',     'Ver nómina'),
('payroll.calculate', 'Calcular nómina'),
('payroll.export',   'Exportar datos de nómina'),
('reports.dashboard', 'Ver dashboard'),
('reports.export',   'Exportar reportes');

-- Admin RH (1)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE clave IN ('payroll.read','payroll.calculate','payroll.export','reports.dashboard','reports.export');

-- Gerente RH (2)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE clave IN ('payroll.read','payroll.export','reports.dashboard','reports.export');

-- Dirección (5)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE clave IN ('payroll.read','reports.dashboard','reports.export');
