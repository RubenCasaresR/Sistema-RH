-- ============================================================
-- FASE 11: Mejoras al módulo de Reclutamiento
-- Índices para candidates y vacancies
-- ============================================================

USE sistema_rh;

ALTER TABLE candidates
    ADD INDEX idx_candidates_estatus (estatus),
    ADD INDEX idx_candidates_email (email);

ALTER TABLE vacancies
    ADD INDEX idx_vacancies_estatus (estatus);
