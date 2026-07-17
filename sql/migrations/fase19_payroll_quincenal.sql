ALTER TABLE payroll_periods
    ADD COLUMN tipo_periodo ENUM('mensual', 'quincenal') NOT NULL DEFAULT 'mensual'
    AFTER periodo;
