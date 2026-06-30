<?php
require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('users.delete');

$id = (int)($_POST['id'] ?? 0);
$token = $_POST['csrf_token'] ?? '';

if ($id <= 0 || !verifyCSRFToken($token)) {
    setFlash('danger', 'Solicitud inválida.');
    redirect(APP_URL . '/modules/users/index.php');
}

if ($id === (int)$_SESSION['user_id']) {
    setFlash('danger', 'No puedes eliminar tu propio usuario.');
    redirect(APP_URL . '/modules/users/index.php');
}

$db = getDB();

$stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'Usuario no encontrado.');
    redirect(APP_URL . '/modules/users/index.php');
}

$db->prepare("UPDATE employees SET user_id = NULL WHERE user_id = :id")->execute([':id' => $id]);

$stmtDel = $db->prepare("DELETE FROM users WHERE id = :id");
$stmtDel->execute([':id' => $id]);

logAudit('delete', 'user', $id, json_encode(['username' => $user['username']]));
setFlash('success', 'Usuario eliminado correctamente.');
redirect(APP_URL . '/modules/users/index.php');
