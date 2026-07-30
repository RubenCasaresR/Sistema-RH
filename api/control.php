<?php
/**
 * API de Tablero de Control RH (endpoints JSON).
 *   GET  /api/control.php?action=tablero            -> ?anio=N
 *   POST /api/control.php?action=tablero_update     -> { tarea_id, anio, mes, estatus, notas }
 *   GET  /api/control.php?action=indicadores        -> ?anio=N&mes=N
 *   POST /api/control.php?action=calcular           -> { anio, mes }
 *   GET  /api/control.php?action=incidencias_list   -> ?page=&per_page=&search=&tipo=&resultado=
 *   GET  /api/control.php?action=incidencia_get     -> ?id=N
 *   POST /api/control.php?action=incidencias_create -> { fecha, personas, area, tipo, descripcion, ... }
 *   POST /api/control.php?action=incidencias_update -> { id, ... }
 *   POST /api/control.php?action=incidencias_delete -> { id }
 *   GET  /api/control.php?action=checklist           -> ?anio=N&mes=N
 *   POST /api/control.php?action=checklist_update    -> { id, estatus, notas, fecha_completado }
 *   POST /api/control.php?action=checklist_bulk      -> { items: [{ id, estatus, notas }] }
 */

require_once __DIR__ . "/../config/app.php";
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: " . (defined("APP_URL") ? APP_URL : "*"));
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../includes/session.php";
requireAuth();

$action = $_GET["action"] ?? "";

try {
    $db = getDB();

    switch ($action) {
        case "tablero":             handleTablero($db); break;
        case "tablero_update":      handleTableroUpdate($db); break;
        case "indicadores":         handleIndicadores($db); break;
        case "calcular":            handleCalcular($db); break;
        case "incidencias_list":    handleIncidenciasList($db); break;
        case "incidencia_get":      handleIncidenciaGet($db); break;
        case "incidencias_create":  handleIncidenciasCreate($db); break;
        case "incidencias_update":  handleIncidenciasUpdate($db); break;
        case "incidencias_delete":  handleIncidenciasDelete($db); break;
        case "checklist":           handleChecklist($db); break;
        case "checklist_update":    handleChecklistUpdate($db); break;
        case "checklist_bulk":      handleChecklistBulk($db); break;
        default:
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Acción no reconocida."]);
    }
} catch (PDOException $e) {
    error_log("API Control error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error interno."]);
}

/* ============================================================
   TABLERO ANUAL
   ============================================================ */

function handleTablero(PDO $db): void
{
    requirePermission("control.tablero");

    $anio = (int)($_GET["anio"] ?? (int)date("Y"));
    if ($anio < 2000 || $anio > 2100) $anio = (int)date("Y");

    $tareas = $db->query("SELECT id, categoria, nombre, orden FROM control_tareas WHERE activo = 1 ORDER BY FIELD(categoria, 'semanal','mensual','bimestral','semestral','permanente'), orden")->fetchAll();

    $stmt = $db->prepare("SELECT tarea_id, mes, estatus, notas FROM control_avance WHERE anio = :anio");
    $stmt->execute([":anio" => $anio]);
    $avances = [];
    foreach ($stmt->fetchAll() as $row) {
        $avances[$row["tarea_id"]][$row["mes"]] = [
            "estatus" => $row["estatus"],
            "notas"   => $row["notas"],
        ];
    }

    $result = [];
    foreach ($tareas as $t) {
        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $meses[$m] = $avances[$t["id"]][$m] ?? ["estatus" => "pendiente", "notas" => null];
        }
        $result[] = [
            "id"        => (int)$t["id"],
            "categoria" => $t["categoria"],
            "nombre"    => $t["nombre"],
            "orden"     => (int)$t["orden"],
            "meses"     => $meses,
        ];
    }

    echo json_encode(["success" => true, "data" => $result, "anio" => $anio]);
}

function handleTableroUpdate(PDO $db): void
{
    requirePermission("control.tablero");

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        return;
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $csrfToken = $input["csrf_token"] ?? "";
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Token de seguridad inválido."]);
        return;
    }

    $tareaId = (int)($input["tarea_id"] ?? 0);
    $anio    = (int)($input["anio"] ?? 0);
    $mes     = (int)($input["mes"] ?? 0);
    $estatus = $input["estatus"] ?? "";
    $notas   = $input["notas"] ?? null;

    $validEstatus = ["pendiente", "en_proceso", "completado", "no_realizado", "na"];
    if ($tareaId <= 0 || $anio < 2000 || $mes < 1 || $mes > 12 || !in_array($estatus, $validEstatus, true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Datos inválidos."]);
        return;
    }

    $stmt = $db->prepare("
        INSERT INTO control_avance (tarea_id, anio, mes, estatus, notas, completado_por)
        VALUES (:tid, :anio, :mes, :estatus, :notas, :uid)
        ON DUPLICATE KEY UPDATE estatus = VALUES(estatus), notas = VALUES(notas), completado_por = VALUES(completado_por)
    ");
    $stmt->execute([
        ":tid"    => $tareaId,
        ":anio"   => $anio,
        ":mes"    => $mes,
        ":estatus"=> $estatus,
        ":notas"  => $notas,
        ":uid"    => $_SESSION["user_id"],
    ]);

    logAudit("update", "control_avance", $tareaId, json_encode(["anio" => $anio, "mes" => $mes, "estatus" => $estatus]));
    echo json_encode(["success" => true, "message" => "Avance actualizado."]);
}

/* ============================================================
   INDICADORES MENSUALES
   ============================================================ */

function handleIndicadores(PDO $db): void
{
    requirePermission("control.indicadores");

    $anio = (int)($_GET["anio"] ?? (int)date("Y"));
    $mes  = (int)($_GET["mes"] ?? (int)date("m"));
    if ($mes < 1 || $mes > 12) $mes = (int)date("m");

    $stmt = $db->prepare("SELECT categoria, indicador, valor, calculado_auto FROM control_indicadores WHERE anio = :anio AND mes = :mes ORDER BY categoria, indicador");
    $stmt->execute([":anio" => $anio, ":mes" => $mes]);
    $indicadores = $stmt->fetchAll();

    echo json_encode(["success" => true, "data" => $indicadores, "anio" => $anio, "mes" => $mes]);
}

function handleCalcular(PDO $db): void
{
    requirePermission("control.calcular");

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        return;
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $csrfToken = $input["csrf_token"] ?? "";
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Token de seguridad inválido."]);
        return;
    }

    $anio = (int)($input["anio"] ?? (int)date("Y"));
    $mes  = (int)($input["mes"] ?? (int)date("m"));
    if ($mes < 1 || $mes > 12) $mes = (int)date("m");

    $inicio = sprintf("%04d-%02d-01", $anio, $mes);
    $fin = date("Y-m-t", strtotime($inicio));

    $indicadores = [];

    // === ASISTENCIA ===
    $lateThreshold = defined("LATE_THRESHOLD") ? LATE_THRESHOLD : "09:05";

    $row = $db->prepare("SELECT COUNT(*) FROM attendance_logs WHERE fecha BETWEEN :inicio AND :fin AND tipo = 'regular' AND hora_entrada IS NOT NULL AND CAST(hora_entrada AS TIME) > CAST(:umbral AS TIME)");
    $row->execute([":inicio" => $inicio, ":fin" => $fin, ":umbral" => $lateThreshold]);
    $indicadores[] = ["asistencia", "Total de retardos en el mes", (float)$row->fetchColumn()];

    $row->execute([":inicio" => $inicio, ":fin" => $fin, ":umbral" => "00:00:00"]);
    $row = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE fecha_inicio <= :fin AND fecha_fin >= :inicio AND tipo = 'permiso_sin_goce' AND estatus = 'aprobado'");
    $row->execute([":inicio" => $inicio, ":fin" => $fin]);
    $faltasJustificadas = (float)$row->fetchColumn();

    $row2 = $db->prepare("SELECT COUNT(*) FROM attendance_logs WHERE fecha BETWEEN :inicio AND :fin AND tipo = 'regular' AND hora_entrada IS NULL");
    $row2->execute([":inicio" => $inicio, ":fin" => $fin]);
    $faltasInjustificadas = max(0, (float)$row2->fetchColumn() - $faltasJustificadas);
    $indicadores[] = ["asistencia", "Total de faltas injustificadas", $faltasInjustificadas];

    $indicadores[] = ["asistencia", "Total de faltas justificadas", $faltasJustificadas];

    $row = $db->prepare("SELECT COALESCE(SUM(dias_solicitados), 0) FROM leave_requests WHERE fecha_inicio <= :fin AND fecha_fin >= :inicio AND tipo = 'incapacidad' AND estatus = 'aprobado'");
    $row->execute([":inicio" => $inicio, ":fin" => $fin]);
    $indicadores[] = ["asistencia", "Días de incapacidad (IMSS)", (float)$row->fetchColumn()];

    $row = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE fecha_inicio <= :fin AND fecha_fin >= :inicio AND tipo IN ('permiso_con_goce','permiso_sin_goce') AND estatus = 'aprobado'");
    $row->execute([":inicio" => $inicio, ":fin" => $fin]);
    $indicadores[] = ["asistencia", "Permisos otorgados", (float)$row->fetchColumn()];

    // === PERSONAL ===
    $activosFin = $db->query("SELECT COUNT(*) FROM employees WHERE activo = 1")->fetchColumn();
    $indicadores[] = ["personal", "Número de colaboradores activos", (float)$activosFin];

    $row = $db->prepare("SELECT COUNT(*) FROM employees WHERE activo = 1 AND fecha_ingreso BETWEEN :inicio AND :fin");
    $row->execute([":inicio" => $inicio, ":fin" => $fin]);
    $altas = (float)$row->fetchColumn();
    $indicadores[] = ["personal", "Altas del mes", $altas];

    $row = $db->prepare("SELECT COUNT(*) FROM employees WHERE activo = 0 AND updated_at BETWEEN :inicio AND :fin");
    $row->execute([":inicio" => $inicio . " 00:00:00", ":fin" => $fin . " 23:59:59"]);
    $bajas = (float)$row->fetchColumn();
    $indicadores[] = ["personal", "Bajas del mes", $bajas];

    $activosInicio = $db->query("SELECT COUNT(*) FROM employees WHERE activo = 1 AND (fecha_ingreso < '$inicio' OR fecha_ingreso IS NULL)")->fetchColumn();
    $rotacion = $activosInicio > 0 ? round($bajas / $activosInicio * 100, 2) : 0;
    $indicadores[] = ["personal", "Rotación del mes (%)", $rotacion];

    // === CLIMA Y CONFLICTOS ===
    $row = $db->prepare("SELECT COUNT(*) FROM control_incidencias WHERE fecha BETWEEN :inicio AND :fin");
    $row->execute([":inicio" => $inicio, ":fin" => $fin]);
    $indicadores[] = ["clima", "Incidencias o conflictos registrados", (float)$row->fetchColumn()];

    $row = $db->prepare("SELECT COUNT(*) FROM control_incidencias WHERE fecha BETWEEN :inicio AND :fin AND resultado = 'resuelto'");
    $row->execute([":inicio" => $inicio, ":fin" => $fin]);
    $indicadores[] = ["clima", "Conflictos resueltos", (float)$row->fetchColumn()];

    $row = $db->prepare("SELECT COUNT(*) FROM control_incidencias WHERE fecha <= :fin AND resultado = 'en_seguimiento'");
    $row->execute([":fin" => $fin]);
    $indicadores[] = ["clima", "Conflictos en seguimiento", (float)$row->fetchColumn()];

    $indicadores[] = ["clima", "Sugerencias recibidas (buzón)", 0.0]; // Manual

    // === EXPEDIENTES ===
    $indicadores[] = ["expedientes", "Expedientes completos", 0.0]; // Manual
    $indicadores[] = ["expedientes", "Expedientes con documentos pendientes", 0.0]; // Manual
    $indicadores[] = ["expedientes", "Documentos vencidos detectados", 0.0]; // Manual

    // === RECLUTAMIENTO ===
    $row = $db->prepare("SELECT COUNT(*) FROM vacancies WHERE estatus = 'abierta'");
    $row->execute();
    $indicadores[] = ["reclutamiento", "Vacantes activas", (float)$row->fetchColumn()];

    $row = $db->prepare("SELECT COUNT(*) FROM candidate_interviews ci INNER JOIN candidates c ON c.id = ci.candidate_id WHERE ci.fecha_hora BETWEEN :inicio AND :fin");
    $row->execute([":inicio" => $inicio . " 00:00:00", ":fin" => $fin . " 23:59:59"]);
    $indicadores[] = ["reclutamiento", "Candidatos entrevistados", (float)$row->fetchColumn()];

    $row = $db->prepare("SELECT COUNT(*) FROM candidates WHERE estatus = 'contratado' AND updated_at BETWEEN :inicio AND :fin");
    $row->execute([":inicio" => $inicio . " 00:00:00", ":fin" => $fin . " 23:59:59"]);
    $indicadores[] = ["reclutamiento", "Contrataciones realizadas", (float)$row->fetchColumn()];

    // === UPSERT ===
    $stmtUp = $db->prepare("
        INSERT INTO control_indicadores (categoria, indicador, anio, mes, valor, calculado_auto)
        VALUES (:cat, :ind, :anio, :mes, :val, 1)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor), calculado_auto = 1
    ");

    $db->beginTransaction();
    try {
        foreach ($indicadores as [$cat, $ind, $val]) {
            $stmtUp->execute([":cat" => $cat, ":ind" => $ind, ":anio" => $anio, ":mes" => $mes, ":val" => $val]);
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    logAudit("calcular_indicadores", "control_indicadores", null, json_encode(["anio" => $anio, "mes" => $mes, "total" => count($indicadores)]));
    echo json_encode(["success" => true, "message" => "Indicadores calculados: " . count($indicadores) . " registros actualizados."]);
}

/* ============================================================
   BITÁCORA DE INCIDENCIAS
   ============================================================ */

function handleIncidenciasList(PDO $db): void
{
    requirePermission("control.incidencias.read");

    $page    = max(1, (int)($_GET["page"] ?? 1));
    $perPage = max(10, min(100, (int)($_GET["per_page"] ?? 20)));
    $offset  = ($page - 1) * $perPage;
    $search  = trim($_GET["search"] ?? "");
    $tipo    = $_GET["tipo"] ?? "";
    $resultado = $_GET["resultado"] ?? "";

    $where = "1=1";
    $params = [];
    if ($search !== "") {
        $where .= " AND (i.personas_involucradas LIKE :search OR i.descripcion LIKE :search OR i.area LIKE :search)";
        $params[":search"] = "%$search%";
    }
    if ($tipo !== "") {
        $where .= " AND i.tipo_incidencia = :tipo";
        $params[":tipo"] = $tipo;
    }
    if ($resultado !== "") {
        $where .= " AND i.resultado = :resultado";
        $params[":resultado"] = $resultado;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM control_incidencias i WHERE $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT i.*, u.username AS registrado_por_nombre
        FROM control_incidencias i
        LEFT JOIN users u ON u.id = i.registrado_por
        WHERE $where
        ORDER BY i.folio DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "data" => $stmt->fetchAll(),
        "page" => $page,
        "per_page" => $perPage,
        "total" => $total,
        "total_pages" => (int)ceil($total / $perPage),
    ]);
}

function handleIncidenciaGet(PDO $db): void
{
    requirePermission("control.incidencias.read");

    $id = (int)($_GET["id"] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID requerido."]);
        return;
    }

    $stmt = $db->prepare("SELECT i.*, u.username AS registrado_por_nombre FROM control_incidencias i LEFT JOIN users u ON u.id = i.registrado_por WHERE i.id = :id LIMIT 1");
    $stmt->execute([":id" => $id]);
    $incidencia = $stmt->fetch();

    if (!$incidencia) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Incidencia no encontrada."]);
        return;
    }

    echo json_encode(["success" => true, "data" => $incidencia]);
}

function handleIncidenciasCreate(PDO $db): void
{
    requirePermission("control.incidencias.create");

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        return;
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $csrfToken = $input["csrf_token"] ?? "";
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Token de seguridad inválido."]);
        return;
    }

    $fecha     = $input["fecha"] ?? "";
    $personas  = trim($input["personas"] ?? "");
    $area      = trim($input["area"] ?? "");
    $tipo      = $input["tipo"] ?? "";
    $desc      = trim($input["descripcion"] ?? "");
    $atencion  = trim($input["atencion"] ?? "");
    $resultado = $input["resultado"] ?? "en_seguimiento";
    $fechaSeg  = $input["fecha_seguimiento"] ?? null;

    $validTipos = ["conflicto_interpersonal", "queja", "falta_disciplinaria", "incumplimiento_politica", "otro"];
    $validResultados = ["resuelto", "en_seguimiento", "escalado_direccion", "sin_resolucion"];

    if ($fecha === "" || $personas === "" || $area === "" || !in_array($tipo, $validTipos, true) || $desc === "") {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Campos obligatorios: fecha, personas, área, tipo, descripción."]);
        return;
    }
    if (!in_array($resultado, $validResultados, true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Resultado inválido."]);
        return;
    }

    // Generar folio secuencial
    $maxFolio = $db->query("SELECT COALESCE(MAX(folio), 0) FROM control_incidencias")->fetchColumn();
    $folio = (int)$maxFolio + 1;

    $stmt = $db->prepare("
        INSERT INTO control_incidencias (folio, fecha, personas_involucradas, area, tipo_incidencia, descripcion, atencion, resultado, fecha_seguimiento, registrado_por)
        VALUES (:folio, :fecha, :personas, :area, :tipo, :desc, :atencion, :resultado, :fechaSeg, :uid)
    ");
    $stmt->execute([
        ":folio"    => $folio,
        ":fecha"    => $fecha,
        ":personas" => $personas,
        ":area"     => $area,
        ":tipo"     => $tipo,
        ":desc"     => $desc,
        ":atencion" => $atencion !== "" ? $atencion : null,
        ":resultado"=> $resultado,
        ":fechaSeg" => $fechaSeg !== "" ? $fechaSeg : null,
        ":uid"      => $_SESSION["user_id"],
    ]);

    $newId = (int)$db->lastInsertId();
    logAudit("create", "control_incidencia", $newId, json_encode(["folio" => $folio, "tipo" => $tipo]));
    echo json_encode(["success" => true, "message" => "Incidencia registrada con folio #$folio.", "id" => $newId, "folio" => $folio]);
}

function handleIncidenciasUpdate(PDO $db): void
{
    requirePermission("control.incidencias.update");

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        return;
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $csrfToken = $input["csrf_token"] ?? "";
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Token de seguridad inválido."]);
        return;
    }

    $id = (int)($input["id"] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID requerido."]);
        return;
    }

    $stmt = $db->prepare("SELECT id FROM control_incidencias WHERE id = :id LIMIT 1");
    $stmt->execute([":id" => $id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Incidencia no encontrada."]);
        return;
    }

    $fields = [];
    $params = [":id" => $id];
    $allowed = ["fecha", "personas_involucradas", "area", "tipo_incidencia", "descripcion", "atencion", "resultado", "fecha_seguimiento"];
    $map = ["personas" => "personas_involucradas", "tipo" => "tipo_incidencia", "descripcion" => "descripcion", "atencion" => "atencion", "resultado" => "resultado", "fecha_seguimiento" => "fecha_seguimiento"];

    foreach ($map as $key => $col) {
        if (isset($input[$key])) {
            $fields[] = "$col = :$key";
            $params[":$key"] = $input[$key] !== "" ? $input[$key] : null;
        }
    }
    if (isset($input["fecha"])) {
        $fields[] = "fecha = :fecha";
        $params[":fecha"] = $input["fecha"];
    }

    if (empty($fields)) {
        echo json_encode(["success" => true, "message" => "Sin cambios."]);
        return;
    }

    $sql = "UPDATE control_incidencias SET " . implode(", ", $fields) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    logAudit("update", "control_incidencia", $id, json_encode($input));
    echo json_encode(["success" => true, "message" => "Incidencia actualizada."]);
}

function handleIncidenciasDelete(PDO $db): void
{
    requirePermission("control.incidencias.delete");

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        return;
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $csrfToken = $input["csrf_token"] ?? "";
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Token de seguridad inválido."]);
        return;
    }

    $id = (int)($input["id"] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID requerido."]);
        return;
    }

    $stmt = $db->prepare("SELECT id, folio FROM control_incidencias WHERE id = :id LIMIT 1");
    $stmt->execute([":id" => $id]);
    $inc = $stmt->fetch();
    if (!$inc) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Incidencia no encontrada."]);
        return;
    }

    $stmt = $db->prepare("DELETE FROM control_incidencias WHERE id = :id");
    $stmt->execute([":id" => $id]);

    logAudit("delete", "control_incidencia", $id, json_encode(["folio" => $inc["folio"]]));
    echo json_encode(["success" => true, "message" => "Incidencia #" . $inc["folio"] . " eliminada."]);
}

/* ============================================================
   CHECKLIST MENSUAL
   ============================================================ */

function handleChecklist(PDO $db): void
{
    requirePermission("control.checklist");

    $anio = (int)($_GET["anio"] ?? (int)date("Y"));
    $mes  = (int)($_GET["mes"] ?? (int)date("m"));
    if ($mes < 1 || $mes > 12) $mes = (int)date("m");

    $stmt = $db->prepare("SELECT * FROM control_checklist WHERE anio = :anio AND mes = :mes ORDER BY FIELD(frecuencia, 'semanal','mensual','bimestral','semestral','permanente'), semana, id");
    $stmt->execute([":anio" => $anio, ":mes" => $mes]);
    $items = $stmt->fetchAll();

    echo json_encode(["success" => true, "data" => $items, "anio" => $anio, "mes" => $mes]);
}

function handleChecklistUpdate(PDO $db): void
{
    requirePermission("control.checklist");

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        return;
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $csrfToken = $input["csrf_token"] ?? "";
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Token de seguridad inválido."]);
        return;
    }

    $id = (int)($input["id"] ?? 0);
    $estatus = $input["estatus"] ?? "";
    $notas = $input["notas"] ?? null;
    $fecha = $input["fecha_completado"] ?? null;

    $validEstatus = ["completado", "en_proceso", "no_realizado", "na"];
    if ($id <= 0 || !in_array($estatus, $validEstatus, true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Datos inválidos."]);
        return;
    }

    $stmt = $db->prepare("UPDATE control_checklist SET estatus = :estatus, notas = :notas, fecha_completado = :fecha, completado_por = :uid WHERE id = :id");
    $stmt->execute([
        ":estatus" => $estatus,
        ":notas"   => $notas,
        ":fecha"   => $fecha !== "" ? $fecha : null,
        ":uid"     => $_SESSION["user_id"],
        ":id"      => $id,
    ]);

    echo json_encode(["success" => true, "message" => "Checklist actualizado."]);
}

function handleChecklistBulk(PDO $db): void
{
    requirePermission("control.checklist");

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        return;
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $csrfToken = $input["csrf_token"] ?? "";
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Token de seguridad inválido."]);
        return;
    }

    $items = $input["items"] ?? [];
    if (empty($items)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "No hay ítems para actualizar."]);
        return;
    }

    $validEstatus = ["completado", "en_proceso", "no_realizado", "na"];
    $stmt = $db->prepare("UPDATE control_checklist SET estatus = :estatus, notas = :notas, fecha_completado = :fecha, completado_por = :uid WHERE id = :id");

    $db->beginTransaction();
    try {
        foreach ($items as $item) {
            $id = (int)($item["id"] ?? 0);
            $estatus = $item["estatus"] ?? "";
            if ($id <= 0 || !in_array($estatus, $validEstatus, true)) continue;
            $stmt->execute([
                ":estatus" => $estatus,
                ":notas"   => $item["notas"] ?? null,
                ":fecha"   => ($item["fecha_completado"] ?? "") !== "" ? $item["fecha_completado"] : null,
                ":uid"     => $_SESSION["user_id"],
                ":id"      => $id,
            ]);
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    echo json_encode(["success" => true, "message" => count($items) . " ítems actualizados."]);
}
