<?php

require_once __DIR__ . '/../includes/session.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
$token = $_GET['token'] ?? '';
$versionId = (int)($_GET['version'] ?? 0);

if ($id <= 0 || !verifyCSRFToken($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida.']);
    exit;
}

requirePermission('documents.read');

$db = getDB();

// If version specified, serve from document_versions
if ($versionId > 0) {
    $stmt = $db->prepare("
        SELECT dv.*, ed.employee_id
        FROM document_versions dv
        INNER JOIN employee_documents ed ON ed.id = dv.document_id
        WHERE dv.id = :vid AND dv.document_id = :did
        LIMIT 1
    ");
    $stmt->execute([':vid' => $versionId, ':did' => $id]);
    $doc = $stmt->fetch();

    if (!$doc) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Versión no encontrada.']);
        exit;
    }

    $filePath = __DIR__ . '/../' . $doc['archivo_ruta'];
    $originalName = $doc['nombre_original'];
    $mime = $doc['mime_type'];

    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Archivo de versión no encontrado en el servidor.']);
        exit;
    }

    $isPreview = isset($_GET['preview']) && $_GET['preview'] === '1';
    $disposition = $isPreview ? 'inline' : 'attachment';

    logAudit($isPreview ? 'preview' : 'download', 'document_version', $versionId, json_encode([
        'document_id' => $id,
        'file' => $originalName,
    ]));

    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $originalName) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, no-store, no-cache');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
    exit;
}

$stmt = $db->prepare("
    SELECT ed.*, e.nombre, e.apellido_paterno
    FROM employee_documents ed
    INNER JOIN employees e ON e.id = ed.employee_id
    WHERE ed.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Documento no encontrado.']);
    exit;
}

$filePath = __DIR__ . '/../' . $doc['archivo_ruta'];

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Archivo no encontrado en el servidor.']);
    exit;
}

$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
];
$ext = strtolower(pathinfo($doc['nombre_original'], PATHINFO_EXTENSION));
$mime = $mimeTypes[$ext] ?? 'application/octet-stream';

$isPreview = isset($_GET['preview']) && $_GET['preview'] === '1';
$disposition = $isPreview ? 'inline' : 'attachment';

logAudit($isPreview ? 'preview' : 'download', 'document', $id, json_encode([
    'employee' => $doc['nombre'] . ' ' . $doc['apellido_paterno'],
    'file'     => $doc['nombre_original'],
]));

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $doc['nombre_original']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-store, no-cache');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
