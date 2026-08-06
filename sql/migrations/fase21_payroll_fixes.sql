-- ============================================================
-- FASE 21: Correcciones de cálculo de nómina
--  - Guarda el salario del período (proporcional a días laborables)
--  - Guarda los días laborables del empleado (lun-vie, según ingreso)
--  - Guarda el subsidio al empleo compensable (LISR)
-- NOTA: Requiere fase5 (tipo_periodo) aplicada. No es idempotente:
--       aplicarla dos veces lanzará "Duplicate column name".
-- ============================================================

USE sistema_rh;

ALTER TABLE payroll_items
    ADD COLUMN salario_periodo DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER salario_diario,
    ADD COLUMN dias_laborables INT UNSIGNED NOT NULL DEFAULT 0 AFTER dias_trabajados,
    ADD COLUMN subsidio_compensable DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subsidio_empleo;
