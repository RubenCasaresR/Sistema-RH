-- ============================================================
-- FASE 7: Mejora de cálculos de nómina
-- ISR, IMSS, Aguinaldo, Prima Vacacional, Subsidio al empleo
-- ============================================================

USE sistema_rh;

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
