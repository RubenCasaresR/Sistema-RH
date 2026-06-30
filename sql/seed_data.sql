-- ============================================================
-- Datos iniciales para el Sistema RH
-- ============================================================

USE sistema_rh;

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

-- Usuario administrador por defecto (password: admin123)
INSERT INTO users (username, email, password_hash, role_id, activo) VALUES
('admin', 'admin@sistema-rh.com', '$2y$12$I4kdB2YXO5ANaaUvmGyIguhsRqi/d1NclR4Z7s28e86cxG3cO.lJO', 1, 1);

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
