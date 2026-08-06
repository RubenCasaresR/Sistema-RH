<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('employees.delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Método no permitido.');
    redirect(APP_URL . '/modules/employees/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlash('danger', 'Empleado no válido.');
    redirect(APP_URL . '/modules/employees/index.php');
}

$db = getDB();

$token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($token)) {
    setFlash('danger', 'Token de seguridad inválido.');
    redirect(APP_URL . '/modules/employees/index.php');
}

try {
    $stmtE = $db->prepare("SELECT nombre, apellido_paterno FROM employees WHERE id = :id");
    $stmtE->execute([':id' => $id]);
    $emp = $stmtE->fetch();

    $stmt = $db->prepare('UPDATE employees SET activo = 1 WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        logAudit('reactivate', 'employee', $id, json_encode([
            'nombre' => ($emp['nombre'] ?? '') . ' ' . ($emp['apellido_paterno'] ?? ''),
        ]));
        setFlash('success', 'Empleado reactivado correctamente.');
    } else {
        setFlash('danger', 'Empleado no encontrado.');
    }
} catch (PDOException $e) {
    error_log('Error al reactivar empleado: ' . $e->getMessage());
    setFlash('danger', 'Error al reactivar el empleado.');
}

redirect(APP_URL . '/modules/employees/index.php');
