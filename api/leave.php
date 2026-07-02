<?php

require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . (defined('APP_URL') ? APP_URL : '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/session.php';
requireAuth();

$action = $_GET['action'] ?? '';

try {
    $db = getDB();

    switch ($action) {
        case 'create':
            handleCreate($db);
            break;
        case 'approve':
            handleApprove($db);
            break;
        case 'list':
            handleList($db);
            break;
        case 'balance':
            handleBalance($db);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
    }
} catch (PDOException $e) {
    error_log('API Leave error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}

function handleCreate(PDO $db): void
{
    requirePermission('leave.request');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
        return;
    }

    $employeeId = (int)($input['employee_id'] ?? 0);
    $tipo = $input['tipo'] ?? '';
    $fechaInicio = $input['fecha_inicio'] ?? '';
    $fechaFin = $input['fecha_fin'] ?? '';
    $motivo = trim($input['motivo'] ?? '');

    if ($employeeId <= 0 || !in_array($tipo, ['vacaciones','permiso_con_goce','permiso_sin_goce','incapacidad']) || !$fechaInicio || !$fechaFin) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
        return;
    }

    try {
        $inicio = new DateTime($fechaInicio);
        $fin = new DateTime($fechaFin);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Fechas inválidas.']);
        return;
    }
    $dias = (int)$inicio->diff($fin)->days + 1;

    $stmt = $db->prepare("INSERT INTO leave_requests (employee_id, tipo, fecha_inicio, fecha_fin, dias_solicitados, motivo, estatus) VALUES (:eid, :tipo, :inicio, :fin, :dias, :motivo, 'pendiente')");
    $stmt->execute([':eid' => $employeeId, ':tipo' => $tipo, ':inicio' => $fechaInicio, ':fin' => $fechaFin, ':dias' => $dias, ':motivo' => $motivo]);

    echo json_encode(['success' => true, 'message' => 'Solicitud creada.', 'id' => (int)$db->lastInsertId()]);
}

function handleApprove(PDO $db): void
{
    requirePermission('leave.approve');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
        return;
    }

    $requestId = (int)($input['request_id'] ?? 0);
    $action = $input['action'] ?? '';
    $comentarios = trim($input['comentarios'] ?? '');

    if ($requestId <= 0 || !in_array($action, ['aprobar', 'rechazar'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
        return;
    }

    if ($action === 'rechazar' && $comentarios === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Debes proporcionar un motivo al rechazar.']);
        return;
    }

    // Filtro por departamento si es Jefe de área
    $userRole = $_SESSION['user']['role_name'] ?? '';
    if ($userRole === 'Jefe de área') {
        $stmtDepto = $db->prepare("SELECT departamento FROM employees WHERE user_id = :uid AND activo = 1 LIMIT 1");
        $stmtDepto->execute([':uid' => (int)$_SESSION['user_id']]);
        $miDepto = $stmtDepto->fetchColumn();
        if ($miDepto) {
            $stmtCheck = $db->prepare("SELECT lr.id FROM leave_requests lr INNER JOIN employees e ON e.id = lr.employee_id WHERE lr.id = :rid AND e.departamento = :depto LIMIT 1");
            $stmtCheck->execute([':rid' => $requestId, ':depto' => $miDepto]);
            if (!$stmtCheck->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'No puedes aprobar solicitudes de otros departamentos.']);
                return;
            }
        }
    }

    $estatus = $action === 'aprobar' ? 'aprobado' : 'rechazado';
    $userId = (int)$_SESSION['user_id'];

    if ($estatus === 'aprobado') {
        $stmtR = $db->prepare("SELECT employee_id, tipo, dias_solicitados FROM leave_requests WHERE id = :id AND estatus = 'pendiente' LIMIT 1");
        $stmtR->execute([':id' => $requestId]);
        $req = $stmtR->fetch();
        if (!$req) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada o ya procesada.']);
            return;
        }
        if ($req['tipo'] === 'vacaciones') {
            $stmtEmpB = $db->prepare("SELECT fecha_ingreso FROM employees WHERE id = :id LIMIT 1");
            $stmtEmpB->execute([':id' => $req['employee_id']]);
            $empB = $stmtEmpB->fetch();
            $antB = 0;
            if ($empB && $empB['fecha_ingreso']) {
                $antB = (int)(new DateTime($empB['fecha_ingreso']))->diff(new DateTime())->y;
            }
            $diasPorLeyB = calculateLFTHolidays($antB);
            $stmtBalB = $db->prepare("SELECT COALESCE(SUM(dias_disfrutados), 0) FROM leave_balance WHERE employee_id = :eid AND periodo = YEAR(CURDATE())");
            $stmtBalB->execute([':eid' => $req['employee_id']]);
            $disfB = (float)$stmtBalB->fetchColumn();
            $dispB = $diasPorLeyB - $disfB;
            if ($req['dias_solicitados'] > $dispB) {
                echo json_encode(['success' => false, 'message' => 'Saldo insuficiente. Disponibles: ' . max(0, $dispB) . ', solicitados: ' . $req['dias_solicitados'] . '.']);
                return;
            }
        }
    } else {
        $req = null;
    }

    $stmt = $db->prepare("UPDATE leave_requests SET estatus = :estatus, aprobado_por = :uid, fecha_aprobacion = NOW(), comentarios_aprobador = :comentarios WHERE id = :id AND estatus = 'pendiente'");
    $stmt->execute([':estatus' => $estatus, ':uid' => $userId, ':comentarios' => $comentarios, ':id' => $requestId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada o ya procesada.']);
        return;
    }

    if ($estatus === 'aprobado' && $req && $req['tipo'] === 'vacaciones') {
        $stmtB = $db->prepare("INSERT INTO leave_balance (employee_id, periodo, dias_totales, dias_disfrutados) VALUES (:eid, YEAR(CURDATE()), 0, :dias) ON DUPLICATE KEY UPDATE dias_disfrutados = dias_disfrutados + :dias2");
        $stmtB->execute([':eid' => $req['employee_id'], ':dias' => $req['dias_solicitados'], ':dias2' => $req['dias_solicitados']]);
    }

    echo json_encode(['success' => true, 'message' => 'Solicitud ' . ($action === 'aprobar' ? 'aprobada' : 'rechazada') . '.']);
}

function handleList(PDO $db): void
{
    requirePermission('leave.read');

    $pagina = max(1, (int)($_GET['p'] ?? 1));
    $porPagina = 50;
    $offset = ($pagina - 1) * $porPagina;
    $tipo = $_GET['tipo'] ?? '';
    $estatus = $_GET['estatus'] ?? '';

    $params = [];
    $where = 'WHERE 1=1';

    if (in_array($tipo, ['vacaciones', 'permiso_con_goce', 'permiso_sin_goce', 'incapacidad'])) {
        $where .= ' AND lr.tipo = :tipo';
        $params[':tipo'] = $tipo;
    }
    if (in_array($estatus, ['pendiente', 'aprobado', 'rechazado', 'cancelado'])) {
        $where .= ' AND lr.estatus = :estatus';
        $params[':estatus'] = $estatus;
    }

    $stmtCount = $db->prepare("SELECT COUNT(*) FROM leave_requests lr $where");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $stmt = $db->prepare("
        SELECT lr.*, e.nombre, e.apellido_paterno, e.apellido_materno, u.username AS aprobador
        FROM leave_requests lr
        INNER JOIN employees e ON e.id = lr.employee_id
        LEFT JOIN users u ON u.id = lr.aprobado_por
        $where
        ORDER BY lr.created_at DESC
        LIMIT $porPagina OFFSET $offset
    ");
    $stmt->execute($params);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $total, 'pagina' => $pagina]);
}

function handleBalance(PDO $db): void
{
    requirePermission('leave.read');

    $employeeId = (int)($_GET['employee_id'] ?? 0);
    if ($employeeId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'employee_id requerido.']);
        return;
    }

    $stmt = $db->prepare("SELECT * FROM leave_balance WHERE employee_id = :eid AND periodo = YEAR(CURDATE()) LIMIT 1");
    $stmt->execute([':eid' => $employeeId]);
    $balance = $stmt->fetch();

    $stmtEmp = $db->prepare("SELECT fecha_ingreso FROM employees WHERE id = :id LIMIT 1");
    $stmtEmp->execute([':id' => $employeeId]);
    $emp = $stmtEmp->fetch();

    $diasPorLey = 0;
    $antiguedad = 0;
    if ($emp && $emp['fecha_ingreso']) {
        $antiguedad = (int)(new DateTime($emp['fecha_ingreso']))->diff(new DateTime())->y;
        $diasPorLey = calculateLFTHolidays($antiguedad);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'balance' => $balance ?: null,
            'antiguedad_anios' => $antiguedad,
            'dias_por_ley' => $diasPorLey,
            'dias_totales' => $balance ? (float)$balance['dias_totales'] : $diasPorLey,
            'dias_disfrutados' => $balance ? (float)$balance['dias_disfrutados'] : 0,
            'dias_disponibles' => $balance ? ($diasPorLey - (float)$balance['dias_disfrutados']) : $diasPorLey,
        ]
    ]);
}
