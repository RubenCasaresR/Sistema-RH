<?php
require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('users.read');
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$params = [];
$where = 'WHERE 1=1';

if ($search !== '') {
    $where .= ' AND (u.username LIKE :search OR e.nombre LIKE :search2 OR e.apellido_paterno LIKE :search3)';
    $params[':search'] = "%$search%";
    $params[':search2'] = "%$search%";
    $params[':search3'] = "%$search%";
}
if ($roleFilter !== '') {
    $where .= ' AND u.role = :role';
    $params[':role'] = $roleFilter;
}

$stmtCount = $db->prepare("SELECT COUNT(*) FROM users u LEFT JOIN employees e ON e.user_id = u.id $where");
$stmtCount->execute($params);
$totalUsers = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalUsers / $perPage));

$stmt = $db->prepare("
    SELECT u.*, e.nombre, e.apellido_paterno, e.apellido_materno
    FROM users u
    LEFT JOIN employees e ON e.user_id = u.id
    $where
    ORDER BY u.username ASC
    LIMIT :lim OFFSET :off
");
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$users = $stmt->fetchAll();

$roles = array_keys(getRolePermissions());
$csrfToken = $_SESSION['csrf_token'] ?? generateCSRFToken();
?>

<div class="page-header">
    <h2>Usuarios del sistema</h2>
    <div class="page-header-actions">
        <?php if (can('users.create')): ?>
            <a href="<?= APP_URL ?>/modules/users/create.php" class="btn btn-primary">+ Nuevo usuario</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <form method="GET" action="" class="search-form">
        <div class="search-form-row">
            <div class="form-group">
                <label for="search">Buscar</label>
                <input type="search" id="search" name="search" placeholder="Usuario o empleado..." value="<?= h($search) ?>">
            </div>
            <div class="form-group">
                <label for="role">Rol</label>
                <select id="role" name="role">
                    <option value="">Todos</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= h($r) ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= h($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end;">
                <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                <a href="?" class="btn btn-sm btn-link">Limpiar</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Empleado vinculado</th>
                    <th>Rol</th>
                    <th>Activo</th>
                    <th>Cambio forzado</th>
                    <th>Último acceso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($users) === 0): ?>
                    <tr><td colspan="7" class="empty-state">No hay usuarios registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong><?= h($u['username']) ?></strong></td>
                            <td><?= h(($u['nombre'] ?? '') . ' ' . ($u['apellido_paterno'] ?? '')) ?: '—' ?></td>
                            <td><span class="badge badge-info"><?= h($u['role']) ?></span></td>
                            <td><?= $u['activo'] ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-secondary">Inactivo</span>' ?></td>
                            <td><?= $u['password_change_required'] ? '<span class="badge badge-warning">Sí</span>' : '<span class="badge badge-secondary">No</span>' ?></td>
                            <td style="font-size:0.85rem;"><?= $u['last_login'] ?? 'Nunca' ?></td>
                            <td class="actions-cell">
                                <?php if (can('users.update')): ?>
                                    <a href="<?= APP_URL ?>/modules/users/edit.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-ghost">Editar</a>
                                <?php endif; ?>
                                <?php if (can('users.delete') && (int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="POST" action="<?= APP_URL ?>/modules/users/delete.php" style="display:inline" onsubmit="return confirm('¿Eliminar al usuario <?= h(addslashes($u['username'])) ?>?')">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <button type="submit" class="btn btn-sm btn-ghost">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="display:flex;justify-content:center;gap:4px;padding:16px 0;">
        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-sm btn-ghost">&laquo; Anterior</a>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-ghost' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-sm btn-ghost">Siguiente &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
