<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('performance.read');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$errors = [];
$puedeCrear = can('performance.create');

$tab = $_GET['tab'] ?? 'courses';
$employeeId = (int)($_GET['employee_id'] ?? 0);

// Crear curso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_course') {
    if (!$puedeCrear) { $errors[] = 'Permiso denegado.'; }

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }

    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = $_POST['tipo'] ?? 'curso';
    $horas = max(0, (int)($_POST['horas'] ?? 0));

    if ($nombre === '') $errors[] = 'Nombre del curso obligatorio.';
    if (strlen($nombre) > 255) $errors[] = 'Nombre demasiado largo.';
    if ($horas > 9999) $errors[] = 'Horas fuera de rango.';

    if (count($errors) === 0) {
        try {
            $stmt = $db->prepare("INSERT INTO training_courses (nombre, descripcion, tipo, horas) VALUES (:n, :d, :t, :h)");
            $stmt->execute([':n' => $nombre, ':d' => $descripcion, ':t' => $tipo, ':h' => $horas]);
            setFlash('success', 'Curso agregado.');
            redirect(APP_URL . '/modules/performance/training.php?tab=courses');
        } catch (PDOException $e) { error_log('Error curso: ' . $e->getMessage()); $errors[] = 'Error al guardar.'; }
    }
}

// Asignar capacitación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign') {
    if (!$puedeCrear) { $errors[] = 'Permiso denegado.'; }

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }

    $empId = (int)($_POST['employee_id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);
    $fechaInicio = $_POST['fecha_inicio'] ?? null;
    $fechaFin = $_POST['fecha_fin'] ?? null;

    if ($empId <= 0) $errors[] = 'Seleccione empleado.';
    if ($courseId <= 0) $errors[] = 'Curso inválido.';
    if ($fechaFin && $fechaInicio && $fechaFin < $fechaInicio) $errors[] = 'Fecha fin no puede ser anterior a inicio.';

    if (count($errors) === 0) {
        try {
            $stmt = $db->prepare("INSERT INTO training_history (employee_id, course_id, fecha_inicio, fecha_fin, estatus) VALUES (:eid, :cid, :fi, :ff, 'inscrito')");
            $stmt->execute([':eid' => $empId, ':cid' => $courseId, ':fi' => $fechaInicio, ':ff' => $fechaFin]);
            setFlash('success', 'Capacitación asignada.');
            redirect(APP_URL . '/modules/performance/training.php?tab=history');
        } catch (PDOException $e) { error_log('Error asignar: ' . $e->getMessage()); $errors[] = 'Error al asignar.'; }
    }
}

// Actualizar estatus capacitación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['completar', 'cancelar'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }

    $historyId = (int)($_POST['id'] ?? 0);
    $nuevoEstatus = $_POST['action'] === 'completar' ? 'completado' : 'cancelado';

    if ($historyId <= 0) $errors[] = 'Registro inválido.';

    if (count($errors) === 0) {
        try {
            $stmt = $db->prepare("UPDATE training_history SET estatus = :est WHERE id = :id");
            $stmt->execute([':est' => $nuevoEstatus, ':id' => $historyId]);
            setFlash('success', 'Registro actualizado.');
            redirect(APP_URL . '/modules/performance/training.php?tab=history');
        } catch (PDOException $e) { error_log('Error actualizar: ' . $e->getMessage()); $errors[] = 'Error al actualizar.'; }
    }
}

// Cursos activos
$courses = $db->query("SELECT * FROM training_courses WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Historial paginado
$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 20;
$offset = ($pagina - 1) * $porPagina;
$paramsH = [];
$whereH = 'WHERE 1=1';
$countParamsH = [];
$countWhereH = 'WHERE 1=1';
if ($employeeId > 0) {
    $whereH = 'WHERE th.employee_id = :eid';
    $paramsH[':eid'] = $employeeId;
    $countWhereH = 'WHERE employee_id = :eid';
    $countParamsH[':eid'] = $employeeId;
}
$stmtCH = $db->prepare("SELECT COUNT(*) FROM training_history $countWhereH");
$stmtCH->execute($countParamsH);
$totalH = (int)$stmtCH->fetchColumn();
$totalPaginasH = max(1, (int)ceil($totalH / $porPagina));

$stmtH = $db->prepare("
    SELECT th.*, e.nombre, e.apellido_paterno, e.apellido_materno, tc.nombre AS curso_nombre, tc.tipo AS curso_tipo
    FROM training_history th
    INNER JOIN employees e ON e.id = th.employee_id
    INNER JOIN training_courses tc ON tc.id = th.course_id
    $whereH
    ORDER BY th.created_at DESC
    LIMIT $porPagina OFFSET $offset
");
$stmtH->execute($paramsH);
$history = $stmtH->fetchAll();

$emps = $db->query("SELECT id, nombre, apellido_paterno, apellido_materno FROM employees WHERE activo = 1 ORDER BY apellido_paterno")->fetchAll();
$csrfToken = generateCSRFToken();

$tipoLabels = ['curso' => 'Curso', 'taller' => 'Taller', 'certificacion' => 'Certificación', 'diplomado' => 'Diplomado'];
$estadoColors = ['inscrito' => 'info', 'completado' => 'success', 'cancelado' => 'danger'];
?>

<div class="page-header">
    <h2>Capacitación</h2>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<!-- Tabs -->
<div class="card" style="display:flex;gap:0;padding:0;overflow:hidden;">
    <a href="?tab=courses" style="flex:1;text-align:center;padding:12px;<?= $tab === 'courses' ? 'background:var(--color-primary);color:#fff;font-weight:600;' : '' ?>">Cursos</a>
    <a href="?tab=history<?= $employeeId > 0 ? '&employee_id=' . $employeeId : '' ?>" style="flex:1;text-align:center;padding:12px;<?= $tab === 'history' ? 'background:var(--color-primary);color:#fff;font-weight:600;' : '' ?>">Historial</a>
</div>

<?php if ($tab === 'courses'): ?>
    <!-- Catálogo de cursos -->
    <?php if ($puedeCrear): ?>
    <div class="card" style="max-width:600px;">
        <h3 class="card-title">Agregar curso</h3>
        <form method="POST" action="" class="form" novalidate>
            <input type="hidden" name="action" value="create_course">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="form-group"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" required maxlength="255"></div>
            <div class="form-row">
                <div class="form-group"><label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo">
                        <?php foreach ($tipoLabels as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label for="horas">Horas</label><input type="number" id="horas" name="horas" min="0" max="9999" value="0"></div>
            </div>
            <div class="form-group"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="3" maxlength="2000"></textarea></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary" id="btnAgregarCurso">Agregar</button></div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3 class="card-title">Catálogo de cursos</h3>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Nombre</th><th>Tipo</th><th>Horas</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php if (count($courses) === 0): ?>
                        <tr><td colspan="4" class="empty-state">Sin cursos registrados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td><strong><?= h($c['nombre']) ?></strong></td>
                                <td><span class="badge badge-info"><?= $tipoLabels[$c['tipo']] ?? $c['tipo'] ?></span></td>
                                <td><?= (int)$c['horas'] ?>h</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-ghost" onclick="openAssignModal(<?= (int)$c['id'] ?>, '<?= h(addslashes($c['nombre'])) ?>')">Asignar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal asignar curso (único) -->
    <div id="assignModal" class="modal">
        <div class="modal-content" style="max-width:450px;">
            <span class="modal-close" onclick="document.getElementById('assignModal').classList.remove('modal-open')">&times;</span>
            <h3 id="assignModalTitle">Asignar curso</h3>
            <form method="POST" action="" class="form" novalidate>
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="course_id" id="assignCourseId">
                <div class="form-group">
                    <label for="assignEmployeeId">Empleado *</label>
                    <select id="assignEmployeeId" name="employee_id" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($emps as $e): ?>
                            <option value="<?= (int)$e['id'] ?>"><?= h($e['apellido_paterno'] . ', ' . $e['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="assignFechaInicio">Inicio</label><input type="date" id="assignFechaInicio" name="fecha_inicio"></div>
                    <div class="form-group"><label for="assignFechaFin">Fin</label><input type="date" id="assignFechaFin" name="fecha_fin"></div>
                </div>
                <div class="form-actions"><button type="submit" class="btn btn-primary" id="btnAsignarCurso">Asignar</button></div>
            </form>
        </div>
    </div>

    <script>
    function openAssignModal(courseId, courseName) {
        document.getElementById('assignCourseId').value = courseId;
        document.getElementById('assignModalTitle').textContent = 'Asignar: ' + courseName;
        document.getElementById('assignModal').classList.add('modal-open');
    }
    document.getElementById('btnAgregarCurso')?.addEventListener('click', function() {
        this.disabled = true; this.textContent = 'Guardando…'; this.form.submit();
    });
    document.getElementById('btnAsignarCurso')?.addEventListener('click', function() {
        this.disabled = true; this.textContent = 'Asignando…'; this.form.submit();
    });
    </script>

<?php else: ?>
    <!-- Historial -->
    <div class="card">
        <h3 class="card-title">Historial de capacitación</h3>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Empleado</th><th>Curso</th><th>Inicio</th><th>Fin</th><th>Estatus</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php if (count($history) === 0): ?>
                        <tr><td colspan="6" class="empty-state">Sin registros de capacitación.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td><?= h($h['nombre'] . ' ' . $h['apellido_paterno']) ?></td>
                                <td>
                                    <?= h($h['curso_nombre']) ?>
                                    <span class="badge badge-secondary" style="font-size:0.7rem;"><?= $tipoLabels[$h['curso_tipo']] ?? $h['curso_tipo'] ?></span>
                                </td>
                                <td><?= $h['fecha_inicio'] ? formatDate($h['fecha_inicio']) : '—' ?></td>
                                <td><?= $h['fecha_fin'] ? formatDate($h['fecha_fin']) : '—' ?></td>
                                <td><span class="badge badge-<?= $estadoColors[$h['estatus']] ?>"><?= ucfirst($h['estatus']) ?></span></td>
                                <td>
                                    <?php if ($h['estatus'] === 'inscrito'): ?>
                                    <form method="POST" action="" style="display:inline" onsubmit="return confirm('¿Completar esta capacitación?')">
                                        <input type="hidden" name="action" value="completar">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Completar</button>
                                    </form>
                                    <form method="POST" action="" style="display:inline" onsubmit="return confirm('¿Cancelar esta capacitación?')">
                                        <input type="hidden" name="action" value="cancelar">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Cancelar</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPaginasH > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPaginasH; $i++): ?>
                <a href="?tab=history&p=<?= $i ?><?= $employeeId > 0 ? '&employee_id=' . $employeeId : '' ?>" class="btn btn-sm <?= $i === $pagina ? 'btn-primary' : 'btn-ghost' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
