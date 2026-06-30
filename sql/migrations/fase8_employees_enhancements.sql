-- ============================================================
-- FASE 8: Mejora del módulo Empleados
-- Catálogos departamentos/puestos, foto de perfil
-- ============================================================

USE sistema_rh;

-- -----------------------------------------------------------
-- 25. Catálogo de departamentos
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 26. Catálogo de puestos
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS positions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255) DEFAULT NULL,
    salario_min DECIMAL(12,2) DEFAULT NULL,
    salario_max DECIMAL(12,2) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Poblar departamentos desde datos existentes
-- -----------------------------------------------------------
INSERT IGNORE INTO departments (nombre)
    SELECT DISTINCT TRIM(departamento) FROM employees
    WHERE departamento IS NOT NULL AND TRIM(departamento) != ''
    ORDER BY departamento;

-- Insertar departamentos comunes si no existen
INSERT IGNORE INTO departments (nombre) VALUES
    ('Dirección General'),
    ('Recursos Humanos'),
    ('Finanzas'),
    ('Contabilidad'),
    ('Tecnología'),
    ('Sistemas'),
    ('Ventas'),
    ('Marketing'),
    ('Operaciones'),
    ('Producción'),
    ('Logística'),
    ('Almacén'),
    ('Compras'),
    ('Atención a Clientes'),
    ('Servicio al Cliente'),
    ('Calidad'),
    ('Investigación y Desarrollo'),
    ('Legal'),
    ('Administración'),
    ('Mantenimiento');

-- -----------------------------------------------------------
-- Poblar puestos desde datos existentes
-- -----------------------------------------------------------
INSERT IGNORE INTO positions (nombre)
    SELECT DISTINCT TRIM(puesto) FROM employees
    WHERE puesto IS NOT NULL AND TRIM(puesto) != ''
    ORDER BY puesto;

-- Insertar puestos comunes si no existen
INSERT IGNORE INTO positions (nombre) VALUES
    ('Director General'),
    ('Gerente de RH'),
    ('Gerente de Finanzas'),
    ('Gerente de TI'),
    ('Gerente de Ventas'),
    ('Coordinador'),
    ('Supervisor'),
    ('Analista'),
    ('Desarrollador'),
    ('Administrativo'),
    ('Asistente'),
    ('Secretario'),
    ('Contador'),
    ('Vendedor'),
    ('Ejecutivo de Cuenta'),
    ('Practicante'),
    ('Operador'),
    ('Técnico'),
    ('Recepcionista'),
    ('Chofer'),
    ('Auxiliar'),
    ('Consultor');

-- -----------------------------------------------------------
-- Agregar columna de foto de perfil si no existe
-- -----------------------------------------------------------
-- Ya existe: foto_url VARCHAR(255) DEFAULT NULL

-- -----------------------------------------------------------
-- Nuevos permisos para exportar empleados
-- -----------------------------------------------------------
INSERT IGNORE INTO permissions (clave, nombre) VALUES
    ('employees.export', 'Exportar empleados a CSV');

-- Asignar permiso a roles existentes (Admin RH, Gerente RH, Dirección)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
    SELECT 1, id FROM permissions WHERE clave = 'employees.export';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
    SELECT 2, id FROM permissions WHERE clave = 'employees.export';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
    SELECT 5, id FROM permissions WHERE clave = 'employees.export';
