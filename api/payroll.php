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
            $stmt = $db->prepare("SELECT pi.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.puesto FROM payroll_items pi INNER JOIN employees e ON e.id = pi.employee_id WHERE pi.period_id = :pid ORDER BY e.apellido_paterno");
            $stmt->execute([':pid' => $periodId]);
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

