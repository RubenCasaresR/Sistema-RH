<?php
/**
 * API de Documentos (endpoints JSON).
 *   GET   /api/documents.php?action=list&employee_id=N
 *   GET   /api/documents.php?action=versions&id=N
 *   GET   /api/documents.php?action=export
 *   POST  /api/documents.php?action=upload  (multipart/form-data)
 *   POST  /api/documents.php?action=delete  { id }
 */

require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . (defined('APP_URL') ? APP_URL : '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
        case 'list':
            handleList($db);
            break;
        case 'versions':
            handleVersions($db);
            break;
        case 'export':
            handleExport($db);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
    }
} catch (PDOException $e) {
    error_log('API Documents error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}

function handleList(PDO $db): void
{
    requirePermission('documents.read');

    $employeeId = (int)($_GET['employee_id'] ?? 0);
    $search = trim($_GET['search'] ?? '');
    $tipo = trim($_GET['tipo'] ?? '');
    $fechaFrom = trim($_GET['fecha_from'] ?? '');
    $fechaTo = trim($_GET['fecha_to'] ?? '');

    $params = [];
    $where = 'WHERE 1=1';

    $scope = resolveEmployeeScope($db);
    if ($scope['type'] === 'own') {
        $where .= ' AND e.id = :scope_eid';
        $params[':scope_eid'] = $scope['id'];
    } elseif ($scope['type'] === 'dept') {
        $where .= ' AND e.departamento = :scope_depto';
        $params[':scope_depto'] = $scope['id'];
    } elseif ($scope['type'] === 'none') {
        $where .= ' AND 1=0';
    }

    if ($employeeId > 0) {
        $where .= ' AND d.employee_id = :emp_id';
        $params[':emp_id'] = $employeeId;
    }

    if ($search !== '') {
        $where .= ' AND (d.nombre_original LIKE :search OR d.tipo_documento LIKE :search2)';
        $params[':search'] = "%$search%";
        $params[':search2'] = "%$search%";
    }

    if ($tipo !== '') {
        $where .= ' AND d.tipo_documento = :tipo';
        $params[':tipo'] = $tipo;
    }

    if ($fechaFrom !== '') {
        $where .= ' AND d.created_at >= :fecha_from';
        $params[':fecha_from'] = $fechaFrom . ' 00:00:00';
    }

    if ($fechaTo !== '') {
        $where .= ' AND d.created_at <= :fecha_to';
        $params[':fecha_to'] = $fechaTo . ' 23:59:59';
    }

    $stmt = $db->prepare("
        SELECT d.id, d.employee_id, d.tipo_documento, d.nombre_original, d.mime_type,
               d.peso_bytes, d.hash_firma, d.fecha_firma, d.notas, d.created_at,
               e.nombre, e.apellido_paterno, e.apellido_materno,
               (SELECT COUNT(*) FROM document_versions WHERE document_id = d.id) as version_count
        FROM employee_documents d
        INNER JOIN employees e ON e.id = d.employee_id
        $where
        ORDER BY d.created_at DESC
    ");
    $stmt->execute($params);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function handleVersions(PDO $db): void
{
    requirePermission('documents.read');

    $docId = (int)($_GET['id'] ?? 0);
    if ($docId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de documento inválido.']);
        return;
    }

    $scope = resolveEmployeeScope($db);
    if ($scope['type'] !== 'all') {
        $stmtOwn = $db->prepare("SELECT ed.employee_id, e.departamento FROM employee_documents ed INNER JOIN employees e ON e.id = ed.employee_id WHERE ed.id = :id LIMIT 1");
        $stmtOwn->execute([':id' => $docId]);
        $own = $stmtOwn->fetch();
        if (!$own) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Documento no encontrado.']);
            return;
        }
        if ($scope['type'] === 'own' && (int)$own['employee_id'] !== $scope['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No puedes ver los documentos de otro empleado.']);
            return;
        }
        if ($scope['type'] === 'dept' && $own['departamento'] !== $scope['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No puedes ver los documentos de otros departamentos.']);
            return;
        }
    }

    // Get current document as version 0
    $stmt = $db->prepare("SELECT id, nombre_original, mime_type, peso_bytes, hash_firma, fecha_firma, created_at FROM employee_documents WHERE id = :id");
    $stmt->execute([':id' => $docId]);
    $current = $stmt->fetch();

    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Documento no encontrado.']);
        return;
    }

    // Get historical versions
    $stmt = $db->prepare("
        SELECT dv.*, u.username
        FROM document_versions dv
        LEFT JOIN users u ON u.id = dv.subido_por
        WHERE dv.document_id = :id
        ORDER BY dv.version_number DESC
    ");
    $stmt->execute([':id' => $docId]);
    $versions = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'current' => $current,
            'versions' => $versions,
        ],
    ]);
}

function handleExport(PDO $db): void
{
    requirePermission('documents.read');

    $employeeId = (int)($_GET['employee_id'] ?? 0);

    $params = [];
    $where = 'WHERE 1=1';

    $scope = resolveEmployeeScope($db);
    if ($scope['type'] === 'own') {
        $where .= ' AND e.id = :scope_eid';
        $params[':scope_eid'] = $scope['id'];
    } elseif ($scope['type'] === 'dept') {
        $where .= ' AND e.departamento = :scope_depto';
        $params[':scope_depto'] = $scope['id'];
    } elseif ($scope['type'] === 'none') {
        $where .= ' AND 1=0';
    }

    if ($employeeId > 0) {
        $where .= ' AND d.employee_id = :emp_id';
        $params[':emp_id'] = $employeeId;
    }

    $stmt = $db->prepare("
        SELECT e.nombre, e.apellido_paterno, e.apellido_materno,
               d.tipo_documento, d.nombre_original, d.mime_type, d.peso_bytes,
               d.hash_firma, d.fecha_firma, d.notas, d.created_at
        FROM employee_documents d
        INNER JOIN employees e ON e.id = d.employee_id
        $where
        ORDER BY d.created_at DESC
    ");
    $stmt->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="documentos_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM

    $csvCell = function (mixed $value): string {
        if (is_string($value)) {
            if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                $value = "'" . $value;
            }
        }
        return (string)$value;
    };

    fputcsv($output, ['Empleado', 'Tipo', 'Archivo', 'Tipo MIME', 'Peso (Bytes)', 'Firma Digital', 'Fecha Firma', 'Notas', 'Subido']);

    foreach ($stmt as $row) {
        fputcsv($output, [
            $csvCell($row['nombre'] . ' ' . $row['apellido_paterno'] . ' ' . ($row['apellido_materno'] ?? '')),
            $csvCell($row['tipo_documento']),
            $csvCell($row['nombre_original']),
            $csvCell($row['mime_type']),
            $csvCell($row['peso_bytes']),
            $csvCell($row['hash_firma'] ? 'Sí' : 'No'),
            $csvCell($row['fecha_firma'] ?? ''),
            $csvCell($row['notas'] ?? ''),
            $csvCell($row['created_at']),
        ]);
    }

    fclose($output);
    exit;
}
