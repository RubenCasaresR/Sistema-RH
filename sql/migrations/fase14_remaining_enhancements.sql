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
