-- ============================================================
-- Fase 11: Mejoras al módulo Empleados
-- Contactos de emergencia, historial salarial, historial de contratos
-- ============================================================

USE sistema_rh;

-- -----------------------------------------------------------
-- 1. Contactos de emergencia
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS emergency_contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    parentesco VARCHAR(50) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    telefono_alternativo VARCHAR(20) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    es_principal TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 2. Historial salarial
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS salary_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    salario_anterior DECIMAL(12,2) DEFAULT NULL,
    salario_nuevo DECIMAL(12,2) NOT NULL,
    tipo_cambio ENUM('alta', 'incremento', 'decremento', 'ajuste') NOT NULL DEFAULT 'alta',
    motivo VARCHAR(255) DEFAULT NULL,
    modificado_por INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (modificado_por) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 3. Historial de contratos
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS contract_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    tipo_contrato_anterior VARCHAR(50) DEFAULT NULL,
    tipo_contrato_nuevo VARCHAR(50) NOT NULL,
    fecha_inicio DATE DEFAULT NULL,
    fecha_fin DATE DEFAULT NULL COMMENT 'NULL para contratos indefinidos',
    motivo VARCHAR(255) DEFAULT NULL,
    modificado_por INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (modificado_por) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
