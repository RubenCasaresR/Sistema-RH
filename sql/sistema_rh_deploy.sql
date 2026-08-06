-- ============================================================
-- SISTEMA RH - Bundle de despliegue (generado automáticamente)
-- Orden: schema + seed_data (roles/permisos/admin) + migraciones
-- fase2..fase21 + seed_data_charts (datos de ejemplo).
-- Se eliminaron las sentencias 'USE <base>;'.
-- ============================================================

-- >>> schema.sql
-- ============================================================
-- SISTEMA RH - Esquema de Base de Datos (Fase 1)
-- Core: Seguridad (RBAC) + Expediente Digital de Empleados
-- ============================================================

CREATE DATABASE IF NOT EXISTS sistema_rh
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;


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


-- >>> seed_data.sql
-- ============================================================
-- Datos iniciales para el Sistema RH
-- ============================================================


-- Roles
INSERT INTO roles (nombre, descripcion) VALUES
('Administrador RH', 'Control total del sistema'),
('Gerente RH', 'Gestión operativa de RH'),
('Jefe de área', 'Gestión de su equipo'),
('Empleado', 'Acceso básico a su información'),
('Dirección', 'Visión estratégica y reportes');

-- Permisos
INSERT INTO permissions (clave, nombre) VALUES
('employees.create', 'Crear empleados'),
('employees.read', 'Ver empleados'),
('employees.update', 'Editar empleados'),
('employees.delete', 'Eliminar empleados'),
('attendance.read', 'Ver asistencia'),
('attendance.clock', 'Registrar entrada/salida'),
('attendance.reports', 'Reportes de asistencia'),
('documents.upload', 'Subir documentos'),
('documents.read', 'Ver documentos'),
('documents.delete', 'Eliminar documentos'),
('leave.request', 'Solicitar vacaciones/permisos'),
('leave.approve', 'Aprobar vacaciones/permisos'),
('leave.read', 'Ver solicitudes'),
('announcements.create', 'Publicar anuncios'),
('announcements.read', 'Ver anuncios'),
('announcements.delete', 'Eliminar anuncios'),
('recruitment.create', 'Crear vacantes'),
('recruitment.read', 'Ver candidatos/vacantes'),
('recruitment.update', 'Editar vacantes'),
('recruitment.hire', 'Contratar candidatos'),
('performance.create', 'Crear evaluaciones'),
('performance.read', 'Ver evaluaciones'),
('performance.update', 'Editar evaluaciones'),
('payroll.read', 'Ver nómina'),
('payroll.calculate', 'Calcular nómina'),
('payroll.export', 'Exportar datos de nómina'),
('reports.dashboard', 'Ver dashboard'),
('reports.export', 'Exportar reportes');

-- Asignar todos los permisos al Administrador RH
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Asignar permisos específicos a otros roles (ejemplo para Gerente RH)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE clave NOT IN ('payroll.calculate');

-- Usuario administrador por defecto.
-- Contraseña generada aleatoriamente (no documentada). El primer login fuerza el cambio
-- de contraseña (ver fase6_security_audit.sql, columna password_change_required).
-- Para regenerar el hash: php -r "echo password_hash('CLAVE_TEMPORAL', PASSWORD_BCRYPT, ['cost'=>12]);"
INSERT INTO users (username, email, password_hash, role_id, activo) VALUES
('admin', 'admin@sistema-rh.com', '$2y$12$HPeIxSl7CJcT4yekVkIjiuDiedjuCHKK81IZ5Vzu8Bj4vqZPRpKD.', 1, 1);

-- Empleado de ejemplo
INSERT INTO employees (
    nombre, apellido_paterno, apellido_materno, curp, rfc, nss,
    fecha_nacimiento, genero, email, telefono,
    calle, numero_exterior, colonia, codigo_postal, ciudad, estado,
    puesto, departamento, fecha_ingreso, salario_base, tipo_contrato, user_id
) VALUES (
    'Admin', 'Sistema', 'RH', 'AXXX000101HDFRRN01', 'AXXX000101XXX', '12345678901',
    '1990-01-01', 'M', 'admin@sistema-rh.com', '5512345678',
    'Av. Principal', '123', 'Centro', '06600', 'Ciudad de México', 'CDMX',
    'Administrador del Sistema', 'TI', '2024-01-01', 35000.00, 'Confianza', 1
);


-- >>> migrations/password_resets.sql
CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL,
    token VARCHAR(64) NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- >>> migrations/fase2_atendance_documents.sql
-- ============================================================
-- FASE 2: Asistencia y Gestión de Documentos
-- ============================================================


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


-- >>> migrations/fase3_leave_announcements.sql
-- ============================================================
-- FASE 3: Vacaciones, Permisos, Incapacidades y Comunicación
-- ============================================================


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


-- >>> migrations/fase4_recruitment_performance.sql
-- ============================================================
-- FASE 4: Reclutamiento (ATS), Evaluación y Capacitación
-- ============================================================


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


-- >>> migrations/fase5_payroll_dashboard.sql
-- ============================================================
-- FASE 5: Nómina y Dashboard
-- ============================================================


-- -----------------------------------------------------------
-- 18. Períodos de nómina
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS payroll_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    periodo VARCHAR(20) NOT NULL UNIQUE COMMENT 'Ej: 2026-06',
    tipo_periodo ENUM('mensual', 'quincenal') NOT NULL DEFAULT 'mensual',
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


-- >>> migrations/fase6_security_audit.sql
-- ============================================================
-- FASE 6: Seguridad y Auditoría
-- Rate limiting, cambio de contraseña forzado, auditoría
-- ============================================================


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


-- >>> migrations/fase7_payroll_enhancements.sql
-- ============================================================
-- FASE 7: Mejora de cálculos de nómina
-- ISR, IMSS, Aguinaldo, Prima Vacacional, Subsidio al empleo
-- ============================================================


-- -----------------------------------------------------------
-- Agregar columnas de deducciones y percepciones a payroll_items
-- -----------------------------------------------------------
ALTER TABLE payroll_items
    ADD COLUMN isr_retener DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER horas_extras,
    ADD COLUMN imss_obrero DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER isr_retener,
    ADD COLUMN subsidio_empleo DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER imss_obrero,
    ADD COLUMN aguinaldo_proporcional DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subsidio_empleo,
    ADD COLUMN prima_vacacional DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER aguinaldo_proporcional,
    ADD COLUMN percepciones_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER prima_vacacional,
    ADD COLUMN deducciones_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER percepciones_total,
    ADD COLUMN salario_diario DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER deducciones_total;

-- -----------------------------------------------------------
-- Tabla para tarifas de ISR (actualizable cada año)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tax_isr_tariff (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ejercicio YEAR NOT NULL,
    tipo ENUM('mensual', 'quincenal', 'semanal') NOT NULL DEFAULT 'mensual',
    limite_inferior DECIMAL(12,2) NOT NULL,
    limite_superior DECIMAL(12,2) NOT NULL,
    cuota_fija DECIMAL(12,2) NOT NULL,
    porcentaje_excedente DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ejercicio_tipo (ejercicio, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Tabla para valor UMA (actualizable cada año)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tax_uma (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ejercicio YEAR NOT NULL,
    valor_diario DECIMAL(10,4) NOT NULL,
    valor_mensual DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ejercicio (ejercicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Tarifa ISR mensual 2025 (estimada con inflación ~4.5%)
-- Fuente: LISR Art. 96, ajustada por INPC estimado
-- -----------------------------------------------------------
INSERT INTO tax_isr_tariff (ejercicio, tipo, limite_inferior, limite_superior, cuota_fija, porcentaje_excedente) VALUES
(2025, 'mensual',       0.01,    780.00,       0.00,   1.92),
(2025, 'mensual',     780.01,   6620.00,      14.97,   6.40),
(2025, 'mensual',    6620.01,  11630.00,     388.73,  10.88),
(2025, 'mensual',   11630.01,  13520.00,     934.41,  16.00),
(2025, 'mensual',   13520.01,  16190.00,    1236.77,  17.92),
(2025, 'mensual',   16190.01,  32650.00,    1714.99,  21.36),
(2025, 'mensual',   32650.01,  38920.00,    5231.06,  23.52),
(2025, 'mensual',   38920.01,  58400.00,    6701.63,  30.00),
(2025, 'mensual',   58400.01, 116800.00,   12548.63,  32.00),
(2025, 'mensual',  116800.01, 232300.00,   31244.63,  34.00),
(2025, 'mensual',  232300.01, 999999.99,   70522.63,  35.00);

-- -----------------------------------------------------------
-- Tarifa ISR quincenal 2025
-- -----------------------------------------------------------
INSERT INTO tax_isr_tariff (ejercicio, tipo, limite_inferior, limite_superior, cuota_fija, porcentaje_excedente) VALUES
(2025, 'quincenal',       0.01,    390.00,       0.00,   1.92),
(2025, 'quincenal',     390.01,   3310.00,       7.48,   6.40),
(2025, 'quincenal',    3310.01,   5815.00,     194.37,  10.88),
(2025, 'quincenal',    5815.01,   6760.00,     467.20,  16.00),
(2025, 'quincenal',    6760.01,   8095.00,     618.39,  17.92),
(2025, 'quincenal',    8095.01,  16325.00,     857.49,  21.36),
(2025, 'quincenal',   16325.01,  19460.00,    2615.53,  23.52),
(2025, 'quincenal',   19460.01,  29200.00,    3350.82,  30.00),
(2025, 'quincenal',   29200.01,  58400.00,    6274.32,  32.00),
(2025, 'quincenal',   58400.01, 116150.00,   15622.32,  34.00),
(2025, 'quincenal',  116150.01, 999999.99,   35261.32,  35.00);

-- -----------------------------------------------------------
-- UMA 2025 (estimada, redondeada del valor publicado DOF)
-- -----------------------------------------------------------
INSERT INTO tax_uma (ejercicio, valor_diario, valor_mensual) VALUES (2025, 113.14, 3438.80);


-- >>> migrations/fase8_employees_enhancements.sql
-- ============================================================
-- FASE 8: Mejora del módulo Empleados
-- Catálogos departamentos/puestos, foto de perfil
-- ============================================================


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


-- >>> migrations/fase9_attendance_enhancements.sql
-- ============================================================
-- FASE 9: Mejoras del Módulo Asistencia
--   - IP address en marcajes
--   - Justificación y estatus de incidencias
--   - Correcciones auditadas
--   - Nuevos permisos
-- ============================================================


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


-- >>> migrations/fase10_announcements_enhancements.sql
-- ============================================================
-- FASE 10: Mejoras al módulo de Comunicados
-- ============================================================


-- Agregar columnas: fecha_expiracion, prioridad, updated_by
ALTER TABLE announcements
    ADD COLUMN fecha_expiracion DATE DEFAULT NULL AFTER updated_at,
    ADD COLUMN prioridad ENUM('alta', 'media', 'baja') NOT NULL DEFAULT 'media' AFTER fecha_expiracion,
    ADD COLUMN updated_by INT UNSIGNED DEFAULT NULL AFTER prioridad,
    ADD INDEX idx_anuncios_activo (activo),
    ADD INDEX idx_anuncios_created (created_at),
    ADD INDEX idx_anuncios_tipo (tipo),
    ADD INDEX idx_anuncios_activo_created (activo, created_at);


-- >>> migrations/fase10_document_versions.sql
-- ============================================================
-- Fase 10: Document Version History + Bulk Upload Support
-- ============================================================


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


-- >>> migrations/fase11_employees_enhancements.sql
-- ============================================================
-- Fase 11: Mejoras al módulo Empleados
-- Contactos de emergencia, historial salarial, historial de contratos
-- ============================================================


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


-- >>> migrations/fase11_recruitment_enhancements.sql
-- ============================================================
-- FASE 11: Mejoras al módulo de Reclutamiento
-- Índices para candidates y vacancies
-- ============================================================


ALTER TABLE candidates
    ADD INDEX idx_candidates_estatus (estatus),
    ADD INDEX idx_candidates_email (email);

ALTER TABLE vacancies
    ADD INDEX idx_vacancies_estatus (estatus);


-- >>> migrations/fase12_performance_enhancements.sql
-- ============================================
-- FASE 12: Performance & Training enhancements
-- Índices, nuevas columnas
-- ============================================

-- Índices performance_evaluations
ALTER TABLE performance_evaluations
  ADD INDEX idx_perf_employee (employee_id),
  ADD INDEX idx_perf_created (created_at),
  ADD INDEX idx_perf_estatus (estatus);

-- Índices training_history
ALTER TABLE training_history
  ADD INDEX idx_th_employee (employee_id),
  ADD INDEX idx_th_estatus (estatus),
  ADD INDEX idx_th_created (created_at);

-- Índices training_courses
ALTER TABLE training_courses
  ADD INDEX idx_tc_activo (activo),
  ADD INDEX idx_tc_tipo (tipo);


-- >>> migrations/fase13_payroll_enhancements.sql
-- ============================================
-- FASE 13: Payroll enhancements
-- Índices para optimizar consultas de nómina
-- ============================================

-- Índices payroll_periods
ALTER TABLE payroll_periods
  ADD INDEX idx_pp_estatus (estatus),
  ADD INDEX idx_pp_periodo (periodo);

-- Índices payroll_items (ya tiene FK index por InnoDB, pero explícito)
ALTER TABLE payroll_items
  ADD INDEX idx_pi_period_employee (period_id, employee_id);


-- >>> migrations/fase14_remaining_enhancements.sql
-- ============================================
-- FASE 14: Remaining modules enhancements
-- Índices para asistencia, empleados y vacaciones
-- ============================================

-- Índices attendance_logs (calendario, reportes, correcciones)
ALTER TABLE attendance_logs
  ADD INDEX idx_al_employee_fecha (employee_id, fecha),
  ADD INDEX idx_al_fecha (fecha);

-- Índices employees (búsquedas CURP/RFC y filtros)
ALTER TABLE employees
  ADD INDEX idx_emp_curp (curp),
  ADD INDEX idx_emp_rfc (rfc),
  ADD INDEX idx_emp_departamento (departamento);

-- Índices leave_balance (consultas de saldo)
ALTER TABLE leave_balance
  ADD INDEX idx_lb_employee_periodo (employee_id, periodo);

-- Índices leave_requests (aprobaciones y filtros)
ALTER TABLE leave_requests
  ADD INDEX idx_lr_employee_estatus (employee_id, estatus),
  ADD INDEX idx_lr_estatus_fecha (estatus, fecha_inicio);


-- >>> migrations/fase15_payroll_bonus.sql
CREATE TABLE IF NOT EXISTS payroll_bonus (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    concepto VARCHAR(100) NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_period_employee (period_id, employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- >>> migrations/fase16_payroll_subsidio.sql
CREATE TABLE IF NOT EXISTS tax_subsidio_tariff (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ejercicio YEAR NOT NULL,
    tipo ENUM('mensual', 'quincenal', 'semanal') NOT NULL DEFAULT 'mensual',
    limite_inferior DECIMAL(12,2) NOT NULL,
    limite_superior DECIMAL(12,2) NOT NULL,
    subsidio DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ejercicio_tipo (ejercicio, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tax_subsidio_tariff (ejercicio, tipo, limite_inferior, limite_superior, subsidio) VALUES
(2025, 'mensual',       0.01,   1768.32,    407.02),
(2025, 'mensual',    1768.33,   2276.77,    406.49),
(2025, 'mensual',    2276.78,   2674.38,    406.02),
(2025, 'mensual',    2674.39,   3071.99,    392.77),
(2025, 'mensual',    3072.00,   3867.23,    382.26),
(2025, 'mensual',    3867.24,   4662.47,    354.23),
(2025, 'mensual',    4662.48,   5457.71,    324.20),
(2025, 'mensual',    5457.72,   6252.95,    294.17),
(2025, 'mensual',    6252.96,   7048.18,    264.14),
(2025, 'mensual',    7048.19,   7843.42,    234.11),
(2025, 'mensual',    7843.43,   8638.66,    204.08),
(2025, 'mensual',    8638.67,   9433.90,    174.05),
(2025, 'mensual',    9433.91,  10229.14,    144.02),
(2025, 'mensual',   10229.15,  11024.38,    113.99),
(2025, 'mensual',   11024.39,  11819.62,     83.96),
(2025, 'mensual',   11819.63,  12614.86,     53.93),
(2025, 'mensual',   12614.87,  13410.10,     23.90),
(2025, 'mensual',   13410.11, 999999.99,      0.00);

INSERT INTO tax_subsidio_tariff (ejercicio, tipo, limite_inferior, limite_superior, subsidio) VALUES
(2025, 'quincenal',       0.01,    884.16,    203.51),
(2025, 'quincenal',     884.17,   1138.39,    203.25),
(2025, 'quincenal',    1138.40,   1337.19,    203.01),
(2025, 'quincenal',    1337.20,   1536.00,    196.39),
(2025, 'quincenal',    1536.01,   1933.62,    191.13),
(2025, 'quincenal',    1933.63,   2331.24,    177.12),
(2025, 'quincenal',    2331.25,   2728.86,    162.10),
(2025, 'quincenal',    2728.87,   3126.48,    147.09),
(2025, 'quincenal',    3126.49,   3524.09,    132.07),
(2025, 'quincenal',    3524.10,   3921.71,    117.06),
(2025, 'quincenal',    3921.72,   4319.33,    102.04),
(2025, 'quincenal',    4319.34,   4716.95,     87.03),
(2025, 'quincenal',    4716.96,   5114.57,     72.01),
(2025, 'quincenal',    5114.58,   5512.19,     57.00),
(2025, 'quincenal',    5512.20,   5909.81,     41.98),
(2025, 'quincenal',    5909.82,   6307.43,     26.97),
(2025, 'quincenal',    6307.44,   6705.05,     11.95),
(2025, 'quincenal',    6705.06, 999999.99,      0.00);


-- >>> migrations/fase17_payroll_adjustments.sql
CREATE TABLE IF NOT EXISTS payroll_adjustments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    tipo ENUM('percepcion', 'deduccion', 'falta', 'retardo', 'hora_extra') NOT NULL,
    concepto VARCHAR(100) NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_period_employee (period_id, employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- >>> migrations/fase18_retardo_deducciones.sql
ALTER TABLE payroll_items
    ADD COLUMN descuento_retardos DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER retardos;


-- >>> migrations/fase19_payroll_quincenal.sql
-- ============================================================
-- FASE 19: (CANCELADA)
-- La columna `tipo_periodo` ya fue agregada en FASE 5
-- (fase5_payroll_dashboard.sql). Esta migración originalmente
-- intentaba agregarla de nuevo, lo que provocaba
-- "Duplicate column name 'tipo_periodo'" al ejecutarse.
-- Mantener este archivo como no-op para no romper el orden de
-- las fases. La estructura quedó definida en fase5.
-- ============================================================


-- >>> migrations/fase20_tablero_control.sql
-- ============================================================
-- FASE 20: Tablero de Control RH
-- Tablas: control_tareas, control_avance, control_indicadores,
--         control_incidencias, control_checklist
-- ============================================================

-- 1. Catálogo de tareas del tablero
CREATE TABLE IF NOT EXISTS control_tareas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria ENUM('semanal','mensual','bimestral','semestral','permanente') NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    orden INT UNSIGNED DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Avance mensual de cada tarea (tablero anual)
CREATE TABLE IF NOT EXISTS control_avance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id INT UNSIGNED NOT NULL,
    anio YEAR NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    estatus ENUM('pendiente','en_proceso','completado','no_realizado','na') NOT NULL DEFAULT 'pendiente',
    notas TEXT DEFAULT NULL,
    completado_por INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tarea_periodo (tarea_id, anio, mes),
    CONSTRAINT fk_avance_tarea FOREIGN KEY (tarea_id) REFERENCES control_tareas(id) ON DELETE CASCADE,
    CONSTRAINT fk_avance_user FOREIGN KEY (completado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Indicadores mensuales calculados
CREATE TABLE IF NOT EXISTS control_indicadores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria VARCHAR(50) NOT NULL,
    indicador VARCHAR(150) NOT NULL,
    anio YEAR NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL DEFAULT 0,
    calculado_auto TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_indicador_periodo (indicador, anio, mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Bitácora de incidencias
CREATE TABLE IF NOT EXISTS control_incidencias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folio INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    personas_involucradas VARCHAR(500) NOT NULL,
    area VARCHAR(100) NOT NULL,
    tipo_incidencia ENUM('conflicto_interpersonal','queja','falta_disciplinaria','incumplimiento_politica','otro') NOT NULL,
    descripcion TEXT NOT NULL,
    atencion TEXT DEFAULT NULL,
    resultado ENUM('resuelto','en_seguimiento','escalado_direccion','sin_resolucion') NOT NULL DEFAULT 'en_seguimiento',
    fecha_seguimiento DATE DEFAULT NULL,
    registrado_por INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_folio (folio),
    CONSTRAINT fk_incidencia_user FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Checklist mensual
CREATE TABLE IF NOT EXISTS control_checklist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id INT UNSIGNED DEFAULT NULL,
    descripcion_tarea VARCHAR(200) NOT NULL,
    frecuencia VARCHAR(30) NOT NULL,
    semana VARCHAR(20) DEFAULT NULL,
    anio YEAR NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    estatus ENUM('completado','en_proceso','no_realizado','na') NOT NULL DEFAULT 'na',
    fecha_completado DATE DEFAULT NULL,
    notas TEXT DEFAULT NULL,
    completado_por INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_checklist_tarea FOREIGN KEY (tarea_id) REFERENCES control_tareas(id) ON DELETE SET NULL,
    CONSTRAINT fk_checklist_user FOREIGN KEY (completado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PERMISOS
-- ============================================================
INSERT IGNORE INTO permissions (clave, nombre) VALUES
    ('control.tablero', 'Ver tablero anual'),
    ('control.indicadores', 'Ver indicadores mensuales'),
    ('control.calcular', 'Recalcular indicadores'),
    ('control.incidencias.read', 'Ver bitácora de incidencias'),
    ('control.incidencias.create', 'Crear incidencia'),
    ('control.incidencias.update', 'Editar incidencia'),
    ('control.incidencias.delete', 'Eliminar incidencia'),
    ('control.checklist', 'Ver y editar checklist mensual');

-- ============================================================
-- SEMILLAS: Catálogo de tareas (22 tareas del Excel)
-- ============================================================
INSERT IGNORE INTO control_tareas (id, categoria, nombre, orden) VALUES
-- SEMANAL (3)
(1,  'semanal',   'Revisión de indicadores (faltas, retardos, incidencias)', 1),
(2,  'semanal',   'Observación del ambiente y clima', 2),
(3,  'semanal',   'Cierre de semana y actualización de bitácora', 3),
-- MENSUAL (7)
(4,  'mensual',   'Consolidado de indicadores del mes anterior', 1),
(5,  'mensual',   'Reporte de indicadores a supervisor / dirección', 2),
(6,  'mensual',   'Revisión del buzón de sugerencias', 3),
(7,  'mensual',   'Seguimiento de acuerdos y compromisos previos', 4),
(8,  'mensual',   'Auditoría de expedientes del personal', 5),
(9,  'mensual',   'Reconocimiento del mes (cumpleaños, logros)', 6),
(10, 'mensual',   'Revisión de actualizaciones al manual de procesos', 7),
-- BIMESTRAL (2)
(11, 'bimestral', 'Entrevistas individuales de seguimiento', 1),
(12, 'bimestral', 'Revisión de diagnóstico de necesidades por área', 2),
-- SEMESTRAL (4)
(13, 'semestral', 'Encuesta de clima laboral (Litwin y Stringer)', 1),
(14, 'semestral', 'Análisis de resultados y propuesta de mejoras', 2),
(15, 'semestral', 'Revisión general del manual de procesos', 3),
(16, 'semestral', 'Evaluación del estado de expedientes completos', 4),
-- PERMANENTE (6)
(17, 'permanente','Registro de incidencias y conflictos', 1),
(18, 'permanente','Integración de expedientes (altas)', 2),
(19, 'permanente','Cierre de expedientes (bajas)', 3),
(20, 'permanente','Atención a conflictos interpersonales', 4),
(21, 'permanente','Comunicados internos al personal', 5),
(22, 'permanente','Apoyo en procesos de reclutamiento activos', 6);

-- ============================================================
-- SEMILLAS: Checklist del mes actual (junio 2026)
-- ============================================================
INSERT IGNORE INTO control_checklist (descripcion_tarea, frecuencia, semana, anio, mes, estatus, fecha_completado) VALUES
-- SEMANAL (4 semanas x 3 tareas)
('Revisión de indicadores (faltas, retardos, incidencias)', 'semanal', 'Sem. 1', 2026, 6, 'completado', '2026-05-12'),
('Observación del ambiente y clima del despacho', 'semanal', 'Sem. 1', 2026, 6, 'completado', '2026-05-12'),
('Cierre de semana y actualización de bitácora', 'semanal', 'Sem. 1', 2026, 6, 'completado', '2026-05-12'),
('Revisión de indicadores (faltas, retardos, incidencias)', 'semanal', 'Sem. 2', 2026, 6, 'completado', '2026-05-12'),
('Observación del ambiente y clima del despacho', 'semanal', 'Sem. 2', 2026, 6, 'completado', '2026-05-12'),
('Cierre de semana y actualización de bitácora', 'semanal', 'Sem. 2', 2026, 6, 'completado', '2026-05-12'),
('Revisión de indicadores (faltas, retardos, incidencias)', 'semanal', 'Sem. 3', 2026, 6, 'completado', '2026-05-22'),
('Observación del ambiente y clima del despacho', 'semanal', 'Sem. 3', 2026, 6, 'completado', '2026-05-22'),
('Cierre de semana y actualización de bitácora', 'semanal', 'Sem. 3', 2026, 6, 'completado', '2026-05-22'),
('Revisión de indicadores (faltas, retardos, incidencias)', 'semanal', 'Sem. 4', 2026, 6, 'completado', '2026-05-29'),
('Observación del ambiente y clima del despacho', 'semanal', 'Sem. 4', 2026, 6, 'completado', '2026-05-29'),
('Cierre de semana y actualización de bitácora', 'semanal', 'Sem. 4', 2026, 6, 'completado', '2026-05-29'),
-- MENSUAL
('Consolidado de indicadores del mes anterior', 'mensual', NULL, 2026, 6, 'na', NULL),
('Reporte de indicadores a supervisor / dirección', 'mensual', NULL, 2026, 6, 'en_proceso', NULL),
('Revisión del buzón de sugerencias anónimo', 'mensual', NULL, 2026, 6, 'completado', NULL),
('Seguimiento de acuerdos y compromisos previos', 'mensual', NULL, 2026, 6, 'completado', NULL),
('Auditoría de expedientes del personal', 'mensual', NULL, 2026, 6, 'completado', NULL),
('Acción de reconocimiento del mes', 'mensual', NULL, 2026, 6, 'completado', NULL),
('Revisión de actualizaciones necesarias al manual', 'mensual', NULL, 2026, 6, 'en_proceso', NULL),
-- BIMESTRAL / SEMESTRAL
('Entrevistas individuales de seguimiento', 'bimestral', NULL, 2026, 6, 'completado', '2026-05-12'),
('Diagnóstico de necesidades por área', 'bimestral', NULL, 2026, 6, 'completado', '2026-05-12'),
('Encuesta de clima laboral', 'semestral', NULL, 2026, 6, 'completado', '2026-05-12'),
('Análisis de resultados y propuesta de mejoras', 'semestral', NULL, 2026, 6, 'completado', '2026-05-12'),
('Revisión general del manual de procesos', 'semestral', NULL, 2026, 6, 'completado', '2026-05-15'),
-- PERMANENTE
('Registro de incidencias y conflictos (cuando ocurran)', 'permanente', NULL, 2026, 6, 'na', NULL),
('Integración de expedientes de nuevas altas', 'permanente', NULL, 2026, 6, 'na', NULL),
('Cierre de expedientes de bajas', 'permanente', NULL, 2026, 6, 'na', NULL),
('Atención a conflictos interpersonales', 'permanente', NULL, 2026, 6, 'na', NULL),
('Comunicados internos según necesidad', 'permanente', NULL, 2026, 6, 'completado', NULL);

-- ============================================================
-- SEMILLA: Incidencia existente del Excel
-- ============================================================
INSERT IGNORE INTO control_incidencias (folio, fecha, personas_involucradas, area, tipo_incidencia, descripcion, atencion, resultado, fecha_seguimiento, registrado_por)
SELECT 1, '2026-05-09', 'BRENDA LUJAN; MICHELLE YANOME', 'TESORERIA', 'queja',
    'Ambas reportaron en la evaluación de clima laboral la presencia de tratos poco amigables y pasivo-agresivos, por parte de un miembro de otra área. Se cree que el motivo es debido a su reciente ingreso, pero no está claro.',
    'Al tomar conocimiento de la situación, se le preguntó a las partes afectadas sobre la persistencia de los hechos descritos, refiriendo el cese de los mismos desde días atrás.',
    'en_seguimiento', '2026-05-29',
    (SELECT id FROM users WHERE username = 'jdelgadillo' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM control_incidencias WHERE folio = 1);


-- >>> migrations/fase21_payroll_fixes.sql
-- ============================================================
-- FASE 21: Correcciones de cálculo de nómina
--  - Guarda el salario del período (proporcional a días laborables)
--  - Guarda los días laborables del empleado (lun-vie, según ingreso)
--  - Guarda el subsidio al empleo compensable (LISR)
-- NOTA: Requiere fase5 (tipo_periodo) aplicada. No es idempotente:
--       aplicarla dos veces lanzará "Duplicate column name".
-- ============================================================


ALTER TABLE payroll_items
    ADD COLUMN salario_periodo DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER salario_diario,
    ADD COLUMN dias_laborables INT UNSIGNED NOT NULL DEFAULT 0 AFTER dias_trabajados,
    ADD COLUMN subsidio_compensable DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subsidio_empleo;


-- >>> seed_data_charts.sql
-- ============================================================
-- Datos de semilla para gráficos del Dashboard
-- ============================================================


-- ============================================================
-- 1. EMPLEADOS ADICIONALES en distintos departamentos
-- ============================================================
INSERT IGNORE INTO employees (nombre, apellido_paterno, apellido_materno, curp, rfc, nss,
    fecha_nacimiento, genero, email, telefono, puesto, departamento, fecha_ingreso,
    salario_base, tipo_contrato, activo) VALUES
('María', 'García', 'López', 'GALM900101MDFRRN01', 'GALM900101XXX', '22345678901',
 '1990-01-01', 'F', 'maria.garcia@sistema-rh.com', '5512345679',
 'Coordinadora de RH', 'RH', '2023-06-15', 28000.00, 'Confianza', 1),

('Juan', 'Pérez', 'Martínez', 'PEMJ850315HDFRRN01', 'PEMJ850315XXX', '32345678901',
 '1985-03-15', 'M', 'juan.perez@sistema-rh.com', '5512345680',
 'Ejecutivo de Ventas', 'Ventas', '2022-11-01', 32000.00, 'Base', 1),

('Carlos', 'López', 'Hernández', 'LOHC920728HDFRRN01', 'LOHC920728XXX', '42345678901',
 '1992-07-28', 'M', 'carlos.lopez@sistema-rh.com', '5512345681',
 'Supervisor de Producción', 'Producción', '2024-02-01', 30000.00, 'Confianza', 1),

('Ana', 'Martínez', 'Díaz', 'MADA880512MDFRRN01', 'MADA880512XXX', '52345678901',
 '1988-05-12', 'F', 'ana.martinez@sistema-rh.com', '5512345682',
 'Analista Financiera', 'Finanzas', '2023-09-01', 34000.00, 'Confianza', 1);

-- ============================================================
-- 2. ASISTENCIA — últimos 7 días (incluye hoy)
-- ============================================================
-- Generar registros para cada empleado activo (IDs 1-5) en los últimos 7 días
-- Cada día: empleados 1-5 con hora_entrada, algunos con hora_salida
-- Días atrás variables para simular faltas ocasionales

-- Día -6 (hace 6 días)
INSERT INTO attendance_logs (employee_id, fecha, hora_entrada, hora_salida, tipo)
SELECT e.id, DATE_SUB(CURDATE(), INTERVAL 6 DAY),
       CONCAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), ' 08:30:00'),
       CONCAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), ' 17:45:00'), 'regular'
FROM employees e WHERE e.activo = 1 AND e.id IN (1,2,3,4,5);

-- Día -5 (hace 5 días) — Carlos ausente
INSERT INTO attendance_logs (employee_id, fecha, hora_entrada, hora_salida, tipo)
SELECT e.id, DATE_SUB(CURDATE(), INTERVAL 5 DAY),
       CONCAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), ' 08:45:00'),
       CONCAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), ' 18:00:00'), 'regular'
FROM employees e WHERE e.activo = 1 AND e.id IN (1,2,4,5);

-- Día -4
INSERT INTO attendance_logs (employee_id, fecha, hora_entrada, hora_salida, tipo)
SELECT e.id, DATE_SUB(CURDATE(), INTERVAL 4 DAY),
       CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 08:15:00'),
       CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 17:30:00'), 'regular'
FROM employees e WHERE e.activo = 1 AND e.id IN (1,2,3,4,5);

-- Día -3 — Ana ausente
INSERT INTO attendance_logs (employee_id, fecha, hora_entrada, hora_salida, tipo)
SELECT e.id, DATE_SUB(CURDATE(), INTERVAL 3 DAY),
       CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 09:00:00'),
       CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 18:15:00'), 'regular'
FROM employees e WHERE e.activo = 1 AND e.id IN (1,2,3,4);

-- Día -2 — todos presentes
INSERT INTO attendance_logs (employee_id, fecha, hora_entrada, hora_salida, tipo)
SELECT e.id, DATE_SUB(CURDATE(), INTERVAL 2 DAY),
       CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 08:30:00'),
       CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 17:45:00'), 'regular'
FROM employees e WHERE e.activo = 1 AND e.id IN (1,2,3,4,5);

-- Día -1 (ayer) — María y Juan ausentes
INSERT INTO attendance_logs (employee_id, fecha, hora_entrada, hora_salida, tipo)
SELECT e.id, DATE_SUB(CURDATE(), INTERVAL 1 DAY),
       CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 08:30:00'),
       CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 17:45:00'), 'regular'
FROM employees e WHERE e.activo = 1 AND e.id IN (1,4,5);

-- Día actual (hoy) — solo entrada registrada
INSERT INTO attendance_logs (employee_id, fecha, hora_entrada, tipo)
SELECT e.id, CURDATE(), CONCAT(CURDATE(), ' 08:30:00'), 'regular'
FROM employees e WHERE e.activo = 1 AND e.id IN (1,2,3,4,5);

-- Día -5 — registro de asistencia NULL para Carlos (ausente)
INSERT IGNORE INTO attendance_logs (employee_id, fecha, tipo) VALUES
(3, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'regular');
-- Día -3 — registro NULL para Ana (ausente)
INSERT IGNORE INTO attendance_logs (employee_id, fecha, tipo) VALUES
(5, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'regular');
-- Día -1 — registros NULL para María y Juan (ausentes)
INSERT IGNORE INTO attendance_logs (employee_id, fecha, tipo) VALUES
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'regular'),
(3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'regular');

-- ============================================================
-- 3. NÓMINA — períodos e ítems
-- ============================================================
-- Período 1: Mayo 2025
INSERT IGNORE INTO payroll_periods (periodo, fecha_inicio, fecha_fin, estatus)
VALUES ('Mayo 2025', '2025-05-01', '2025-05-31', 'cerrado');

INSERT INTO payroll_items (period_id, employee_id, salario_base, total_bonos, total_deducciones, total_incidencias, sueldo_bruto, sueldo_neto, dias_trabajados, faltas, retardos, horas_extras)
SELECT pp.id, e.id, e.salario_base, 1500.00, ROUND(e.salario_base * 0.08, 2), 0.00,
       ROUND(e.salario_base + 1500.00 - (e.salario_base * 0.08), 2),
       ROUND(e.salario_base + 1500.00 - (e.salario_base * 0.08) - (e.salario_base * 0.10), 2),
       22, 0, 1, 4.00
FROM payroll_periods pp
CROSS JOIN employees e
WHERE pp.periodo = 'Mayo 2025' AND e.activo = 1 AND e.id IN (1,2,3,4,5)
ON DUPLICATE KEY UPDATE sueldo_neto = VALUES(sueldo_neto);

-- Período 2: Junio 2025
INSERT IGNORE INTO payroll_periods (periodo, fecha_inicio, fecha_fin, estatus)
VALUES ('Junio 2025', '2025-06-01', '2025-06-30', 'calculado');

INSERT INTO payroll_items (period_id, employee_id, salario_base, total_bonos, total_deducciones, total_incidencias, sueldo_bruto, sueldo_neto, dias_trabajados, faltas, retardos, horas_extras)
SELECT pp.id, e.id, e.salario_base, 2000.00, ROUND(e.salario_base * 0.08, 2), ROUND(e.salario_base / 30 * 1, 2),
       ROUND(e.salario_base + 2000.00 - (e.salario_base * 0.08) - (e.salario_base / 30 * 1), 2),
       ROUND(e.salario_base + 2000.00 - (e.salario_base * 0.08) - (e.salario_base / 30 * 1) - (e.salario_base * 0.10), 2),
       21, 1, 2, 6.00
FROM payroll_periods pp
CROSS JOIN employees e
WHERE pp.periodo = 'Junio 2025' AND e.activo = 1 AND e.id IN (1,2,3,4,5)
ON DUPLICATE KEY UPDATE sueldo_neto = VALUES(sueldo_neto);

-- ============================================================
-- 4. SOLICITUDES DE VACACIONES (para el KPI de vacantes pendientes)
-- ============================================================
INSERT IGNORE INTO leave_requests (employee_id, tipo, fecha_inicio, fecha_fin, dias_solicitados, motivo, estatus)
VALUES (2, 'vacaciones', CURDATE() + INTERVAL 15 DAY, CURDATE() + INTERVAL 19 DAY, 5,
        'Vacaciones familiares programadas', 'pendiente'),
       (3, 'permiso_con_goce', CURDATE() + INTERVAL 5 DAY, CURDATE() + INTERVAL 5 DAY, 1,
        'Cita médica', 'aprobado'),
       (5, 'vacaciones', CURDATE() + INTERVAL 30 DAY, CURDATE() + INTERVAL 35 DAY, 6,
        'Viaje personal', 'pendiente');

-- ============================================================
-- 5. ANUNCIOS (para que la sección se vea con contenido)
-- ============================================================
INSERT IGNORE INTO announcements (titulo, contenido, tipo, publicado_por, activo)
VALUES ('Bienvenida a nuevos compañeros', 'Damos la bienvenida a Carlos López y Ana Martínez, quienes se incorporan a los departamentos de Producción y Finanzas respectivamente.', 'aviso', 1, 1),
       ('Política de home office actualizada', 'Se ha actualizado la política de trabajo remoto. Revisar el documento en el portal del empleado.', 'politica', 1, 1),
       ('Evento Día del Padre', 'El próximo viernes tendremos una convivencia por el Día del Padre en la sala de usos múltiples a las 13:00 hrs.', 'evento', 1, 1);


