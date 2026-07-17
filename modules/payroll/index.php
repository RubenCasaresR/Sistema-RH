<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('payroll.read');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$errors = [];
$puedeCalcular = can('payroll.calculate');
$puedeExportar = can('payroll.export');

// Crear nuevo período
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_period') {
    if (!$puedeCalcular) { $errors[] = 'Permiso denegado.'; }

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }

    $periodo = trim($_POST['periodo'] ?? '');
    $fechaInicio = $_POST['fecha_inicio'] ?? '';
    $fechaFin = $_POST['fecha_fin'] ?? '';

    if ($periodo === '') $errors[] = 'Período requerido.';
    if (strlen($periodo) > 20) $errors[] = 'Período demasiado largo.';
    if (!$fechaInicio || !$fechaFin) $errors[] = 'Fechas requeridas.';
    if ($fechaInicio && $fechaFin && $fechaFin < $fechaInicio) $errors[] = 'Fecha fin no puede ser anterior a inicio.';

    if (count($errors) === 0) {
        try {
            $tipoPeriodo = $_POST['tipo_periodo'] ?? 'mensual';
            if (!in_array($tipoPeriodo, ['mensual', 'quincenal'])) $tipoPeriodo = 'mensual';
            $stmt = $db->prepare("INSERT INTO payroll_periods (periodo, tipo_periodo, fecha_inicio, fecha_fin) VALUES (:p, :tp, :fi, :ff)");
            $stmt->execute([':p' => $periodo, ':tp' => $tipoPeriodo, ':fi' => $fechaInicio, ':ff' => $fechaFin]);
            setFlash('success', 'Período creado.');
            redirect(APP_URL . '/modules/payroll/index.php');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errors[] = 'El período "' . h($periodo) . '" ya existe.';
            } else {
                error_log('Error payroll period: ' . $e->getMessage());
                $errors[] = 'Error al crear período.';
            }
        }
    }
}

// Eliminar período
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_period') {
    if (!$puedeCalcular) { $errors[] = 'Permiso denegado.'; }

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }

    $periodId = (int)($_POST['id'] ?? 0);
    if ($periodId <= 0) $errors[] = 'Período inválido.';

    if (count($errors) === 0) {
        try {
            $stmt = $db->prepare("DELETE FROM payroll_periods WHERE id = :id");
            $stmt->execute([':id' => $periodId]);
            setFlash('success', 'Período eliminado.');
            redirect(APP_URL . '/modules/payroll/index.php');
        } catch (PDOException $e) {
            error_log('Error delete period: ' . $e->getMessage());
            $errors[] = 'Error al eliminar período.';
        }
    }
}

// Cerrar período
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_period') {
    if (!$puedeCalcular) { $errors[] = 'Permiso denegado.'; }

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }

    $periodId = (int)($_POST['id'] ?? 0);
    if ($periodId <= 0) $errors[] = 'Período inválido.';

    if (count($errors) === 0) {
        try {
            $stmt = $db->prepare("UPDATE payroll_periods SET estatus = 'cerrado' WHERE id = :id AND estatus != 'cerrado'");
            $stmt->execute([':id' => $periodId]);
            if ($stmt->rowCount() > 0) {
                setFlash('success', 'Período cerrado.');
            } else {
                $errors[] = 'El período ya está cerrado o no existe.';
            }
            redirect(APP_URL . '/modules/payroll/index.php');
        } catch (PDOException $e) {
            error_log('Error close period: ' . $e->getMessage());
            $errors[] = 'Error al cerrar período.';
        }
    }
}

// Filtro y paginación
$search = trim($_GET['search'] ?? '');
$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 20;
$offset = ($pagina - 1) * $porPagina;

$params = [];
$where = '';
if ($search !== '') {
    $where = 'WHERE pp.periodo LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

$countSql = "SELECT COUNT(*) FROM payroll_periods pp $where";
$stmtC = $db->prepare($countSql);
$stmtC->execute($params);
$total = (int)$stmtC->fetchColumn();
$totalPaginas = max(1, (int)ceil($total / $porPagina));

$sql = "
    SELECT pp.*,
           (SELECT COUNT(*) FROM payroll_items pi WHERE pi.period_id = pp.id) AS total_empleados,
           (SELECT SUM(pi.sueldo_neto) FROM payroll_items pi WHERE pi.period_id = pp.id) AS total_neto
    FROM payroll_periods pp
    $where
    ORDER BY pp.periodo DESC
    LIMIT $porPagina OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$periods = $stmt->fetchAll();

$csrfToken = generateCSRFToken();

$estatusColors = ['abierto' => 'success', 'calculado' => 'info', 'cerrado' => 'secondary'];
?>

<div class="page-header">
    <h2>Nómina</h2>
    <?php if ($puedeCalcular): ?>
        <button class="btn btn-primary" onclick="document.getElementById('modalPeriodo').classList.add('modal-open')">+ Nuevo período</button>
    <?php endif; ?>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<!-- Modal nuevo período -->
<div id="modalPeriodo" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <span class="modal-close" onclick="this.closest('.modal').classList.remove('modal-open')">&times;</span>
        <h3>Nuevo período de nómina</h3>
        <form method="POST" action="" class="form" novalidate>
            <input type="hidden" name="action" value="create_period">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="form-group"><label for="periodo">Período (ej. 2026-06)</label><input type="text" id="periodo" name="periodo" required placeholder="2026-06" maxlength="20"></div>
            <div class="form-group"><label for="tipo_periodo">Tipo de período</label>
                <select id="tipo_periodo" name="tipo_periodo" required>
                    <option value="mensual">Mensual</option>
                    <option value="quincenal">Quincenal</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="fecha_inicio">Fecha inicio</label><input type="date" id="fecha_inicio" name="fecha_inicio" required></div>
                <div class="form-group"><label for="fecha_fin">Fecha fin</label><input type="date" id="fecha_fin" name="fecha_fin" required></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary" id="btnCrearPeriodo">Crear</button></div>
        </form>
    </div>
</div>

<!-- Filtro -->
<div class="card">
    <form method="GET" action="" class="search-form">
        <div class="form-group" style="margin:0;">
            <input type="text" name="search" placeholder="Buscar período (ej. 2026)…" value="<?= h($search) ?>" style="max-width:300px;">
            <button type="submit" class="btn btn-ghost">Buscar</button>
            <?php if ($search !== ''): ?>
                <a href="?" class="btn btn-ghost">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Período</th>
                    <th>Tipo</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Empleados</th>
                    <th>Total neto</th>
                    <th>Estatus</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($periods) === 0): ?>
                    <tr><td colspan="8" class="empty-state"><?= $search !== '' ? 'Sin resultados para "' . h($search) . '".' : 'Sin períodos de nómina. Crea el primer período para comenzar.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($periods as $p): ?>
                        <tr>
                            <td><strong><?= h($p['periodo']) ?></strong></td>
                            <td><span class="badge badge-<?= ($p['tipo_periodo'] ?? 'mensual') === 'quincenal' ? 'warning' : 'secondary' ?>"><?= ucfirst($p['tipo_periodo'] ?? 'Mensual') ?></span></td>
                            <td><?= formatDate($p['fecha_inicio']) ?></td>
                            <td><?= formatDate($p['fecha_fin']) ?></td>
                            <td><?= (int)$p['total_empleados'] ?></td>
                            <td><?= $p['total_neto'] ? formatCurrency((float)$p['total_neto']) : '—' ?></td>
                            <td><span class="badge badge-<?= $estatusColors[$p['estatus']] ?>"><?= ucfirst($p['estatus']) ?></span></td>
                            <td class="actions-cell">
                                <a href="<?= APP_URL ?>/modules/payroll/calculate.php?period_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-ghost">Detalle</a>
                                <?php if ($puedeExportar && $p['estatus'] !== 'abierto'): ?>
                                    <a href="<?= APP_URL ?>/modules/payroll/export.php?period_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-ghost">Exportar</a>
                                <?php endif; ?>
                                <?php if ($puedeCalcular && $p['estatus'] !== 'cerrado'): ?>
                                    <form method="POST" action="" style="display:inline" onsubmit="return confirm('¿Cerrar el período <?= h(addslashes($p['periodo'])) ?>? Ya no se podrá modificar.')">
                                        <input type="hidden" name="action" value="close_period">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Cerrar</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($puedeCalcular): ?>
                                    <form method="POST" action="" style="display:inline" onsubmit="return confirm('¿Eliminar el período <?= h(addslashes($p['periodo'])) ?>? Esta acción no se puede deshacer.')">
                                        <input type="hidden" name="action" value="delete_period">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
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

<script>
document.getElementById('btnCrearPeriodo')?.addEventListener('click', function() {
    this.disabled = true; this.textContent = 'Creando…'; this.form.submit();
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
