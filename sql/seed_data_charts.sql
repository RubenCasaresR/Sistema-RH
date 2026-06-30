-- ============================================================
-- Datos de semilla para gráficos del Dashboard
-- ============================================================

USE sistema_rh;

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
