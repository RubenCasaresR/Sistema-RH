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
