<?php
/**
 * Evaluaciones de desempeño.
 * Listado, creación, edición de evaluaciones por período.
 */

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('performance.read');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$errors = [];
$puedeCrear = can('performance.create');
$puedeEditar = can('performance.update');

$employeeId = (int)($_GET['employee_id'] ?? 0);

// Crear evaluación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!$puedeCrear) { $errors[] = 'Permiso denegado.'; }

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }

    $empId = (int)($_POST['employee_id'] ?? 0);
    $periodo = trim($_POST['periodo'] ?? '');
    $calificacion = $_POST['calificacion'] !== '' ? (float)$_POST['calificacion'] : null;
    $fortalezas = trim($_POST['fortalezas'] ?? '');
    $areasMejora = trim($_POST['areas_mejora'] ?? '');
    $retroalimentacion = trim($_POST['retroalimentacion'] ?? '');

    if ($empId <= 0) $errors[] = 'Seleccione empleado.';
    if ($periodo === '') $errors[] = 'Período requerido.';
    if ($calificacion !== null && ($calificacion < 0 || $calificacion > 100)) $errors[] = 'Calificación debe estar entre 0 y 100.';
    if (strlen($periodo) > 50) $errors[] = 'Período demasiado largo.';
    if (strlen($fortalezas) > 2000) $errors[] = 'Fortalezas demasiado largo.';
    if (strlen($areasMejora) > 2000) $errors[] = 'Áreas de mejora demasiado largo.';
    if (strlen($retroalimentacion) > 5000) $errors[] = 'Retroalimentación demasiado largo.';

    if (count($errors) === 0) {
        try {
            $stmt = $db->prepare("INSERT INTO performance_evaluations (employee_id, periodo, evaluador, calificacion, fortalezas, areas_mejora, retroalimentacion, estatus) VALUES (:eid, :per, :eval, :cal, :fort, :areas, :retro, 'completada')");
            $stmt->execute([':eid' => $empId, ':per' => $periodo, ':eval' => (int)$_SESSION['user_id'], ':cal' => $calificacion, ':fort' => $fortalezas, ':areas' => $areasMejora, ':retro' => $retroalimentacion]);
            setFlash('success', 'Evaluación registrada.');
            redirect(APP_URL . '/modules/performance/index.php');
        } catch (PDOException $e) { error_log('Error evaluación: ' . $e->getMessage()); $errors[] = 'Error al guardar.'; }
    }
}

// Actualizar evaluación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!$puedeEditar) { $errors[] = 'Permiso denegado.'; }

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }

    $evalId = (int)($_POST['id'] ?? 0);
    $calificacion = $_POST['calificacion'] !== '' ? (float)$_POST['calificacion'] : null;
    $fortalezas = trim($_POST['fortalezas'] ?? '');
    $areasMejora = trim($_POST['areas_mejora'] ?? '');
    $retroalimentacion = trim($_POST['retroalimentacion'] ?? '');

    if ($evalId <= 0) $errors[] = 'Evaluación inválida.';
    if ($calificacion !== null && ($calificacion < 0 || $calificacion > 100)) $errors[] = 'Calificación debe estar entre 0 y 100.';
    if (strlen($fortalezas) > 2000) $errors[] = 'Fortalezas demasiado largo.';
    if (strlen($areasMejora) > 2000) $errors[] = 'Áreas de mejora demasiado largo.';
    if (strlen($retroalimentacion) > 5000) $errors[] = 'Retroalimentación demasiado largo.';

    if (count($errors) === 0) {
        try {
            $stmt = $db->prepare("UPDATE performance_evaluations SET calificacion = :cal, fortalezas = :fort, areas_mejora = :areas, retroalimentacion = :retro WHERE id = :id");
            $stmt->execute([':cal' => $calificacion, ':fort' => $fortalezas, ':areas' => $areasMejora, ':retro' => $retroalimentacion, ':id' => $evalId]);
            setFlash('success', 'Evaluación actualizada.');
            redirect(APP_URL . '/modules/performance/index.php');
        } catch (PDOException $e) { error_log('Error evaluación: ' . $e->getMessage()); $errors[] = 'Error al actualizar.'; }
    }
}

// Paginación
$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 20;
$offset = ($pagina - 1) * $porPagina;

// Listado de evaluaciones
$params = [];
$where = 'WHERE 1=1';
$countParams = [];
$countWhere = 'WHERE 1=1';
if ($employeeId > 0) {
    $where = 'WHERE pe.employee_id = :eid';
    $params[':eid'] = $employeeId;
    $countWhere = 'WHERE employee_id = :eid';
    $countParams[':eid'] = $employeeId;
}

$stmtC = $db->prepare("SELECT COUNT(*) FROM performance_evaluations $countWhere");
$stmtC->execute($countParams);
$total = (int)$stmtC->fetchColumn();
$totalPaginas = max(1, (int)ceil($total / $porPagina));

$stmt = $db->prepare("
    SELECT pe.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.puesto, u.username AS evaluador_nombre
    FROM performance_evaluations pe
    INNER JOIN employees e ON e.id = pe.employee_id
    INNER JOIN users u ON u.id = pe.evaluador
    $where
    ORDER BY pe.created_at DESC
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute($params);
$evaluaciones = $stmt->fetchAll();

$emps = $db->query("SELECT id, nombre, apellido_paterno, apellido_materno FROM employees WHERE activo = 1 ORDER BY apellido_paterno")->fetchAll();
$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <h2>Evaluaciones de desempeño</h2>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<!-- Filtro -->
<div class="card">
    <form method="GET" action="" class="search-form">
        <div class="form-group">
            <label for="employee_id">Filtrar por empleado</label>
            <select id="filter_employee_id" name="employee_id" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach ($emps as $e): ?>
                    <option value="<?= (int)$e['id'] ?>" <?= $employeeId === (int)$e['id'] ? 'selected' : '' ?>>
                        <?= h($e['apellido_paterno'] . ', ' . $e['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($puedeCrear): ?>
<div class="card" style="max-width:700px;">
    <h3 class="card-title">Nueva evaluación</h3>
    <form method="POST" action="" class="form" novalidate>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="form-row">
            <div class="form-group">
                <label for="employee_id">Empleado *</label>
                <select id="employee_id" name="employee_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($emps as $e): ?>
                        <option value="<?= (int)$e['id'] ?>"><?= h($e['apellido_paterno'] . ', ' . $e['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="periodo">Período *</label>
                <input type="text" id="periodo" name="periodo" placeholder="Ej: 2026-Q1" required>
            </div>
            <div class="form-group">
                <label for="calificacion">Calificación (0-100)</label>
                <input type="number" id="calificacion" name="calificacion" min="0" max="100" step="0.1">
            </div>
        </div>
        <div class="form-group"><label for="fortalezas">Fortalezas</label><textarea id="fortalezas" name="fortalezas" rows="3"></textarea></div>
        <div class="form-group"><label for="areas_mejora">Áreas de mejora</label><textarea id="areas_mejora" name="areas_mejora" rows="3"></textarea></div>
        <div class="form-group"><label for="retroalimentacion">Retroalimentación</label><textarea id="retroalimentacion" name="retroalimentacion" rows="3"></textarea></div>
        <div class="form-actions"><button type="submit" class="btn btn-primary" id="btnGuardarEval">Guardar evaluación</button></div>
    </form>
</div>
<script>
document.getElementById('btnGuardarEval')?.addEventListener('click', function() {
    this.disabled = true;
    this.textContent = 'Guardando…';
    this.form.submit();
});
</script>
<?php endif; ?>

<div class="card">
    <h3 class="card-title">Historial de evaluaciones</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Período</th>
                    <th>Evaluador</th>
                    <th>Calificación</th>
                    <th>Fecha</th>
                    <?php if ($puedeEditar): ?><th>Acción</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (count($evaluaciones) === 0): ?>
                    <tr><td colspan="<?= $puedeEditar ? 6 : 5 ?>" class="empty-state">Sin evaluaciones registradas.</td></tr>
                <?php else: ?>
                    <?php foreach ($evaluaciones as $ev): ?>
                        <tr>
                            <td><?= h($ev['nombre'] . ' ' . $ev['apellido_paterno']) ?></td>
                            <td><span class="badge badge-info"><?= h($ev['periodo']) ?></span></td>
                            <td><?= h($ev['evaluador_nombre']) ?></td>
                            <td>
                                <?php if ($ev['calificacion'] !== null): ?>
                                    <span class="badge badge-<?= $ev['calificacion'] >= 80 ? 'success' : ($ev['calificacion'] >= 60 ? 'warning' : 'danger') ?>">
                                        <?= h($ev['calificacion']) ?>/100
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($ev['created_at']) ?></td>
                            <?php if ($puedeEditar): ?>
                            <td>
                                <button type="button" class="btn btn-sm btn-ghost" onclick="openEditModal(<?= (int)$ev['id'] ?>, <?= (float)($ev['calificacion'] ?? 0) ?>, '<?= h(addslashes($ev['fortalezas'] ?? '')) ?>', '<?= h(addslashes($ev['areas_mejora'] ?? '')) ?>', '<?= h(addslashes($ev['retroalimentacion'] ?? '')) ?>')">Editar</button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php if ($ev['retroalimentacion']): ?>
                            <tr style="background:#f8f9fa;"><td colspan="<?= $puedeEditar ? 6 : 5 ?>" style="font-size:0.85rem;color:var(--color-text-secondary);padding:6px 12px;">
                                <strong>Retro:</strong> <?= h($ev['retroalimentacion']) ?>
                            </td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPaginas > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['p' => $i])) ?>" class="btn btn-sm <?= $i === $pagina ? 'btn-primary' : 'btn-ghost' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Editar -->
<div id="editModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <span class="modal-close" onclick="document.getElementById('editModal').classList.remove('modal-open')">&times;</span>
        <h3>Editar evaluación</h3>
        <form method="POST" action="" class="form" novalidate id="editForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="id" id="editId">
            <div class="form-group">
                <label for="editCalificacion">Calificación (0-100)</label>
                <input type="number" id="editCalificacion" name="calificacion" min="0" max="100" step="0.1">
            </div>
            <div class="form-group"><label for="editFortalezas">Fortalezas</label><textarea id="editFortalezas" name="fortalezas" rows="2"></textarea></div>
            <div class="form-group"><label for="editAreasMejora">Áreas de mejora</label><textarea id="editAreasMejora" name="areas_mejora" rows="2"></textarea></div>
            <div class="form-group"><label for="editRetroalimentacion">Retroalimentación</label><textarea id="editRetroalimentacion" name="retroalimentacion" rows="2"></textarea></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, calificacion, fortalezas, areasMejora, retroalimentacion) {
    document.getElementById('editId').value = id;
    document.getElementById('editCalificacion').value = calificacion || '';
    document.getElementById('editFortalezas').value = fortalezas || '';
    document.getElementById('editAreasMejora').value = areasMejora || '';
    document.getElementById('editRetroalimentacion').value = retroalimentacion || '';
    document.getElementById('editModal').classList.add('modal-open');
}
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
