ALTER TABLE payroll_items
    ADD COLUMN descuento_retardos DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER retardos;
