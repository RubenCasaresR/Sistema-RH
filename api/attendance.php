<?php
/**
 * API de Asistencia (endpoints JSON).
 *   POST /api/attendance.php?action=clock     -> { employee_id, action: 'entrada'|'salida' }
 *   GET  /api/attendance.php?action=status    -> ?employee_id=N  (estado de hoy)
 *   GET  /api/attendance.php?action=report    -> ?fecha_inicio=&fecha_fin=&employee_id=&page=&per_page=
 *   GET  /api/attendance.php?action=summary   -> ?fecha_inicio=&fecha_fin=&employee_id=
 *   GET  /api/attendance.php?action=calendar  -> ?mes=&anio=&employee_id=
 *   GET  /api/attendance.php?action=export    -> ?fecha_inicio=&fecha_fin=&employee_id=
 *   POST /api/attendance.php?action=correct   -> { id, campo, valor, motivo }
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
        case "clock":    handleClock($db); break;
        case "status":   handleStatus($db); break;
        case "report":   handleReport($db); break;
        case "summary":  handleSummary($db); break;
        case "calendar": handleCalendar($db); break;
        case "export":   handleExport($db); break;
        case "correct":  handleCorrect($db); break;
        case "correction_history": handleCorrectionHistory($db); break;
        default:
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Acción no reconocida."]);
    }
} catch (PDOException $e) {
    error_log("API Attendance error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error interno."]);
}

function handleClock(PDO $db): void
{
    requirePermission("attendance.clock");

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

    $empId = (int)($input["employee_id"] ?? 0);
    $action = $input["action"] ?? "";

    if ($empId <= 0 || !in_array($action, ["entrada", "salida"], true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Datos inválidos."]);
        return;
    }

    $hoy = date("Y-m-d");
    $ahora = date("Y-m-d H:i:s");
    $ip = getClientIP();

    if ($action === "entrada") {
        $stmtC = $db->prepare("SELECT id FROM attendance_logs WHERE employee_id = :eid AND fecha = :fecha AND tipo = 'regular' AND hora_entrada IS NOT NULL LIMIT 1");
        $stmtC->execute([":eid" => $empId, ":fecha" => $hoy]);
        if ($stmtC->fetch()) {
            echo json_encode(["success" => false, "message" => "Ya registraste entrada hoy."]);
            return;
        }
        $stmtU = $db->prepare("INSERT INTO attendance_logs (employee_id, fecha, hora_entrada, tipo, ip_address) VALUES (:eid, :fecha, :hora, 'regular', :ip) ON DUPLICATE KEY UPDATE hora_entrada = VALUES(hora_entrada), ip_address = VALUES(ip_address)");
        $stmtU->execute([":eid" => $empId, ":fecha" => $hoy, ":hora" => $ahora, ":ip" => $ip]);
        logAudit("clock_in", "attendance", $empId, "Entrada registrada vía API");
        echo json_encode(["success" => true, "message" => "Entrada registrada: " . date("H:i:s"), "hora" => $ahora]);
    } else {
        $stmtC = $db->prepare("SELECT id FROM attendance_logs WHERE employee_id = :eid AND fecha = :fecha AND tipo = 'regular' AND hora_entrada IS NOT NULL AND hora_salida IS NULL LIMIT 1");
        $stmtC->execute([":eid" => $empId, ":fecha" => $hoy]);
        $log = $stmtC->fetch();
        if (!$log) {
            echo json_encode(["success" => false, "message" => "No hay entrada registrada hoy."]);
            return;
        }
        $stmtU = $db->prepare("UPDATE attendance_logs SET hora_salida = :hora, ip_address = COALESCE(ip_address, :ip) WHERE id = :id");
        $stmtU->execute([":hora" => $ahora, ":ip" => $ip, ":id" => $log["id"]]);
        logAudit("clock_out", "attendance", $empId, "Salida registrada vía API");
        echo json_encode(["success" => true, "message" => "Salida registrada: " . date("H:i:s"), "hora" => $ahora]);
    }
}

function handleStatus(PDO $db): void
{
    requirePermission("attendance.read");
    $empId = (int)($_GET["employee_id"] ?? 0);
    if ($empId <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "employee_id requerido."]);
        return;
    }

    $stmt = $db->prepare("SELECT hora_entrada, hora_salida, ip_address, estatus FROM attendance_logs WHERE employee_id = :eid AND fecha = :fecha AND tipo = 'regular' LIMIT 1");
    $stmt->execute([":eid" => $empId, ":fecha" => date("Y-m-d")]);
    $status = $stmt->fetch();

    echo json_encode([
        "success" => true,
        "data" => $status ?: null
    ]);
}

function handleReport(PDO $db): void
{
    requirePermission("attendance.reports");

    $fechaInicio = $_GET["fecha_inicio"] ?? date("Y-m-01");
    $fechaFin    = $_GET["fecha_fin"] ?? date("Y-m-d");
    $employeeId  = (int)($_GET["employee_id"] ?? 0);
    $page        = max(1, (int)($_GET["page"] ?? 1));
    $perPage     = max(10, min(100, (int)($_GET["per_page"] ?? 50)));
    $offset      = ($page - 1) * $perPage;

    $params = [":inicio" => $fechaInicio, ":fin" => $fechaFin];
    $whereEmployee = "";
    if ($employeeId > 0) {
        $whereEmployee = "AND a.employee_id = :emp_id";
        $params[":emp_id"] = $employeeId;
    }

    $stmtCount = $db->prepare("SELECT COUNT(*) FROM attendance_logs a WHERE a.fecha BETWEEN :inicio AND :fin AND a.tipo = 'regular' $whereEmployee");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $stmt = $db->prepare("
        SELECT a.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.puesto
        FROM attendance_logs a
        INNER JOIN employees e ON e.id = a.employee_id
        WHERE a.fecha BETWEEN :inicio AND :fin AND a.tipo = 'regular' $whereEmployee
        ORDER BY a.fecha DESC, e.apellido_paterno
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
        "total_pages" => (int)ceil($total / $perPage)
    ]);
}

function handleSummary(PDO $db): void
{
    requirePermission("attendance.reports");

    $fechaInicio = $_GET["fecha_inicio"] ?? date("Y-m-01");
    $fechaFin    = $_GET["fecha_fin"] ?? date("Y-m-d");
    $employeeId  = (int)($_GET["employee_id"] ?? 0);

    $params = [":inicio" => $fechaInicio, ":fin" => $fechaFin];
    $whereEmployee = "";
    if ($employeeId > 0) {
        $whereEmployee = "AND a.employee_id = :emp_id";
        $params[":emp_id"] = $employeeId;
    }

    $lateThreshold = defined("LATE_THRESHOLD") ? LATE_THRESHOLD : "09:05";
    list($hL, $mL) = explode(":", $lateThreshold);

    $stmt = $db->prepare("
        SELECT
            e.id AS employee_id,
            e.nombre,
            e.apellido_paterno,
            e.apellido_materno,
            COUNT(a.id) AS total_dias,
            SUM(CASE WHEN a.hora_entrada IS NULL THEN 1 ELSE 0 END) AS faltas,
            SUM(CASE
                WHEN a.hora_entrada IS NOT NULL
                 AND (CAST(a.hora_entrada AS TIME) > CAST(:umbral AS TIME))
                THEN 1 ELSE 0 END) AS retardos,
            SUM(CASE WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL THEN 1 ELSE 0 END) AS completados,
            SUM(CASE WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NULL THEN 1 ELSE 0 END) AS sin_salida_count,
            SEC_TO_TIME(SUM(CASE WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL
                THEN TIMESTAMPDIFF(SECOND, a.hora_entrada, a.hora_salida) ELSE 0 END)) AS total_jornada
        FROM employees e
        LEFT JOIN attendance_logs a ON a.employee_id = e.id AND a.fecha BETWEEN :inicio2 AND :fin2 AND a.tipo = 'regular'
        WHERE e.activo = 1
          $whereEmployee
        GROUP BY e.id, e.nombre, e.apellido_paterno, e.apellido_materno
        ORDER BY e.apellido_paterno
    ");
    $stmt->bindValue(":umbral", "$hL:$mL:00");
    $stmt->bindValue(":inicio2", $fechaInicio);
    $stmt->bindValue(":fin2", $fechaFin);
    if ($employeeId > 0) $stmt->bindValue(":emp_id", $employeeId, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    $jornadaHoras = defined("JORNADA_HORAS") ? JORNADA_HORAS : 8;

    $result = [];
    foreach ($rows as $r) {
        $totalSegundos = 0;
        if ($r["total_jornada"]) {
            $parts = explode(":", $r["total_jornada"]);
            $totalSegundos = (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2];
        }
        $horasReales = round($totalSegundos / 3600, 2);
        $horasEsperadas = (int)$r["completados"] * $jornadaHoras;
        $horasExtra = max(0, round($horasReales - $horasEsperadas, 2));

        $diasHabiles = (int)$r["total_dias"];
        $asistencias = (int)$r["completados"] + (int)$r["sin_salida_count"];
        $faltas = (int)$r["faltas"];
        $retardos = (int)$r["retardos"];
        $sinSalida = (int)$r["sin_salida_count"];

        $result[] = [
            "employee_id" => (int)$r["employee_id"],
            "nombre_completo" => $r["nombre"] . " " . $r["apellido_paterno"] . ($r["apellido_materno"] ? " " . $r["apellido_materno"] : ""),
            "dias_habiles" => $diasHabiles,
            "asistencias" => $asistencias,
            "faltas" => $faltas,
            "retardos" => $retardos,
            "sin_salida" => $sinSalida,
            "horas_reales" => $horasReales,
            "horas_esperadas" => $horasEsperadas,
            "horas_extra" => $horasExtra,
        ];
    }

    echo json_encode(["success" => true, "data" => $result]);
}

function handleCalendar(PDO $db): void
{
    requirePermission("attendance.reports");

    $mes = (int)($_GET["mes"] ?? (int)date("m"));
    $anio = (int)($_GET["anio"] ?? (int)date("Y"));
    $employeeId = (int)($_GET["employee_id"] ?? 0);

    if ($mes < 1 || $mes > 12) $mes = (int)date("m");
    if ($anio < 2000 || $anio > 2100) $anio = (int)date("Y");
    if ($employeeId <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "employee_id requerido."]);
        return;
    }

    $inicio = sprintf("%04d-%02d-01", $anio, $mes);
    $fin = date("Y-m-t", strtotime($inicio));

    $stmt = $db->prepare("
        SELECT a.fecha, a.hora_entrada, a.hora_salida, a.estatus
        FROM attendance_logs a
        WHERE a.employee_id = :eid AND a.fecha BETWEEN :inicio AND :fin AND a.tipo = 'regular'
        ORDER BY a.fecha
    ");
    $stmt->execute([":eid" => $employeeId, ":inicio" => $inicio, ":fin" => $fin]);
    $registros = $stmt->fetchAll();

    $lateThresholdCal = defined("LATE_THRESHOLD") ? LATE_THRESHOLD : "09:05";
    $jornadaHorasCal = defined("JORNADA_HORAS") ? JORNADA_HORAS : 8;
    $dias = [];
    foreach ($registros as $r) {
        $status = computeAttendanceStatus($r["hora_entrada"], $r["hora_salida"], $lateThresholdCal, $jornadaHorasCal);
        $dias[$r["fecha"]] = [
            "hora_entrada" => $r["hora_entrada"] ? date("H:i", strtotime($r["hora_entrada"])) : null,
            "hora_salida" => $r["hora_salida"] ? date("H:i", strtotime($r["hora_salida"])) : null,
            "estado" => $r["estatus"] === "justificado" ? "Justificado" : $status["estado"],
            "class" => $r["estatus"] === "justificado" ? "present" : ($status["class"] === "success" ? "present" : ($status["class"] === "warning" ? "no-record" : "absent")),
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => [
            "mes" => $mes,
            "anio" => $anio,
            "dias" => $dias,
            "primer_dia_semana" => (int)date("N", strtotime($inicio)),
            "total_dias" => (int)date("t", strtotime($inicio)),
        ]
    ]);
}

function handleExport(PDO $db): void
{
    requirePermission("attendance.export");

    $fechaInicio = $_GET["fecha_inicio"] ?? date("Y-m-01");
    $fechaFin    = $_GET["fecha_fin"] ?? date("Y-m-d");
    $employeeId  = (int)($_GET["employee_id"] ?? 0);

    $params = [":inicio" => $fechaInicio, ":fin" => $fechaFin];
    $whereEmployee = "";
    if ($employeeId > 0) {
        $whereEmployee = "AND a.employee_id = :emp_id";
        $params[":emp_id"] = $employeeId;
    }

    $stmt = $db->prepare("
        SELECT a.fecha, e.nombre, e.apellido_paterno, e.apellido_materno, e.puesto, e.departamento,
               a.hora_entrada, a.hora_salida, a.ip_address, a.estatus, a.justificacion
        FROM attendance_logs a
        INNER JOIN employees e ON e.id = a.employee_id
        WHERE a.fecha BETWEEN :inicio AND :fin AND a.tipo = 'regular' $whereEmployee
        ORDER BY a.fecha DESC, e.apellido_paterno
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=asistencia_" . $fechaInicio . "_" . $fechaFin . ".csv");

    $output = fopen("php://output", "w");
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ["Fecha", "Nombre", "Apellidos", "Puesto", "Departamento", "Entrada", "Salida", "Jornada", "Horas", "Extra", "Estatus", "Justificación", "IP"]);

    $lateThresholdExp = defined("LATE_THRESHOLD") ? LATE_THRESHOLD : "09:05";
    $jornadaHorasExp = defined("JORNADA_HORAS") ? JORNADA_HORAS : 8;
    foreach ($rows as $r) {
        $status = computeAttendanceStatus($r["hora_entrada"], $r["hora_salida"], $lateThresholdExp, $jornadaHorasExp);
        $estadoTexto = $r["estatus"] === "justificado" ? "Justificado" : $status["estado"];
        fputcsv($output, [
            $r["fecha"],
            $r["nombre"],
            trim($r["apellido_paterno"] . " " . $r["apellido_materno"]),
            $r["puesto"],
            $r["departamento"],
            $r["hora_entrada"] ? date("H:i:s", strtotime($r["hora_entrada"])) : "",
            $r["hora_salida"] ? date("H:i:s", strtotime($r["hora_salida"])) : "",
            $status["jornada"],
            $status["horas_totales"],
            $status["horas_extra"],
            $estadoTexto,
            $r["justificacion"] ?? "",
            $r["ip_address"] ?? "",
        ]);
    }
    fclose($output);
    exit;
}

function handleCorrect(PDO $db): void
{
    requirePermission("attendance.correct");

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
    $campo = $input["campo"] ?? "";
    $valorNuevo = $input["valor"] ?? "";
    $motivo = trim($input["motivo"] ?? "");

    $columnMap = [
        "hora_entrada" => "hora_entrada",
        "hora_salida"  => "hora_salida",
        "justificacion" => "justificacion",
        "estatus"      => "estatus",
    ];

    if ($id <= 0 || !isset($columnMap[$campo]) || $motivo === "") {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Datos inválidos. Motivo requerido."]);
        return;
    }

    $stmt = $db->prepare("SELECT * FROM attendance_logs WHERE id = :id LIMIT 1");
    $stmt->execute([":id" => $id]);
    $log = $stmt->fetch();

    if (!$log) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Registro no encontrado."]);
        return;
    }

    $valorAnterior = $log[$campo] ?? "";

    if (in_array($campo, ["hora_entrada", "hora_salida"], true)) {
        if ($valorNuevo !== "" && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $valorNuevo)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Formato de hora inválido (use HH:mm:ss)."]);
            return;
        }
        if ($campo === "hora_salida" && $valorNuevo === "") {
            $db->prepare("UPDATE attendance_logs SET hora_salida = NULL, estatus = 'incidencia' WHERE id = :id")->execute([":id" => $id]);
        }
    } elseif ($campo === "estatus" && $valorNuevo !== "" && !in_array($valorNuevo, ["regular", "justificado", "incidencia"], true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Estatus inválido. Use: regular, justificado o incidencia."]);
        return;
    }

    $column = $columnMap[$campo];
    $sql = "UPDATE attendance_logs SET `$column` = :valor WHERE id = :id";
    $stmtU = $db->prepare($sql);
    $stmtU->execute([":valor" => $valorNuevo, ":id" => $id]);

    $stmtC = $db->prepare("INSERT INTO attendance_corrections (attendance_log_id, user_id, campo_modificado, valor_anterior, valor_nuevo, motivo) VALUES (:log_id, :uid, :campo, :anterior, :nuevo, :motivo)");
    $stmtC->execute([
        ":log_id" => $id,
        ":uid" => $_SESSION["user_id"],
        ":campo" => $campo,
        ":anterior" => $valorAnterior,
        ":nuevo" => $valorNuevo,
        ":motivo" => $motivo,
    ]);

    logAudit("correct_attendance", "attendance", $id, "Corregido $campo: '$valorAnterior' → '$valorNuevo'. Motivo: $motivo");
    echo json_encode(["success" => true, "message" => "Registro corregido exitosamente."]);
}

function handleCorrectionHistory(PDO $db): void
{
    requirePermission("attendance.correct");

    $logId = (int)($_GET["log_id"] ?? 0);
    if ($logId <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "log_id requerido."]);
        return;
    }

    $stmt = $db->prepare("
        SELECT ac.campo_modificado, ac.valor_anterior, ac.valor_nuevo, ac.motivo, ac.created_at AS fecha, u.username
        FROM attendance_corrections ac
        LEFT JOIN users u ON u.id = ac.user_id
        WHERE ac.attendance_log_id = :log_id
        ORDER BY ac.created_at DESC
    ");
    $stmt->execute([":log_id" => $logId]);
    $rows = $stmt->fetchAll();

    echo json_encode(["success" => true, "data" => $rows]);
}