<?php

require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . APP_URL);
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
        case 'get':
            handleGet($db);
            break;
        case 'export':
            handleExport($db);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
    }
} catch (PDOException $e) {
    error_log('API Employees error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}

function handleList(PDO $db): void
{
    requirePermission('employees.read');

    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $stmt = $db->prepare('
            SELECT id, nombre, apellido_paterno, apellido_materno, curp, rfc, puesto,
                   departamento, fecha_ingreso, activo
            FROM employees
            WHERE activo = 1
              AND (nombre LIKE :q OR apellido_paterno LIKE :q OR curp LIKE :q)
            ORDER BY apellido_paterno, nombre
        ');
        $like = '%' . $search . '%';
        $stmt->execute([':q' => $like]);
    } else {
        $stmt = $db->query('
            SELECT id, nombre, apellido_paterno, apellido_materno, curp, rfc, puesto,
                   departamento, fecha_ingreso, activo
            FROM employees WHERE activo = 1
            ORDER BY apellido_paterno, nombre
        ');
    }

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function handleGet(PDO $db): void
{
    requirePermission('employees.read');

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        return;
    }

    $stmt = $db->prepare('SELECT * FROM employees WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $emp = $stmt->fetch();

    if (!$emp) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Empleado no encontrado.']);
        return;
    }

    echo json_encode(['success' => true, 'data' => $emp]);
}

function handleExport(PDO $db): void
{
    requirePermission('employees.export');

    $search = trim($_GET['search'] ?? '');
    $filtroDepto = $_GET['departamento'] ?? '';
    $filtroContrato = $_GET['tipo_contrato'] ?? '';
    $filtroEstatus = $_GET['estatus'] ?? 'activos';

    $where = 'WHERE 1=1';
    $params = [];

    if ($filtroEstatus === 'activos') {
        $where .= ' AND activo = 1';
    } elseif ($filtroEstatus === 'inactivos') {
        $where .= ' AND activo = 0';
    }

    if ($search !== '') {
        $where .= ' AND (nombre LIKE :q OR apellido_paterno LIKE :q OR curp LIKE :q OR rfc LIKE :q OR puesto LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }

    if ($filtroDepto !== '') {
        $where .= ' AND departamento = :depto';
        $params[':depto'] = $filtroDepto;
    }

    if ($filtroContrato !== '') {
        $where .= ' AND tipo_contrato = :contrato';
        $params[':contrato'] = $filtroContrato;
    }

    $stmt = $db->prepare("
        SELECT id, nombre, apellido_paterno, apellido_materno, curp, rfc, nss,
               fecha_nacimiento, genero, email, telefono,
               calle, numero_exterior, numero_interior, colonia, codigo_postal, ciudad, estado,
               puesto, departamento, fecha_ingreso, salario_base, tipo_contrato,
               activo
        FROM employees
        $where
        ORDER BY apellido_paterno, nombre
    ");
    $stmt->execute($params);
    $employees = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="empleados.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, [
        'ID','Nombre','Apellido Paterno','Apellido Materno','CURP','RFC','NSS',
        'Fecha Nacimiento','Género','Email','Teléfono',
        'Calle','No. Ext','No. Int','Colonia','CP','Ciudad','Estado',
        'Puesto','Departamento','Fecha Ingreso','Salario Base','Tipo Contrato','Activo'
    ]);

    foreach ($employees as $e) {
        fputcsv($output, [
            $e['id'],
            $e['nombre'],
            $e['apellido_paterno'],
            $e['apellido_materno'],
            $e['curp'],
            $e['rfc'],
            $e['nss'],
            $e['fecha_nacimiento'],
            $e['genero'],
            $e['email'],
            $e['telefono'],
            $e['calle'],
            $e['numero_exterior'],
            $e['numero_interior'],
            $e['colonia'],
            $e['codigo_postal'],
            $e['ciudad'],
            $e['estado'],
            $e['puesto'],
            $e['departamento'],
            $e['fecha_ingreso'],
            $e['salario_base'],
            $e['tipo_contrato'],
            $e['activo'] ? 'Si' : 'No',
        ]);
    }

    fclose($output);
    exit;
}
