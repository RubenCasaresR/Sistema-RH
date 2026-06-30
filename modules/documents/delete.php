<?php
/**
 * Eliminación de un documento (archivo + registro BD).
 */

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('documents.delete');

$id = (int)($_POST['id'] ?? 0);
$token = $_POST['csrf_token'] ?? '';

if ($id <= 0 || !verifyCSRFToken($token)) {
    setFlash('danger', 'Solicitud inválida.');
    redirect(APP_URL . '/modules/documents/index.php');
}

$db = getDB();

$stmt = $db->prepare("SELECT * FROM employee_documents WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    setFlash('danger', 'Documento no encontrado.');
    redirect(APP_URL . '/modules/documents/index.php');
}

// Registrar auditoría antes de eliminar
logAudit('delete', 'document', $id, json_encode([
    'employee' => $doc['employee_id'],
    'file'     => $doc['nombre_original'],
    'tipo'     => $doc['tipo_documento'],
]));

// Eliminar archivo físico
$filePath = __DIR__ . '/../../' . $doc['archivo_ruta'];
if (file_exists($filePath)) {
    @unlink($filePath);
}

// Eliminar registro
$stmtDel = $db->prepare("DELETE FROM employee_documents WHERE id = :id");
$stmtDel->execute([':id' => $id]);

setFlash('success', 'Documento eliminado correctamente.');
redirect(APP_URL . '/modules/documents/index.php');
