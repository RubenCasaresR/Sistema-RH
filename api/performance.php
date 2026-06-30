<?php

require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/session.php';
requireAuth();
requirePermission('performance.read');

$action = $_GET['action'] ?? '';

try {
    $db = getDB();
    switch ($action) {
        case 'list_evaluations':
            $employeeId = (int)($_GET['employee_id'] ?? 0);
            $pagina = max(1, (int)($_GET['p'] ?? 1));
            $porPagina = 50;
            $offset = ($pagina - 1) * $porPagina;
            $params = [];
            $where = 'WHERE 1=1';
            if ($employeeId > 0) { $where = 'WHERE pe.employee_id = :eid'; $params[':eid'] = $employeeId; }
            $stmtC = $db->prepare("SELECT COUNT(*) FROM performance_evaluations pe $where");
            $stmtC->execute($params);
            $total = (int)$stmtC->fetchColumn();
            $stmt = $db->prepare("SELECT pe.*, e.nombre, e.apellido_paterno, u.username AS evaluador_nombre FROM performance_evaluations pe INNER JOIN employees e ON e.id = pe.employee_id INNER JOIN users u ON u.id = pe.evaluador $where ORDER BY pe.created_at DESC LIMIT $porPagina OFFSET $offset");
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $total, 'pagina' => $pagina]);
            break;
        case 'list_courses':
            $stmt = $db->query("SELECT * FROM training_courses WHERE activo = 1 ORDER BY nombre");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;
        case 'training_history':
            $employeeId = (int)($_GET['employee_id'] ?? 0);
            $pagina = max(1, (int)($_GET['p'] ?? 1));
            $porPagina = 50;
            $offset = ($pagina - 1) * $porPagina;
            $params = [];
            $where = 'WHERE 1=1';
            if ($employeeId > 0) { $where = 'WHERE th.employee_id = :eid'; $params[':eid'] = $employeeId; }
            $stmtC = $db->prepare("SELECT COUNT(*) FROM training_history th $where");
            $stmtC->execute($params);
            $total = (int)$stmtC->fetchColumn();
            $stmt = $db->prepare("SELECT th.*, e.nombre, e.apellido_paterno, tc.nombre AS curso_nombre, tc.tipo AS curso_tipo FROM training_history th INNER JOIN employees e ON e.id = th.employee_id INNER JOIN training_courses tc ON tc.id = th.course_id $where ORDER BY th.created_at DESC LIMIT $porPagina OFFSET $offset");
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $total, 'pagina' => $pagina]);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
    }
} catch (PDOException $e) {
    error_log('API Performance error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
