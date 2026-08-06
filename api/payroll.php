<?php
/**
 * API de Nómina.
 *   GET /api/payroll.php?action=periods
 *   GET /api/payroll.php?action=items&period_id=N
 */

require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . (defined('APP_URL') ? APP_URL : '*'));
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/session.php';
requireAuth();
requirePermission('payroll.read');

$action = $_GET['action'] ?? '';

try {
    $db = getDB();
    switch ($action) {
        case 'periods':
            $stmt = $db->query("SELECT pp.*, (SELECT COUNT(*) FROM payroll_items pi WHERE pi.period_id = pp.id) AS total_empleados, (SELECT SUM(pi.sueldo_neto) FROM payroll_items pi WHERE pi.period_id = pp.id) AS total_neto FROM payroll_periods pp ORDER BY pp.periodo DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;
        case 'items':
            $periodId = (int)($_GET['period_id'] ?? 0);
            if ($periodId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'period_id requerido.']);
                break;
            }

            $scope = resolveEmployeeScope($db);
            if ($scope['type'] === 'none') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
                break;
            }

            $sql = "SELECT pi.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.puesto
                    FROM payroll_items pi
                    INNER JOIN employees e ON e.id = pi.employee_id
                    WHERE pi.period_id = :pid";
            $params = [':pid' => $periodId];

            if ($scope['type'] === 'dept') {
                $sql .= " AND e.departamento = :scope_depto";
                $params[':scope_depto'] = $scope['id'];
            } elseif ($scope['type'] === 'own') {
                $sql .= " AND pi.employee_id = :scope_eid";
                $params[':scope_eid'] = $scope['id'];
            }

            $sql .= " ORDER BY e.apellido_paterno";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
    }
} catch (PDOException $e) {
    error_log('API Payroll error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}

