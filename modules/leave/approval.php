<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('leave.approve');

$db = getDB();

$userRole = $_SESSION['user']['role_name'] ?? '';
$esJefe = $userRole === 'Jefe de área';
$miDepto = null;
if ($esJefe) {
    $stmtD = $db->prepare("SELECT departamento FROM employees WHERE user_id = :uid AND activo = 1 LIMIT 1");
    $stmtD->execute([':uid' => (int)$_SESSION['user_id']]);
    $miDepto = $stmtD->fetchColumn();
}

$tipoLabels = [
    'vacaciones'        => 'Vacaciones',
    'permiso_con_goce'  => 'Permiso c/goce',
    'permiso_sin_goce'  => 'Permiso s/goce',
    'incapacidad'       => 'Incapacidad',
];

$csrfToken = $_SESSION['csrf_token'] ?? generateCSRFToken();

// Procesar aprobación / rechazo (antes de header.php para poder usar redirect())
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $comentarios = trim($_POST['comentarios'] ?? '');

    if (verifyCSRFToken($csrfToken) && $requestId > 0 && in_array($action, ['aprobar', 'rechazar'], true)) {
        if ($action === 'rechazar' && $comentarios === '') {
            setFlash('danger', 'Debes proporcionar un motivo al rechazar la solicitud.');
            redirect(APP_URL . '/modules/leave/approval.php');
        }

        $nuevoEstatus = $action === 'aprobar' ? 'aprobado' : 'rechazado';
        $userId = (int)$_SESSION['user_id'];

        try {
            if ($nuevoEstatus === 'aprobado') {
                $stmtR = $db->prepare("SELECT employee_id, tipo, dias_solicitados FROM leave_requests WHERE id = :id AND estatus = 'pendiente' LIMIT 1");
                $stmtR->execute([':id' => $requestId]);
                $req = $stmtR->fetch();
                if ($req && $req['tipo'] === 'vacaciones') {
                    $stmtEmpB = $db->prepare("SELECT fecha_ingreso FROM employees WHERE id = :id LIMIT 1");
                    $stmtEmpB->execute([':id' => $req['employee_id']]);
                    $empB = $stmtEmpB->fetch();
                    $antB = 0;
                    if ($empB && $empB['fecha_ingreso']) {
                        $antB = (int)(new DateTime($empB['fecha_ingreso']))->diff(new DateTime())->y;
                    }
                    $diasPorLeyB = calculateLFTHolidays($antB);
                    $stmtBalB = $db->prepare("SELECT COALESCE(SUM(dias_disfrutados), 0) FROM leave_balance WHERE employee_id = :eid AND periodo = YEAR(CURDATE())");
                    $stmtBalB->execute([':eid' => $req['employee_id']]);
                    $disfB = (float)$stmtBalB->fetchColumn();
                    $dispB = $diasPorLeyB - $disfB;
                    if ($req['dias_solicitados'] > $dispB) {
                        setFlash('warning', 'El empleado no tiene saldo suficiente de vacaciones. Disponibles: ' . max(0, $dispB) . ', solicitados: ' . $req['dias_solicitados'] . '.');
                        redirect(APP_URL . '/modules/leave/approval.php');
                    }
                }
            } else {
                $req = null;
            }

            $stmt = $db->prepare("
                UPDATE leave_requests
                SET estatus = :estatus, aprobado_por = :uid, fecha_aprobacion = NOW(), comentarios_aprobador = :comentarios
                WHERE id = :id AND estatus = 'pendiente'
            ");
            $stmt->execute([
                ':estatus'    => $nuevoEstatus,
                ':uid'        => $userId,
                ':comentarios' => $comentarios,
                ':id'         => $requestId,
            ]);

            if ($stmt->rowCount() > 0) {
                if ($nuevoEstatus === 'aprobado' && $req && $req['tipo'] === 'vacaciones') {
                    $stmtB = $db->prepare("
                        INSERT INTO leave_balance (employee_id, periodo, dias_totales, dias_disfrutados)
                        VALUES (:eid, YEAR(CURDATE()), 0, :dias)
                        ON DUPLICATE KEY UPDATE dias_disfrutados = dias_disfrutados + :dias2
                    ");
                    $stmtB->execute([':eid' => $req['employee_id'], ':dias' => $req['dias_solicitados'], ':dias2' => $req['dias_solicitados']]);
                }

                logAudit($action === 'aprobar' ? 'approve' : 'reject', 'leave', $requestId, json_encode([
                    'comentarios' => $comentarios,
                ]));
                setFlash('success', 'Solicitud ' . ($action === 'aprobar' ? 'aprobada' : 'rechazada') . ' correctamente.');

                if ($req) {
                    $stmtEmpE = $db->prepare("SELECT email, nombre, apellido_paterno FROM employees WHERE id = :id LIMIT 1");
                    $stmtEmpE->execute([':id' => $req['employee_id']]);
                    $empE = $stmtEmpE->fetch();
                    if ($empE && $empE['email']) {
                        $tipoTexto = $tipoLabels[$req['tipo']] ?? $req['tipo'];
                        $asunto = 'Solicitud de ' . $tipoTexto . ' ' . ($nuevoEstatus === 'aprobado' ? 'aprobada' : 'rechazada');
                        $cuerpo = '<p>Hola ' . h($empE['nombre']) . ',</p><p>Tu solicitud de <strong>' . $tipoTexto . '</strong> del ' . $req['fecha_inicio'] . ' al ' . $req['fecha_fin'] . ' (' . (int)$req['dias_solicitados'] . ' días) ha sido <strong>' . ($nuevoEstatus === 'aprobado' ? 'aprobada' : 'rechazada') . '</strong>.</p>' . ($comentarios ? '<p>Comentarios: ' . h($comentarios) . '</p>' : '') . '<p>Saludos,<br>Sistema RH</p>';
                        sendEmail($empE['email'], $asunto, $cuerpo);
                    }
                }
            } else {
                setFlash('warning', 'La solicitud ya fue procesada por otro usuario.');
            }
        } catch (PDOException $e) {
            error_log('Error en aprobación: ' . $e->getMessage());
            setFlash('danger', 'Error al procesar la solicitud.');
        }

        redirect(APP_URL . '/modules/leave/approval.php');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$tipoActual = $_GET['tipo'] ?? '';
$filterEmpId = (int)($_GET['employee_id'] ?? 0);
$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 50;
$offset = ($pagina - 1) * $porPagina;

$pendParams = [];
$pendWhere = '';
if ($miDepto) {
    $pendWhere = ' AND e.departamento = :depto';
    $pendParams[':depto'] = $miDepto;
}
if (in_array($tipoActual, ['vacaciones', 'permiso_con_goce', 'permiso_sin_goce', 'incapacidad'])) {
    $pendWhere .= ' AND lr.tipo = :tipo';
    $pendParams[':tipo'] = $tipoActual;
}
if ($filterEmpId > 0) {
    $pendWhere .= ' AND lr.employee_id = :emp_id';
    $pendParams[':emp_id'] = $filterEmpId;
}

$emps = $db->query("SELECT id, nombre, apellido_paterno, apellido_materno FROM employees WHERE activo = 1 ORDER BY apellido_paterno")->fetchAll();

// Total pendientes
$stmtCount = $db->prepare("SELECT COUNT(*) FROM leave_requests lr INNER JOIN employees e ON e.id = lr.employee_id WHERE lr.estatus = 'pendiente' $pendWhere");
$stmtCount->execute($pendParams);
$totalPend = (int)$stmtCount->fetchColumn();

// Pendientes paginados, ordenados por urgencia (fecha_inicio más próxima primero)
$stmt = $db->prepare("
    SELECT lr.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.puesto, e.departamento
    FROM leave_requests lr
    INNER JOIN employees e ON e.id = lr.employee_id
    WHERE lr.estatus = 'pendiente' $pendWhere
    ORDER BY lr.fecha_inicio ASC, lr.created_at ASC
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute($pendParams);
$pending = $stmt->fetchAll();

$histPagina = max(1, (int)($_GET['hp'] ?? 1));
$histOffset = ($histPagina - 1) * 50;
$histParams = [];
$histWhere = '';
if ($miDepto) {
    $histWhere = ' AND e.departamento = :depto';
    $histParams[':depto'] = $miDepto;
}

$stmtHistCount = $db->prepare("SELECT COUNT(*) FROM leave_requests lr INNER JOIN employees e ON e.id = lr.employee_id WHERE lr.estatus IN ('aprobado', 'rechazado') AND lr.fecha_aprobacion >= DATE_SUB(NOW(), INTERVAL 30 DAY) $histWhere");
$stmtHistCount->execute($histParams);
$totalHist = (int)$stmtHistCount->fetchColumn();

$stmtHist = $db->prepare("
    SELECT lr.*, e.nombre, e.apellido_paterno, e.apellido_materno, u.username AS aprobador
    FROM leave_requests lr
    INNER JOIN employees e ON e.id = lr.employee_id
    LEFT JOIN users u ON u.id = lr.aprobado_por
    WHERE lr.estatus IN ('aprobado', 'rechazado')
      AND lr.fecha_aprobacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      $histWhere
    ORDER BY lr.fecha_aprobacion DESC
    LIMIT 50 OFFSET $histOffset
");
$stmtHist->execute($histParams);
$history = $stmtHist->fetchAll();

$totalPagPend = max(1, (int)ceil($totalPend / $porPagina));
$totalPagHist = max(1, (int)ceil($totalHist / 50));
?>

<div class="page-header">
    <h2>Autorización de solicitudes</h2>
    <a href="<?= APP_URL ?>/modules/leave/requests.php" class="btn btn-link">&larr; Ver todas</a>
</div>

<!-- Pendientes -->
<div class="card">
    <h3 class="card-title">Pendientes (<?= $totalPend ?>)</h3>

    <!-- Filtros -->
    <form method="GET" action="" style="margin-bottom:16px;">
        <div class="search-form" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <select name="tipo" style="padding:6px 10px;border:1px solid var(--color-border);border-radius:var(--radius-sm);">
                <option value="">Todos los tipos</option>
                <?php foreach ($tipoLabels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $tipoActual === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" list="empList" name="employee_name" id="filterEmpName" placeholder="Empleado..." value="<?php $selName = ''; foreach ($emps as $e) { if ($filterEmpId === (int)$e['id']) { $selName = h($e['apellido_paterno'] . ' ' . ($e['apellido_materno'] ?? '') . ', ' . $e['nombre']); break; } } echo $selName; ?>" style="padding:6px 10px;border:1px solid var(--color-border);border-radius:var(--radius-sm);font-size:0.85rem;min-width:180px;">
            <input type="hidden" name="employee_id" id="filterEmpId" value="<?= $filterEmpId ?>">
            <datalist id="empList">
                <?php foreach ($emps as $e): ?>
                    <option value="<?= h($e['apellido_paterno'] . ' ' . ($e['apellido_materno'] ?? '') . ', ' . $e['nombre']) ?>" data-id="<?= (int)$e['id'] ?>">
                <?php endforeach; ?>
            </datalist>
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <?php if ($tipoActual): ?>
                <a href="?" class="btn btn-sm btn-link">Limpiar filtro</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (count($pending) === 0): ?>
        <div class="card" style="box-shadow:none;padding:0;">
            <p class="empty-state">No hay solicitudes pendientes<?= $tipoActual ? ' de este tipo' : '' ?>.</p>
            <p style="text-align:center;margin-top:8px;font-size:0.85rem;color:var(--color-text-secondary);">
                Las solicitudes de vacaciones y permisos aparecerán aquí para su revisión.
            </p>
        </div>
    <?php else: ?>
        <?php foreach ($pending as $r):
            $saldoEmp = 0;
            $diasPorLeyEmp = 0;
            $diasDisfEmp = 0;
            if ($r['tipo'] === 'vacaciones') {
                $stmtBE = $db->prepare("SELECT COALESCE(SUM(dias_disfrutados), 0) FROM leave_balance WHERE employee_id = :eid AND periodo = YEAR(CURDATE())");
                $stmtBE->execute([':eid' => $r['employee_id']]);
                $diasDisfEmp = (float)$stmtBE->fetchColumn();
                $stmtEE = $db->prepare("SELECT fecha_ingreso FROM employees WHERE id = :id LIMIT 1");
                $stmtEE->execute([':id' => $r['employee_id']]);
                $empE = $stmtEE->fetch();
                $antE = 0;
                if ($empE && $empE['fecha_ingreso']) {
                    $antE = (int)(new DateTime($empE['fecha_ingreso']))->diff(new DateTime())->y;
                }
                $diasPorLeyEmp = calculateLFTHolidays($antE);
                $saldoEmp = max(0, $diasPorLeyEmp - $diasDisfEmp);
            }
            $diasParaInicio = (int)((strtotime($r['fecha_inicio']) - strtotime(date('Y-m-d'))) / 86400);
            ?>
            <div style="border:1px solid var(--color-border);border-radius:var(--radius);padding:16px;margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                    <div>
                        <strong><?= h($r['nombre'] . ' ' . $r['apellido_paterno']) ?></strong>
                        <?php if ($r['puesto'] || $r['departamento']): ?>
                            <span style="color:var(--color-text-secondary);font-size:0.8rem;">
                                (<?= h($r['puesto'] ?? '') ?><?= ($r['puesto'] && $r['departamento']) ? ' — ' : '' ?><?= h($r['departamento'] ?? '') ?>)
                            </span>
                        <?php endif; ?>
                        <br>
                        <span class="badge badge-info"><?= $tipoLabels[$r['tipo']] ?? $r['tipo'] ?></span>
                        <span style="color:var(--color-text-secondary);font-size:0.85rem;">
                            <?= formatDate($r['fecha_inicio']) ?> → <?= formatDate($r['fecha_fin']) ?>
                            (<?= (int)$r['dias_solicitados'] ?> días)
                        </span>
                        <?php if ($diasParaInicio >= 0 && $diasParaInicio <= 3): ?>
                            <span class="badge badge-<?= $diasParaInicio <= 1 ? 'danger' : 'warning' ?>" style="font-size:0.75rem;">
                                Inicia en <?= $diasParaInicio === 0 ? 'hoy' : $diasParaInicio . ' día(s)' ?>
                            </span>
                        <?php elseif ($diasParaInicio < 0): ?>
                            <span class="badge badge-secondary" style="font-size:0.75rem;">Ya iniciada</span>
                        <?php endif; ?>
                        <?php if ($r['tipo'] === 'vacaciones'): ?>
                            <span style="font-size:0.8rem;color:var(--color-text-secondary);margin-left:8px;">
                                Saldo: <strong style="color:var(--color-<?= $saldoEmp > 0 ? 'success' : 'danger' ?>)"><?= $saldoEmp ?></strong>/<?= $diasPorLeyEmp ?> días
                            </span>
                        <?php endif; ?>
                    </div>
                    <span style="font-size:0.8rem;color:var(--color-text-secondary);">
                        Solicitado: <?= formatDate($r['created_at']) ?>
                    </span>
                </div>
                <?php if ($r['motivo']): ?>
                    <p style="margin-top:8px;font-size:0.85rem;color:var(--color-text-secondary);">
                        <?= h($r['motivo']) ?>
                    </p>
                <?php endif; ?>

                <form method="POST" action="" style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;"
                      onsubmit="return validarRechazo(this, <?= (int)$r['id'] ?>)">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <input type="text" name="comentarios" placeholder="Comentarios<?= $action === 'rechazar' ? '' : ' (opcional)' ?>"
                           style="flex:1;min-width:200px;padding:6px 10px;border:1px solid var(--color-border);border-radius:var(--radius-sm);font-size:0.85rem;"
                           id="comentarios_<?= (int)$r['id'] ?>">
                    <button type="submit" name="action" value="aprobar" class="btn btn-sm btn-primary"
                            onclick="return confirm('¿Aprobar solicitud de <?= h($r['nombre'] . ' ' . $r['apellido_paterno']) ?> — <?= $tipoLabels[$r['tipo']] ?? $r['tipo'] ?> (<?= (int)$r['dias_solicitados'] ?> días)? <?php if ($r['tipo'] === 'vacaciones'): ?>Saldo: <?= $saldoEmp ?>/<?= $diasPorLeyEmp ?> días. <?php endif; ?>')">Aprobar</button>
                    <button type="submit" name="action" value="rechazar" class="btn btn-sm btn-secondary">Rechazar</button>
                </form>
            </div>
        <?php endforeach; ?>

        <!-- Paginación pendientes -->
        <?php if ($totalPagPend > 1): ?>
            <div style="display:flex;justify-content:center;gap:8px;margin-top:16px;">
                <?php if ($pagina > 1): ?>
                    <a href="?p=<?= $pagina - 1 ?><?= $tipoActual ? '&tipo=' . urlencode($tipoActual) : '' ?>" class="btn btn-sm">&laquo; Anterior</a>
                <?php endif; ?>
                <span style="padding:6px 12px;">Página <?= $pagina ?> de <?= $totalPagPend ?> (<?= $totalPend ?> solicitudes)</span>
                <?php if ($pagina < $totalPagPend): ?>
                    <a href="?p=<?= $pagina + 1 ?><?= $tipoActual ? '&tipo=' . urlencode($tipoActual) : '' ?>" class="btn btn-sm">Siguiente &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Historial reciente -->
<div class="card">
    <h3 class="card-title">Historial reciente (30 días)</h3>
    <div class="table-responsive">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
            <input type="text" id="histSearch" placeholder="Buscar por nombre..." style="padding:6px 12px;border:1px solid var(--color-border);border-radius:var(--radius);flex:1;max-width:300px;" oninput="filtrarHistorial(this.value)">
        </div>
        <table class="table" id="historyTable">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Tipo</th>
                    <th>Período</th>
                    <th>Días</th>
                    <th>Estatus</th>
                    <th>Aprobador</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($history) === 0): ?>
                    <tr><td colspan="7" class="empty-state">Sin actividad reciente.</td></tr>
                <?php else: ?>
                    <?php foreach ($history as $h): ?>
                        <?php
                        $estadoClass = $h['estatus'] === 'aprobado' ? 'success' : 'danger';
                        $esPasado = $h['estatus'] === 'aprobado' && strtotime($h['fecha_fin']) < time();
                        ?>
                        <tr class="hist-row">
                            <td class="hist-name"><?= h($h['nombre'] . ' ' . $h['apellido_paterno']) ?></td>
                            <td><?= $tipoLabels[$h['tipo']] ?? $h['tipo'] ?></td>
                            <td><?= formatDate($h['fecha_inicio']) ?> → <?= formatDate($h['fecha_fin']) ?></td>
                            <td><?= (int)$h['dias_solicitados'] ?></td>
                            <td>
                                <span class="badge badge-<?= $estadoClass ?>"><?= ucfirst($h['estatus']) ?></span>
                                <?php if ($esPasado): ?>
                                    <span class="badge badge-secondary" style="font-size:0.7rem;">Disfrutado</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($h['aprobador'] ?? '—') ?></td>
                            <td><?= $h['fecha_aprobacion'] ? date('d/m/Y', strtotime($h['fecha_aprobacion'])) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($totalPagHist > 1): ?>
            <div style="display:flex;justify-content:center;gap:8px;margin-top:12px;">
                <?php if ($histPagina > 1): ?>
                    <a href="?hp=<?= $histPagina - 1 ?><?= $tipoActual ? '&tipo=' . urlencode($tipoActual) : '' ?>" class="btn btn-sm">&laquo; Anterior</a>
                <?php endif; ?>
                <span style="padding:6px 12px;">Página <?= $histPagina ?> de <?= $totalPagHist ?></span>
                <?php if ($histPagina < $totalPagHist): ?>
                    <a href="?hp=<?= $histPagina + 1 ?><?= $tipoActual ? '&tipo=' . urlencode($tipoActual) : '' ?>" class="btn btn-sm">Siguiente &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filtrarHistorial(val) {
    var q = val.toLowerCase().trim();
    document.querySelectorAll('.hist-row').forEach(function(r) {
        r.style.display = q === '' || r.querySelector('.hist-name').textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
    });
}
function validarRechazo(form, reqId) {
    var actionBtn = form.querySelector('button[value="rechazar"]');
    if (actionBtn === document.activeElement || actionBtn === event.submitter) {
        var coment = form.querySelector('input[name="comentarios"]');
        if (coment && coment.value.trim() === '') {
            alert('Debes proporcionar un motivo al rechazar la solicitud.');
            coment.focus();
            return false;
        }
        if (!confirm('¿Rechazar esta solicitud?')) return false;
    }
    return true;
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form[action=""][method="POST"]').forEach(function(f) {
        f.addEventListener('submit', function() {
            var btns = this.querySelectorAll('.btn');
            btns.forEach(function(b) {
                b.disabled = true;
                b.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid transparent;border-top-color:currentColor;border-radius:50%;animation:spinkf 0.6s linear infinite;vertical-align:middle;margin-right:6px;"></span>' + b.textContent.trim();
            });
        });
    });
    var empInput = document.getElementById('filterEmpName');
    var empHidden = document.getElementById('filterEmpId');
    if (empInput && empHidden) {
        empInput.addEventListener('input', function() {
            var opts = document.querySelectorAll('#empList option');
            var found = false;
            for (var i = 0; i < opts.length; i++) {
                if (opts[i].value === this.value) {
                    empHidden.value = opts[i].dataset.id;
                    found = true;
                    break;
                }
            }
            if (!found) empHidden.value = '';
        });
    }
});
</script>
<style>
@keyframes spinkf { to { transform: rotate(360deg); } }
</style>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
