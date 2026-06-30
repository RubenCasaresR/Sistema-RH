<?php
require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('users.create');
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$roles = array_keys(getRolePermissions());
$errors = [];
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'Empleado';
        $employeeId = (int)($_POST['employee_id'] ?? 0);

        if ($username === '') $errors[] = 'El nombre de usuario es obligatorio.';
        if (strlen($password) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        if (!in_array($role, $roles, true)) $errors[] = 'Rol no válido.';

        $check = $db->prepare("SELECT id FROM users WHERE username = :u");
        $check->execute([':u' => $username]);
        if ($check->fetch()) $errors[] = 'El nombre de usuario ya existe.';

        if ($employeeId > 0) {
            $chk = $db->prepare("SELECT id FROM employees WHERE id = :id AND user_id IS NULL");
            $chk->execute([':id' => $employeeId]);
            if (!$chk->fetch()) $errors[] = 'El empleado seleccionado ya tiene un usuario vinculado.';
        }

        if (count($errors) === 0) {
            $stmt = $db->prepare("INSERT INTO users (username, password, role, activo) VALUES (:u, :p, :r, 1)");
            $stmt->execute([':u' => $username, ':p' => hashPassword($password), ':r' => $role]);
            $newId = (int)$db->lastInsertId();

            if ($employeeId > 0) {
                $db->prepare("UPDATE employees SET user_id = :uid WHERE id = :eid")->execute([':uid' => $newId, ':eid' => $employeeId]);
            }

            logAudit('create', 'user', $newId, json_encode(['username' => $username, 'role' => $role]));
            setFlash('success', 'Usuario creado correctamente.');
            redirect(APP_URL . '/modules/users/index.php');
        }
    }
}

$emps = $db->query("SELECT id, nombre, apellido_paterno, apellido_materno FROM employees WHERE activo = 1 AND user_id IS NULL ORDER BY apellido_paterno")->fetchAll();
$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <h2>Nuevo usuario</h2>
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
            <input type="text" id="username" name="username" value="<?= h($old['username'] ?? '') ?>" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Contraseña * (mín. 8 caracteres)</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
            <label for="role">Rol *</label>
            <select id="role" name="role" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= h($r) ?>" <?= ($old['role'] ?? 'Empleado') === $r ? 'selected' : '' ?>><?= h($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="employee_id">Vincular a empleado (opcional)</label>
            <select id="employee_id" name="employee_id">
                <option value="">— Sin vínculo —</option>
                <?php foreach ($emps as $e): ?>
                    <option value="<?= (int)$e['id'] ?>" <?= ($old['employee_id'] ?? 0) === (int)$e['id'] ? 'selected' : '' ?>>
                        <?= h($e['apellido_paterno'] . ' ' . ($e['apellido_materno'] ?? '') . ', ' . $e['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Crear usuario</button>
            <a href="<?= APP_URL ?>/modules/users/index.php" class="btn btn-link">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
