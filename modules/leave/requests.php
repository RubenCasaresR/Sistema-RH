<?php
/**
 * Solicitudes de vacaciones, permisos e incapacidades.
 * Los empleados pueden crear solicitudes; admins/gerentes ven todas.
 */

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('leave.request');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user']['role_name'] ?? '';

// Obtener empleado vinculado al usuario actual
$stmtEmp = $db->prepare("SELECT id, nombre, apellido_paterno, apellido_materno FROM employees WHERE user_id = :uid AND activo = 1 LIMIT 1");
$stmtEmp->execute([':uid' => $userId]);
$myEmployee = $stmtEmp->fetch();

$errors = [];

// Procesar cancelación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $csrfTokenC = $_POST['csrf_token'] ?? '';
    if (verifyCSRFToken($csrfTokenC)) {
        $cancelId = (int)($_POST['request_id'] ?? 0);
        if ($cancelId > 0) {
            $stmtC = $db->prepare("UPDATE leave_requests SET estatus = 'cancelado' WHERE id = :id AND estatus = 'pendiente' AND employee_id = :eid");
            $stmtC->execute([':id' => $cancelId, ':eid' => $myEmployee['id'] ?? 0]);
            if ($stmtC->rowCount() > 0) {
                setFlash('success', 'Solicitud cancelada.');
            }
        }
    }
    redirect(APP_URL . '/modules/leave/requests.php');
}

// Procesar nueva solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $errors[] = 'Token de seguridad inválido.';
    }

    $employeeId = (int)($_POST['employee_id'] ?? ($myEmployee['id'] ?? 0));
    $tipo = $_POST['tipo'] ?? '';
    $fechaInicio = $_POST['fecha_inicio'] ?? '';
    $fechaFin = $_POST['fecha_fin'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');

    if ($employeeId <= 0) $errors[] = 'Empleado no válido.';
    if (!in_array($tipo, ['vacaciones', 'permiso_con_goce', 'permiso_sin_goce', 'incapacidad'])) $errors[] = 'Tipo inválido.';
    if (!$fechaInicio || !$fechaFin) $errors[] = 'Fechas requeridas.';
    if ($fechaInicio > $fechaFin) $errors[] = 'La fecha fin debe ser posterior a la inicio.';
    if ($fechaInicio < date('Y-m-d')) $errors[] = 'La fecha de inicio no puede ser en el pasado.';

    if (count($errors) === 0) {
        $inicio = new DateTime($fechaInicio);
        $fin = new DateTime($fechaFin);
        $dias = (int)$inicio->diff($fin)->days + 1;
        if ($dias > 60) $errors[] = 'El período no puede exceder 60 días.';

        try {
            $stmt = $db->prepare("
                INSERT INTO leave_requests (employee_id, tipo, fecha_inicio, fecha_fin, dias_solicitados, motivo, estatus)
                VALUES (:eid, :tipo, :inicio, :fin, :dias, :motivo, 'pendiente')
            ");
            $stmt->execute([
                ':eid'    => $employeeId,
                ':tipo'   => $tipo,
                ':inicio' => $fechaInicio,
                ':fin'    => $fechaFin,
                ':dias'   => $dias,
                ':motivo' => $motivo,
            ]);

            setFlash('success', 'Solicitud enviada. Espera la autorización de tu jefe.');
            redirect(APP_URL . '/modules/leave/requests.php');
        } catch (PDOException $e) {
            error_log('Error al crear solicitud: ' . $e->getMessage());
            $errors[] = 'Error al guardar la solicitud.';
        }
    }
}

// Listar solicitudes con filtros y paginación
$esAdmin = in_array($userRole, ['Administrador RH', 'Gerente RH']);
$estatusFilter = trim($_GET['estatus'] ?? '');
$pageReq = max(1, (int)($_GET['page'] ?? 1));
$perPageReq = 50;
$paramsList = [];
$whereList = '';
$stmt = true;

if ($esAdmin) {
    if ($estatusFilter !== '') {
        $whereList .= ' AND lr.estatus = :estatus';
        $paramsList[':estatus'] = $estatusFilter;
    }
} elseif ($myEmployee) {
    $whereList .= ' AND lr.employee_id = :eid';
    $paramsList[':eid'] = $myEmployee['id'];
    if ($estatusFilter !== '') {
        $whereList .= ' AND lr.estatus = :estatus2';
        $paramsList[':estatus2'] = $estatusFilter;
    }
} else {
    $stmt = false;
}

if ($stmt !== false) {
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM leave_requests lr WHERE 1=1 $whereList");
    $stmtCount->execute($paramsList);
    $totalReqs = (int)$stmtCount->fetchColumn();
    $totalPagesReqs = max(1, (int)ceil($totalReqs / $perPageReq));
    if ($pageReq > $totalPagesReqs) $pageReq = $totalPagesReqs;
    $offsetReqs = ($pageReq - 1) * $perPageReq;

    $stmt = $db->prepare("
        SELECT lr.*, e.nombre, e.apellido_paterno, e.apellido_materno,
               u.username AS aprobador
        FROM leave_requests lr
        INNER JOIN employees e ON e.id = lr.employee_id
        LEFT JOIN users u ON u.id = lr.aprobado_por
        WHERE 1=1 $whereList
        ORDER BY lr.created_at DESC
        LIMIT :lim OFFSET :off
    ");
    $stmt->bindValue(':lim', $perPageReq, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offsetReqs, PDO::PARAM_INT);
    foreach ($paramsList as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
}

$requests = $stmt ? $stmt->fetchAll() : [];

// Empleados para el combo (solo admins)
$emps = [];
if ($esAdmin) {
    $emps = $db->query("SELECT id, nombre, apellido_paterno, apellido_materno FROM employees WHERE activo = 1 ORDER BY apellido_paterno")->fetchAll();
}

// Calcular saldo disponible para el empleado actual
$saldoDisponible = 0;
$diasPorLeyReq = 0;
$diasDisfReq = 0;
if ($myEmployee) {
    $stmtBalReq = $db->prepare("SELECT COALESCE(SUM(dias_disfrutados), 0) FROM leave_balance WHERE employee_id = :eid AND periodo = YEAR(CURDATE())");
    $stmtBalReq->execute([':eid' => $myEmployee['id']]);
    $diasDisfReq = (float)$stmtBalReq->fetchColumn();
    $stmtEmpReq = $db->prepare("SELECT fecha_ingreso FROM employees WHERE id = :id LIMIT 1");
    $stmtEmpReq->execute([':id' => $myEmployee['id']]);
    $empReq = $stmtEmpReq->fetch();
    if ($empReq && $empReq['fecha_ingreso']) {
        $antReq = (int)(new DateTime($empReq['fecha_ingreso']))->diff(new DateTime())->y;
        $diasPorLeyReq = calculateLFTHolidays($antReq);
    }
    $saldoDisponible = max(0, $diasPorLeyReq - $diasDisfReq);
}

$csrfToken = $_SESSION['csrf_token'] ?? generateCSRFToken();

// Nombre del tipo para mostrar
$tipoLabels = [
    'vacaciones'        => 'Vacaciones',
    'permiso_con_goce'  => 'Permiso con goce',
    'permiso_sin_goce'  => 'Permiso sin goce',
    'incapacidad'       => 'Incapacidad',
];
?>

<div class="page-header">
    <h2>Vacaciones y permisos</h2>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
    <h3 class="card-title">Nueva solicitud</h3>
    <form method="POST" action="" class="form" novalidate>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <?php if ($esAdmin): ?>
            <div class="form-group">
                <label for="employee_name">Empleado</label>
                <input type="text" list="empList" id="employee_name" autocomplete="off" placeholder="Escribir para buscar" required>
                <input type="hidden" name="employee_id" id="employee_id_hidden" value="">
                <datalist id="empList">
                    <?php foreach ($emps as $e): ?>
                        <option value="<?= h($e['apellido_paterno'] . ', ' . $e['nombre']) ?>" data-id="<?= (int)$e['id'] ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
        <?php elseif ($myEmployee): ?>
            <input type="hidden" name="employee_id" value="<?= (int)$myEmployee['id'] ?>">
            <p style="margin-bottom:12px;font-weight:500;"><?= h($myEmployee['nombre'] . ' ' . $myEmployee['apellido_paterno']) ?></p>
        <?php endif; ?>

        <?php if ($myEmployee): ?>
            <div style="padding:8px 12px;background:var(--color-surface-alt);border-radius:var(--radius-sm);margin-bottom:12px;font-size:0.9rem;">
                <strong>Saldo de vacaciones:</strong>
                <span style="color:var(--color-<?= $saldoDisponible > 0 ? 'success' : 'danger' ?>);font-weight:600;">
                    <?= $saldoDisponible ?> día(s) disponible(s)
                </span>
                <span style="color:var(--color-text-secondary);font-size:0.8rem;">
                    (<?= $diasPorLeyReq ?> por ley - <?= $diasDisfReq ?> disfrutados)
                </span>
            </div>
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" required>
                    <option value="vacaciones">Vacaciones</option>
                    <option value="permiso_con_goce">Permiso con goce de sueldo</option>
                    <option value="permiso_sin_goce">Permiso sin goce de sueldo</option>
                    <option value="incapacidad">Incapacidad</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="fecha_inicio">Fecha inicio</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" required>
            </div>
            <div class="form-group">
                <label for="fecha_fin">Fecha fin</label>
                <input type="date" id="fecha_fin" name="fecha_fin" required>
            </div>
        </div>
        <p id="diasCalculados" style="font-size:0.85rem;color:var(--color-text-secondary);margin:-8px 0 12px;">Días: —</p>
        <div class="form-group">
            <label for="motivo">Motivo / Observaciones</label>
            <textarea id="motivo" name="motivo" rows="3" maxlength="500"></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Enviar solicitud</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;padding:12px 16px;border-bottom:1px solid var(--color-border);">
        <h3 class="card-title" style="margin:0;">Solicitudes existentes</h3>
        <form method="GET" action="" style="display:flex;gap:8px;align-items:center;">
            <select name="estatus" style="padding:4px 8px;border:1px solid var(--color-border);border-radius:var(--radius-sm);font-size:0.85rem;" onchange="this.form.submit()">
                <option value="">Todos los estatus</option>
                <option value="pendiente" <?= $estatusFilter === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="aprobado" <?= $estatusFilter === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
                <option value="rechazado" <?= $estatusFilter === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                <option value="cancelado" <?= $estatusFilter === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
            </select>
            <?php if ($estatusFilter !== ''): ?>
                <a href="<?= APP_URL ?>/modules/leave/requests.php" class="btn btn-sm btn-link">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <?php if ($esAdmin): ?><th>Empleado</th><?php endif; ?>
                    <th>Tipo</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Días</th>
                    <th>Estatus</th>
                    <th>Aprobador</th>
                    <th>Solicitado</th>
                    <?php if (!$esAdmin): ?><th>Acción</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (count($requests) === 0): ?>
                    <tr><td colspan="<?= $esAdmin ? 8 : 8 ?>" class="empty-state">Sin solicitudes.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $r): ?>
                        <?php
                        $estadoClass = [
                            'pendiente' => 'warning',
                            'aprobado'  => 'success',
                            'rechazado' => 'danger',
                            'cancelado' => 'secondary',
                        ][$r['estatus']] ?? 'secondary';
                        $esPasado = $r['estatus'] === 'aprobado' && strtotime($r['fecha_fin']) < time();
                        ?>
                        <tr>
                            <?php if ($esAdmin): ?>
                                <td><?= h($r['nombre'] . ' ' . $r['apellido_paterno']) ?></td>
                            <?php endif; ?>
                            <td><?= $tipoLabels[$r['tipo']] ?? $r['tipo'] ?></td>
                            <td><?= formatDate($r['fecha_inicio']) ?></td>
                            <td><?= formatDate($r['fecha_fin']) ?></td>
                            <td><?= (int)$r['dias_solicitados'] ?></td>
                            <td>
                                <span class="badge badge-<?= $estadoClass ?>"><?= ucfirst($r['estatus']) ?></span>
                                <?php if ($esPasado): ?>
                                    <span class="badge badge-secondary" style="font-size:0.7rem;">Disfrutado</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($r['aprobador'] ?? '—') ?></td>
                            <td><?= formatDate($r['created_at']) ?></td>
                            <?php if (!$esAdmin): ?>
                                <td>
                                    <?php if ($r['estatus'] === 'pendiente'): ?>
                                        <form method="POST" action="" style="display:inline">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <button type="submit" class="btn btn-sm btn-link" style="color:var(--color-danger);" onclick="return confirm('¿Cancelar esta solicitud?')">Cancelar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPagesReqs > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:1px solid var(--color-border);">
        <span style="font-size:0.85rem;color:var(--color-text-secondary);">
            Mostrando <?= $offsetReqs + 1 ?>–<?= min($offsetReqs + $perPageReq, $totalReqs) ?> de <?= $totalReqs ?>
        </span>
        <div style="display:flex;gap:4px;">
            <?php if ($pageReq > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pageReq - 1])) ?>" class="btn btn-sm btn-link">&laquo; Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pageReq - 2); $i <= min($totalPagesReqs, $pageReq + 2); $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="btn btn-sm <?= $i === $pageReq ? 'btn-primary' : 'btn-link' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pageReq < $totalPagesReqs): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pageReq + 1])) ?>" class="btn btn-sm btn-link">Siguiente &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inicio = document.getElementById('fecha_inicio');
    const fin = document.getElementById('fecha_fin');
    const label = document.getElementById('diasCalculados');
    const saldo = <?= $saldoDisponible ?>;

    function calcularDias() {
        if (!inicio.value || !fin.value) {
            label.textContent = 'Días: —';
            return;
        }
        const d1 = new Date(inicio.value);
        const d2 = new Date(fin.value);
        if (d2 < d1) {
            label.textContent = 'Días: — (fecha fin anterior a inicio)';
            label.style.color = 'var(--color-danger)';
            return;
        }
        const diff = Math.floor((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
        let msg = 'Días: ' + diff;
        if (diff > 60) {
            msg += ' — Excede el límite de 60 días';
            label.style.color = 'var(--color-danger)';
        } else if (saldo > 0 && diff > saldo) {
            msg += ' — Supera el saldo disponible (' + saldo + ')';
            label.style.color = 'var(--color-warning)';
        } else {
            label.style.color = 'var(--color-text-secondary)';
        }
        label.textContent = msg;
    }

    inicio.addEventListener('change', calcularDias);
    fin.addEventListener('change', calcularDias);

    const empInput = document.getElementById('employee_name');
    const empHidden = document.getElementById('employee_id_hidden');
    if (empInput && empHidden) {
        empInput.addEventListener('input', function() {
            const opts = document.querySelectorAll('#empList option');
            let found = false;
            for (const opt of opts) {
                if (opt.value === this.value) {
                    empHidden.value = opt.dataset.id;
                    found = true;
                    break;
                }
            }
            if (!found) empHidden.value = '';
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
