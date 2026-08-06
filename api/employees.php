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

    $scope = resolveEmployeeScope($db);
    $scopeWhere = '';
    $scopeParams = [];
    if ($scope['type'] === 'own') {
        $scopeWhere = ' AND id = :scope_eid';
        $scopeParams[':scope_eid'] = $scope['id'];
    } elseif ($scope['type'] === 'dept') {
        $scopeWhere = ' AND departamento = :scope_depto';
        $scopeParams[':scope_depto'] = $scope['id'];
    } elseif ($scope['type'] === 'none') {
        $scopeWhere = ' AND 0';
    }

    $search = trim($_GET['search'] ?? '');
    $baseSql = '
            SELECT id, nombre, apellido_paterno, apellido_materno, curp, rfc, puesto,
                   departamento, fecha_ingreso, activo
            FROM employees
            WHERE activo = 1 ';

    if ($search !== '') {
        $sql = $baseSql . ' AND (nombre LIKE :q OR apellido_paterno LIKE :q OR curp LIKE :q)' . $scopeWhere . ' ORDER BY apellido_paterno, nombre';
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([':q' => '%' . $search . '%'], $scopeParams));
    } else {
        $sql = $baseSql . $scopeWhere . ' ORDER BY apellido_paterno, nombre';
        $stmt = $db->prepare($sql);
        $stmt->execute($scopeParams);
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

    $scope = resolveEmployeeScope($db);
    $scopeWhere = '';
    $params = [':id' => $id];
    if ($scope['type'] === 'own') {
        $scopeWhere = ' AND id = :scope_eid';
        $params[':scope_eid'] = $scope['id'];
    } elseif ($scope['type'] === 'dept') {
        $scopeWhere = ' AND departamento = :scope_depto';
        $params[':scope_depto'] = $scope['id'];
    } elseif ($scope['type'] === 'none') {
        $scopeWhere = ' AND 0';
    }

    $stmt = $db->prepare('
        SELECT id, nombre, apellido_paterno, apellido_materno,
               curp, rfc, fecha_nacimiento, genero,
               email, telefono, calle, numero_exterior, numero_interior,
               colonia, codigo_postal, ciudad, estado, pais,
               puesto, departamento, fecha_ingreso, tipo_contrato,
               foto_url, notas, activo, created_at, updated_at
        FROM employees WHERE id = :id ' . $scopeWhere . ' LIMIT 1
    ');
    $stmt->execute($params);
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

    $scope = resolveEmployeeScope($db);
    if ($scope['type'] === 'own') {
        $where .= ' AND id = :scope_eid';
        $params[':scope_eid'] = $scope['id'];
    } elseif ($scope['type'] === 'dept') {
        $where .= ' AND departamento = :scope_depto';
        $params[':scope_depto'] = $scope['id'];
    } elseif ($scope['type'] === 'none') {
        $where .= ' AND 0';
    }

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

    $csvCell = function (mixed $value): string {
        $value = trim((string)$value);
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"])) {
            return "'" . $value;
        }
        return $value;
    };

    fputcsv($output, [
        'ID','Nombre','Apellido Paterno','Apellido Materno','CURP','RFC','NSS',
        'Fecha Nacimiento','Género','Email','Teléfono',
        'Calle','No. Ext','No. Int','Colonia','CP','Ciudad','Estado',
        'Puesto','Departamento','Fecha Ingreso','Salario Base','Tipo Contrato','Activo'
    ]);

    foreach ($employees as $e) {
        fputcsv($output, [
            $csvCell($e['id']),
            $csvCell($e['nombre']),
            $csvCell($e['apellido_paterno']),
            $csvCell($e['apellido_materno']),
            $csvCell($e['curp']),
            $csvCell($e['rfc']),
            $csvCell($e['nss']),
            $csvCell($e['fecha_nacimiento']),
            $csvCell($e['genero']),
            $csvCell($e['email']),
            $csvCell($e['telefono']),
            $csvCell($e['calle']),
            $csvCell($e['numero_exterior']),
            $csvCell($e['numero_interior']),
            $csvCell($e['colonia']),
            $csvCell($e['codigo_postal']),
            $csvCell($e['ciudad']),
            $csvCell($e['estado']),
            $csvCell($e['puesto']),
            $csvCell($e['departamento']),
            $csvCell($e['fecha_ingreso']),
            $csvCell($e['salario_base']),
            $csvCell($e['tipo_contrato']),
            $csvCell($e['activo'] ? 'Si' : 'No'),
        ]);
    }

    fclose($output);
    exit;
}
