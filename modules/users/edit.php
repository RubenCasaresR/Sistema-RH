<?php
require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('users.update');
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('danger', 'Usuario no válido.');
    redirect(APP_URL . '/modules/users/index.php');
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();
if (!$user) {
    setFlash('danger', 'Usuario no encontrado.');
    redirect(APP_URL . '/modules/users/index.php');
}

$roles = array_keys(getRolePermissions());
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? $user['role'];
        $activo = isset($_POST['activo']) ? 1 : 0;
        $passwordChangeRequired = isset($_POST['password_change_required']) ? 1 : 0;

        if ($username === '') $errors[] = 'El nombre de usuario es obligatorio.';
        if (!in_array($role, $roles, true)) $errors[] = 'Rol no válido.';

        $check = $db->prepare("SELECT id FROM users WHERE username = :u AND id != :id");
        $check->execute([':u' => $username, ':id' => $id]);
        if ($check->fetch()) $errors[] = 'El nombre de usuario ya existe.';

        if (count($errors) === 0) {
            if ($password !== '') {
                if (strlen($password) < 8) {
                    $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
                } else {
                    $stmtU = $db->prepare("UPDATE users SET username = :u, password = :p, role = :r, activo = :act, password_change_required = :pcr WHERE id = :id");
                    $stmtU->execute([':u' => $username, ':p' => hashPassword($password), ':r' => $role, ':act' => $activo, ':pcr' => $passwordChangeRequired, ':id' => $id]);
                }
            } else {
                $stmtU = $db->prepare("UPDATE users SET username = :u, role = :r, activo = :act, password_change_required = :pcr WHERE id = :id");
                $stmtU->execute([':u' => $username, ':r' => $role, ':act' => $activo, ':pcr' => $passwordChangeRequired, ':id' => $id]);
            }

            if (count($errors) === 0) {
                logAudit('update', 'user', $id, json_encode(['username' => $username, 'role' => $role, 'activo' => $activo]));
                setFlash('success', 'Usuario actualizado correctamente.');
                redirect(APP_URL . '/modules/users/index.php');
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <h2>Editar usuario</h2>
    <a href="<?= APP_URL ?>/modules/users/index.php" class="btn btn-link">&larr; Volver</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e): ?><p><?= h($e) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="" class="form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="form-group">
            <label for="username">Nombre de usuario *</label>
            <input type="text" id="username" name="username" value="<?= h($_POST['username'] ?? $user['username']) ?>" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Nueva contraseña (dejar vacío para mantener)</label>
            <input type="password" id="password" name="password" placeholder="Mín. 8 caracteres">
        </div>

        <div class="form-group">
            <label for="role">Rol *</label>
            <select id="role" name="role" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= h($r) ?>" <?= ($_POST['role'] ?? $user['role']) === $r ? 'selected' : '' ?>><?= h($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="display:flex;gap:20px;flex-wrap:wrap;">
            <label class="checkbox-label">
                <input type="checkbox" name="activo" value="1" <?= ($_POST['activo'] ?? $user['activo']) ? 'checked' : '' ?>> Activo
            </label>
            <label class="checkbox-label">
                <input type="checkbox" name="password_change_required" value="1" <?= ($_POST['password_change_required'] ?? $user['password_change_required']) ? 'checked' : '' ?>> Forzar cambio de contraseña
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="<?= APP_URL ?>/modules/users/index.php" class="btn btn-link">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
