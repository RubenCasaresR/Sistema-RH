-- ============================================================
-- FASE 22: Tarifas fiscales 2026 (ISR, UMA, Subsidio al empleo)
--
-- Fuentes oficiales:
--   - ISR mensual/quincenal: Anexo 8 de la RMF 2026 (DOF 28/12/2025),
--     Art. 96 LISR (tarifa mensual y tarifa para periodos de 15 dias).
--   - UMA: INEGI (DOF 09/01/2026); vigente a partir del 01/02/2026.
--     Diaria $117.31 · Mensual $3,566.22.
--   - Subsidio para el empleo: Decreto DOF 31/12/2025. Es una cuota fija
--     = UMA mensual x 15.02% = $535.65, para ingresos de hasta $11,492.66
--     (ya NO aplica la tabla de rangos del Decreto 26/12/2013).
--     Enero 2026 usó un porcentaje transitorio del 15.59% ($536.21).
--     Quincenal: ($535.65 / 30.4) x 15 = $264.30, limite $5,670.72.
-- ============================================================

-- -----------------------------------------------------------
-- Tarifa ISR mensual 2026 (Art. 96 LISR)
-- -----------------------------------------------------------
INSERT INTO tax_isr_tariff (ejercicio, tipo, limite_inferior, limite_superior, cuota_fija, porcentaje_excedente) VALUES
(2026, 'mensual',       0.01,    844.59,       0.00,   1.92),
(2026, 'mensual',     844.60,   7168.51,      16.22,   6.40),
(2026, 'mensual',    7168.52,  12598.02,     420.95,  10.88),
(2026, 'mensual',   12598.03,  14644.64,    1011.68,  16.00),
(2026, 'mensual',   14644.65,  17533.64,    1339.14,  17.92),
(2026, 'mensual',   17533.65,  35362.83,    1856.84,  21.36),
(2026, 'mensual',   35362.84,  55736.68,    5665.16,  23.52),
(2026, 'mensual',   55736.69, 106410.50,   10457.09,  30.00),
(2026, 'mensual',  106410.51, 141880.66,   25659.23,  32.00),
(2026, 'mensual',  141880.67, 425641.99,   37009.69,  34.00),
(2026, 'mensual',  425642.00, 999999.99,  133488.54,  35.00);

-- -----------------------------------------------------------
-- Tarifa ISR quincenal 2026 (periodo de 15 dias)
-- -----------------------------------------------------------
INSERT INTO tax_isr_tariff (ejercicio, tipo, limite_inferior, limite_superior, cuota_fija, porcentaje_excedente) VALUES
(2026, 'quincenal',       0.01,    416.70,       0.00,   1.92),
(2026, 'quincenal',     416.71,   3537.15,       7.95,   6.40),
(2026, 'quincenal',    3537.16,   6216.15,     207.75,  10.88),
(2026, 'quincenal',    6216.16,   7225.95,     499.20,  16.00),
(2026, 'quincenal',    7225.96,   8651.40,     660.75,  17.92),
(2026, 'quincenal',    8651.41,  17448.75,     916.20,  21.36),
(2026, 'quincenal',   17448.76,  27501.60,    2795.25,  23.52),
(2026, 'quincenal',   27501.61,  52505.25,    5159.70,  30.00),
(2026, 'quincenal',   52505.26,  70006.95,   12660.75,  32.00),
(2026, 'quincenal',   70006.96, 210020.70,   18261.30,  34.00),
(2026, 'quincenal',  210020.71, 999999.99,   65866.05,  35.00);

-- -----------------------------------------------------------
-- UMA 2026 (vigente desde el 01/02/2026)
-- -----------------------------------------------------------
INSERT INTO tax_uma (ejercicio, valor_diario, valor_mensual) VALUES (2026, 117.31, 3566.22);

-- -----------------------------------------------------------
-- Subsidio para el empleo 2026 (cuota fija)
-- Mensual: UMA mensual x 15.02% = $535.65 · limite $11,492.66
-- Quincenal: ($535.65 / 30.4) x 15 = $264.30 · limite $5,670.72
-- -----------------------------------------------------------
INSERT INTO tax_subsidio_tariff (ejercicio, tipo, limite_inferior, limite_superior, subsidio) VALUES
(2026, 'mensual',     0.01,  11492.66,   535.65),
(2026, 'mensual', 11492.67, 999999.99,     0.00);

INSERT INTO tax_subsidio_tariff (ejercicio, tipo, limite_inferior, limite_superior, subsidio) VALUES
(2026, 'quincenal',     0.01,   5670.72,   264.30),
(2026, 'quincenal',  5670.73, 999999.99,     0.00);
