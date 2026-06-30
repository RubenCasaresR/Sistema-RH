<?php

require_once __DIR__ . '/../includes/session.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

$candidateId = (int)($_GET['candidate_id'] ?? 0);
$token = $_GET['token'] ?? '';

if ($candidateId <= 0 || !verifyCSRFToken($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida.']);
    exit;
}

requirePermission('recruitment.read');

$db = getDB();

$stmt = $db->prepare("SELECT c.cv_ruta, c.nombre, c.apellido_paterno, c.apellido_materno, v.titulo AS vacante_titulo FROM candidates c INNER JOIN vacancies v ON v.id = c.vacancy_id WHERE c.id = :id LIMIT 1");
$stmt->execute([':id' => $candidateId]);
$candidate = $stmt->fetch();

if (!$candidate || !$candidate['cv_ruta']) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'CV no encontrado.']);
    exit;
}

$filePath = __DIR__ . '/../' . $candidate['cv_ruta'];

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Archivo no encontrado en el servidor.']);
    exit;
}

$mimeTypes = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mime = $mimeTypes[$ext] ?? 'application/octet-stream';

$isPreview = isset($_GET['preview']) && $_GET['preview'] === '1';
$disposition = $isPreview ? 'inline' : 'attachment';

$candidateName = $candidate['nombre'] . ' ' . $candidate['apellido_paterno'];
$filename = 'CV_' . preg_replace('/[^a-zA-Z0-9]/', '_', $candidateName) . '.' . $ext;

logAudit('download', 'cv', $candidateId, json_encode([
    'candidato' => $candidateName,
    'vacante'   => $candidate['vacante_titulo'],
]));

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-store, no-cache');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
