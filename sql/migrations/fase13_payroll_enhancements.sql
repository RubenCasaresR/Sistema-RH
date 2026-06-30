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
