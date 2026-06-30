-- ============================================================
-- FASE 4: Reclutamiento (ATS), Evaluación y Capacitación
-- ============================================================

USE sistema_rh;

-- -----------------------------------------------------------
-- 12. Vacantes
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS vacancies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT,
    requisitos TEXT,
    departamento VARCHAR(100),
    ubicacion VARCHAR(100),
    salario_min DECIMAL(12,2) DEFAULT NULL,
    salario_max DECIMAL(12,2) DEFAULT NULL,
    estatus ENUM('abierta','en_proceso','cerrada','cancelada') NOT NULL DEFAULT 'abierta',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 13. Candidatos
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS candidates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vacancy_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    apellido_paterno VARCHAR(80) NOT NULL,
    apellido_materno VARCHAR(80) DEFAULT NULL,
    email VARCHAR(120) NOT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    cv_ruta VARCHAR(500) DEFAULT NULL,
    estatus ENUM('recibido','revisado','entrevista','evaluacion','aceptado','rechazado','contratado') NOT NULL DEFAULT 'recibido',
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vacancy_id) REFERENCES vacancies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 14. Entrevistas de candidatos
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS candidate_interviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT UNSIGNED NOT NULL,
    fecha_hora DATETIME NOT NULL,
    tipo ENUM('presencial','virtual','telefonica') NOT NULL DEFAULT 'presencial',
    entrevistador VARCHAR(100) DEFAULT NULL,
    resultado TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 15. Evaluaciones de desempeño
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS performance_evaluations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    periodo VARCHAR(20) NOT NULL COMMENT 'Ej: 2026-Q1',
    evaluador INT UNSIGNED NOT NULL COMMENT 'ID del usuario que evalúa',
    calificacion DECIMAL(5,2) DEFAULT NULL,
    fortalezas TEXT,
    areas_mejora TEXT,
    retroalimentacion TEXT,
    estatus ENUM('borrador','completada') NOT NULL DEFAULT 'borrador',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluador) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 16. Catálogo de cursos / capacitación
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    tipo ENUM('curso','taller','certificacion','diplomado') NOT NULL DEFAULT 'curso',
    horas INT UNSIGNED NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- 17. Historial de capacitación por empleado
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    fecha_inicio DATE DEFAULT NULL,
    fecha_fin DATE DEFAULT NULL,
    estatus ENUM('inscrito','completado','cancelado') NOT NULL DEFAULT 'inscrito',
    calificacion DECIMAL(5,2) DEFAULT NULL,
    constancia_ruta VARCHAR(500) DEFAULT NULL COMMENT 'PDF de constancia/certificado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Permisos para Fase 4
-- -----------------------------------------------------------
INSERT IGNORE INTO permissions (clave, nombre) VALUES
('recruitment.create', 'Crear vacantes'),
('recruitment.read',   'Ver candidatos/vacantes'),
('recruitment.update', 'Editar vacantes'),
('recruitment.hire',   'Contratar candidatos'),
('performance.create', 'Crear evaluaciones'),
('performance.read',   'Ver evaluaciones'),
('performance.update', 'Editar evaluaciones');

-- Admin RH (1)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE clave IN ('recruitment.create','recruitment.read','recruitment.update','recruitment.hire','performance.create','performance.read','performance.update');

-- Gerente RH (2)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE clave IN ('recruitment.create','recruitment.read','recruitment.update','recruitment.hire','performance.create','performance.read','performance.update');

-- Jefe de área (3)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE clave IN ('performance.create','performance.read','performance.update');

-- Dirección (5)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE clave IN ('recruitment.read');
