-- ============================================================
-- FASE 20: Tablero de Control RH
-- Tablas: control_tareas, control_avance, control_indicadores,
--         control_incidencias, control_checklist
-- ============================================================

-- 1. Catálogo de tareas del tablero
CREATE TABLE IF NOT EXISTS control_tareas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria ENUM('semanal','mensual','bimestral','semestral','permanente') NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    orden INT UNSIGNED DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Avance mensual de cada tarea (tablero anual)
CREATE TABLE IF NOT EXISTS control_avance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id INT UNSIGNED NOT NULL,
    anio YEAR NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    estatus ENUM('pendiente','en_proceso','completado','no_realizado','na') NOT NULL DEFAULT 'pendiente',
    notas TEXT DEFAULT NULL,
    completado_por INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tarea_periodo (tarea_id, anio, mes),
    CONSTRAINT fk_avance_tarea FOREIGN KEY (tarea_id) REFERENCES control_tareas(id) ON DELETE CASCADE,
    CONSTRAINT fk_avance_user FOREIGN KEY (completado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Indicadores mensuales calculados
CREATE TABLE IF NOT EXISTS control_indicadores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria VARCHAR(50) NOT NULL,
    indicador VARCHAR(150) NOT NULL,
    anio YEAR NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL DEFAULT 0,
    calculado_auto TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_indicador_periodo (indicador, anio, mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Bitácora de incidencias
CREATE TABLE IF NOT EXISTS control_incidencias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folio INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    personas_involucradas VARCHAR(500) NOT NULL,
    area VARCHAR(100) NOT NULL,
    tipo_incidencia ENUM('conflicto_interpersonal','queja','falta_disciplinaria','incumplimiento_politica','otro') NOT NULL,
    descripcion TEXT NOT NULL,
    atencion TEXT DEFAULT NULL,
    resultado ENUM('resuelto','en_seguimiento','escalado_direccion','sin_resolucion') NOT NULL DEFAULT 'en_seguimiento',
    fecha_seguimiento DATE DEFAULT NULL,
    registrado_por INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_folio (folio),
    CONSTRAINT fk_incidencia_user FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Checklist mensual
CREATE TABLE IF NOT EXISTS control_checklist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id INT UNSIGNED DEFAULT NULL,
    descripcion_tarea VARCHAR(200) NOT NULL,
    frecuencia VARCHAR(30) NOT NULL,
    semana VARCHAR(20) DEFAULT NULL,
    anio YEAR NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    estatus ENUM('completado','en_proceso','no_realizado','na') NOT NULL DEFAULT 'na',
    fecha_completado DATE DEFAULT NULL,
    notas TEXT DEFAULT NULL,
    completado_por INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_checklist_tarea FOREIGN KEY (tarea_id) REFERENCES control_tareas(id) ON DELETE SET NULL,
    CONSTRAINT fk_checklist_user FOREIGN KEY (completado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PERMISOS
-- ============================================================
INSERT IGNORE INTO permissions (clave, nombre) VALUES
    ('control.tablero', 'Ver tablero anual'),
    ('control.indicadores', 'Ver indicadores mensuales'),
    ('control.calcular', 'Recalcular indicadores'),
    ('control.incidencias.read', 'Ver bitácora de incidencias'),
    ('control.incidencias.create', 'Crear incidencia'),
    ('control.incidencias.update', 'Editar incidencia'),
    ('control.incidencias.delete', 'Eliminar incidencia'),
    ('control.checklist', 'Ver y editar checklist mensual');

-- ============================================================
-- SEMILLAS: Catálogo de tareas (22 tareas del Excel)
-- ============================================================
INSERT IGNORE INTO control_tareas (id, categoria, nombre, orden) VALUES
-- SEMANAL (3)
(1,  'semanal',   'Revisión de indicadores (faltas, retardos, incidencias)', 1),
(2,  'semanal',   'Observación del ambiente y clima', 2),
(3,  'semanal',   'Cierre de semana y actualización de bitácora', 3),
-- MENSUAL (7)
(4,  'mensual',   'Consolidado de indicadores del mes anterior', 1),
(5,  'mensual',   'Reporte de indicadores a supervisor / dirección', 2),
(6,  'mensual',   'Revisión del buzón de sugerencias', 3),
(7,  'mensual',   'Seguimiento de acuerdos y compromisos previos', 4),
(8,  'mensual',   'Auditoría de expedientes del personal', 5),
(9,  'mensual',   'Reconocimiento del mes (cumpleaños, logros)', 6),
(10, 'mensual',   'Revisión de actualizaciones al manual de procesos', 7),
-- BIMESTRAL (2)
(11, 'bimestral', 'Entrevistas individuales de seguimiento', 1),
(12, 'bimestral', 'Revisión de diagnóstico de necesidades por área', 2),
-- SEMESTRAL (4)
(13, 'semestral', 'Encuesta de clima laboral (Litwin y Stringer)', 1),
(14, 'semestral', 'Análisis de resultados y propuesta de mejoras', 2),
(15, 'semestral', 'Revisión general del manual de procesos', 3),
(16, 'semestral', 'Evaluación del estado de expedientes completos', 4),
-- PERMANENTE (6)
(17, 'permanente','Registro de incidencias y conflictos', 1),
(18, 'permanente','Integración de expedientes (altas)', 2),
(19, 'permanente','Cierre de expedientes (bajas)', 3),
(20, 'permanente','Atención a conflictos interpersonales', 4),
(21, 'permanente','Comunicados internos al personal', 5),
(22, 'permanente','Apoyo en procesos de reclutamiento activos', 6);

-- ============================================================
-- SEMILLAS: Checklist del mes actual (junio 2026)
-- ============================================================
INSERT IGNORE INTO control_checklist (descripcion_tarea, frecuencia, semana, anio, mes, estatus, fecha_completado) VALUES
-- SEMANAL (4 semanas x 3 tareas)
('Revisión de indicadores (faltas, retardos, incidencias)', 'semanal', 'Sem. 1', 2026, 6, 'completado', '2026-05-12'),
('Observación del ambiente y clima del despacho', 'semanal', 'Sem. 1', 2026, 6, 'completado', '2026-05-12'),
('Cierre de semana y actualización de bitácora', 'semanal', 'Sem. 1', 2026, 6, 'completado', '2026-05-12'),
('Revisión de indicadores (faltas, retardos, incidencias)', 'semanal', 'Sem. 2', 2026, 6, 'completado', '2026-05-12'),
('Observación del ambiente y clima del despacho', 'semanal', 'Sem. 2', 2026, 6, 'completado', '2026-05-12'),
('Cierre de semana y actualización de bitácora', 'semanal', 'Sem. 2', 2026, 6, 'completado', '2026-05-12'),
('Revisión de indicadores (faltas, retardos, incidencias)', 'semanal', 'Sem. 3', 2026, 6, 'completado', '2026-05-22'),
('Observación del ambiente y clima del despacho', 'semanal', 'Sem. 3', 2026, 6, 'completado', '2026-05-22'),
('Cierre de semana y actualización de bitácora', 'semanal', 'Sem. 3', 2026, 6, 'completado', '2026-05-22'),
('Revisión de indicadores (faltas, retardos, incidencias)', 'semanal', 'Sem. 4', 2026, 6, 'completado', '2026-05-29'),
('Observación del ambiente y clima del despacho', 'semanal', 'Sem. 4', 2026, 6, 'completado', '2026-05-29'),
('Cierre de semana y actualización de bitácora', 'semanal', 'Sem. 4', 2026, 6, 'completado', '2026-05-29'),
-- MENSUAL
('Consolidado de indicadores del mes anterior', 'mensual', NULL, 2026, 6, 'na', NULL),
('Reporte de indicadores a supervisor / dirección', 'mensual', NULL, 2026, 6, 'en_proceso', NULL),
('Revisión del buzón de sugerencias anónimo', 'mensual', NULL, 2026, 6, 'completado', NULL),
('Seguimiento de acuerdos y compromisos previos', 'mensual', NULL, 2026, 6, 'completado', NULL),
('Auditoría de expedientes del personal', 'mensual', NULL, 2026, 6, 'completado', NULL),
('Acción de reconocimiento del mes', 'mensual', NULL, 2026, 6, 'completado', NULL),
('Revisión de actualizaciones necesarias al manual', 'mensual', NULL, 2026, 6, 'en_proceso', NULL),
-- BIMESTRAL / SEMESTRAL
('Entrevistas individuales de seguimiento', 'bimestral', NULL, 2026, 6, 'completado', '2026-05-12'),
('Diagnóstico de necesidades por área', 'bimestral', NULL, 2026, 6, 'completado', '2026-05-12'),
('Encuesta de clima laboral', 'semestral', NULL, 2026, 6, 'completado', '2026-05-12'),
('Análisis de resultados y propuesta de mejoras', 'semestral', NULL, 2026, 6, 'completado', '2026-05-12'),
('Revisión general del manual de procesos', 'semestral', NULL, 2026, 6, 'completado', '2026-05-15'),
-- PERMANENTE
('Registro de incidencias y conflictos (cuando ocurran)', 'permanente', NULL, 2026, 6, 'na', NULL),
('Integración de expedientes de nuevas altas', 'permanente', NULL, 2026, 6, 'na', NULL),
('Cierre de expedientes de bajas', 'permanente', NULL, 2026, 6, 'na', NULL),
('Atención a conflictos interpersonales', 'permanente', NULL, 2026, 6, 'na', NULL),
('Comunicados internos según necesidad', 'permanente', NULL, 2026, 6, 'completado', NULL);

-- ============================================================
-- SEMILLA: Incidencia existente del Excel
-- ============================================================
INSERT IGNORE INTO control_incidencias (folio, fecha, personas_involucradas, area, tipo_incidencia, descripcion, atencion, resultado, fecha_seguimiento, registrado_por)
SELECT 1, '2026-05-09', 'BRENDA LUJAN; MICHELLE YANOME', 'TESORERIA', 'queja',
    'Ambas reportaron en la evaluación de clima laboral la presencia de tratos poco amigables y pasivo-agresivos, por parte de un miembro de otra área. Se cree que el motivo es debido a su reciente ingreso, pero no está claro.',
    'Al tomar conocimiento de la situación, se le preguntó a las partes afectadas sobre la persistencia de los hechos descritos, refiriendo el cese de los mismos desde días atrás.',
    'en_seguimiento', '2026-05-29',
    (SELECT id FROM users WHERE username = 'jdelgadillo' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM control_incidencias WHERE folio = 1);
