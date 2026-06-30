<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('announcements.delete');

$id = (int)($_POST['id'] ?? 0);
$token = $_POST['csrf_token'] ?? '';

if ($id <= 0 || !verifyCSRFToken($token)) {
    setFlash('danger', 'Solicitud inválida.');
    redirect(APP_URL . '/modules/communication/announcements.php');
}

$db = getDB();

try {
    $stmtA = $db->prepare("SELECT titulo FROM announcements WHERE id = :id");
    $stmtA->execute([':id' => $id]);
    $ann = $stmtA->fetch();

    $stmt = $db->prepare("UPDATE announcements SET activo = 0 WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        logAudit('delete', 'announcement', $id, json_encode(['titulo' => $ann['titulo'] ?? '']));
        setFlash('success', 'Comunicado eliminado.');
    } else {
        setFlash('danger', 'Comunicado no encontrado.');
    }
} catch (PDOException $e) {
    error_log('Error al eliminar anuncio: ' . $e->getMessage());
    setFlash('danger', 'Error al eliminar.');
}

redirect(APP_URL . '/modules/communication/announcements.php');
