-- ============================================================
-- SISTEMA RH - Esquema de Base de Datos (Fase 1)
-- Core: Seguridad (RBAC) + Expediente Digital de Empleados
-- ============================================================

CREATE DATABASE IF NOT EXISTS sistema_rh
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sistema_rh;

-- -----------------------------------------------------------
-- 1. Roles
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 2. Permisos
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(80) NOT NULL UNIQUE COMMENT 'Identificador interno del permiso (ej. employees.create)',
    nombre VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 3. Relación Rol <-> Permiso
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 4. Usuarios del sistema
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 5. Empleados (Expediente Digital)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL COMMENT 'Relación opcional con usuario del sistema',
    -- Datos personales
    nombre VARCHAR(80) NOT NULL,
    apellido_paterno VARCHAR(80) NOT NULL,
    apellido_materno VARCHAR(80) DEFAULT NULL,
    curp CHAR(18) NOT NULL UNIQUE,
    rfc CHAR(13) NOT NULL UNIQUE,
    nss CHAR(11) NOT NULL UNIQUE COMMENT 'Número de Seguridad Social (11 dígitos)',
    fecha_nacimiento DATE DEFAULT NULL,
    genero ENUM('M', 'F', 'Otro') DEFAULT NULL,
    -- Contacto y domicilio
    email VARCHAR(120) DEFAULT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    calle VARCHAR(150) DEFAULT NULL,
    numero_exterior VARCHAR(20) DEFAULT NULL,
    numero_interior VARCHAR(20) DEFAULT NULL,
    colonia VARCHAR(100) DEFAULT NULL,
    codigo_postal VARCHAR(10) DEFAULT NULL,
    ciudad VARCHAR(80) DEFAULT NULL,
    estado VARCHAR(80) DEFAULT NULL,
    pais VARCHAR(60) DEFAULT 'México',
    -- Relación laboral
    puesto VARCHAR(100) DEFAULT NULL,
    departamento VARCHAR(100) DEFAULT NULL,
    fecha_ingreso DATE DEFAULT NULL,
    salario_base DECIMAL(12,2) DEFAULT NULL,
    tipo_contrato ENUM('Base', 'Confianza', 'Temporal', 'Honorarios', 'Outsourcing', 'Becario') DEFAULT 'Base',
    -- Control
    foto_url VARCHAR(255) DEFAULT NULL,
    notas TEXT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = baja lógica',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
