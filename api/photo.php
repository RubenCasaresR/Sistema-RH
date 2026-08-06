<?php

require_once __DIR__ . '/../includes/session.php';
requireAuth();
requirePermission('employees.read');

header('Content-Type: application/json; charset=utf-8');

$employeeId = (int)($_GET['employee_id'] ?? 0);

if ($employeeId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida.']);
    exit;
}

try {
    $db = getDB();
} catch (PDOException $e) {
    error_log('Error en photo.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT foto_url, departamento FROM employees WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $employeeId]);
    $emp = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error en photo.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
    exit;
}

if (!$emp || !$emp['foto_url']) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Foto no encontrada.']);
    exit;
}

// Control de alcance
$scope = resolveEmployeeScope($db);
if (
    ($scope['type'] === 'own' && $employeeId !== $scope['id']) ||
    ($scope['type'] === 'dept' && $emp['departamento'] !== $scope['id']) ||
    $scope['type'] === 'none'
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes acceso a esta foto.']);
    exit;
}

$filePath = __DIR__ . '/../' . $emp['foto_url'];

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Archivo no encontrado en el servidor.']);
    exit;
}

$baseDir = realpath(__DIR__ . '/../uploads/profiles') ?: '';
$resolved = realpath($filePath);
if ($resolved === false || $baseDir === '' || strpos($resolved, $baseDir . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Archivo no permitido.']);
    exit;
}
$filePath = $resolved;

$mimeTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];
$ext = strtolower(pathinfo($emp['foto_url'], PATHINFO_EXTENSION));
$mime = $mimeTypes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-store, no-cache');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
