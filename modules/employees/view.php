<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('employees.read');

require_once __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('danger', 'Empleado no válido.');
    redirect(APP_URL . '/modules/employees/index.php');
}

$db = getDB();
$stmt = $db->prepare('SELECT e.*, u.username, u.email AS user_email FROM employees e LEFT JOIN users u ON u.id = e.user_id WHERE e.id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$emp = $stmt->fetch();

if (!$emp) {
    setFlash('danger', 'Empleado no encontrado.');
    redirect(APP_URL . '/modules/employees/index.php');
}

$edad = $emp['fecha_nacimiento'] ? calculateAge($emp['fecha_nacimiento']) : null;
$antiguedad = $emp['fecha_ingreso'] ? calculateSeniority($emp['fecha_ingreso']) : null;

$totalDocs = $db->prepare("SELECT COUNT(*) FROM employee_documents WHERE employee_id = :id");
$totalDocs->execute([':id' => $id]);
$totalDocs = (int)$totalDocs->fetchColumn();

$docs = $db->prepare("SELECT id, tipo_documento, nombre_original, created_at, hash_firma FROM employee_documents WHERE employee_id = :id ORDER BY created_at DESC LIMIT 5");
$docs->execute([':id' => $id]);
$recentDocs = $docs->fetchAll();

// Calcular saldo vacacional según LFT
$diasDisfrutados = 0;
$stmtBal = $db->prepare("SELECT dias_disfrutados FROM leave_balance WHERE employee_id = :id AND periodo = YEAR(CURDATE()) LIMIT 1");
$stmtBal->execute([':id' => $id]);
$balRow = $stmtBal->fetch();
if ($balRow) $diasDisfrutados = (float)$balRow['dias_disfrutados'];

$stmtEmpF = $db->prepare("SELECT fecha_ingreso FROM employees WHERE id = :id LIMIT 1");
$stmtEmpF->execute([':id' => $id]);
$empF = $stmtEmpF->fetch();

$diasPorLey = 0;
if ($empF && $empF['fecha_ingreso']) {
    $antiguedad = (int)(new DateTime($empF['fecha_ingreso']))->diff(new DateTime())->y;
    $diasPorLey = calculateLFTHolidays($antiguedad);
}
$saldoVacacional = max(0, $diasPorLey - $diasDisfrutados);

$leaveHistory = $db->prepare("SELECT tipo, dias_solicitados, estatus, fecha_inicio, fecha_fin FROM leave_requests WHERE employee_id = :id ORDER BY created_at DESC LIMIT 5");
$leaveHistory->execute([':id' => $id]);
$recentLeave = $leaveHistory->fetchAll();

$payrollHistory = $db->prepare("
    SELECT pp.periodo, pi.sueldo_neto, pi.sueldo_bruto, pi.isr_retener, pi.dias_trabajados
    FROM payroll_items pi
    INNER JOIN payroll_periods pp ON pp.id = pi.period_id
    WHERE pi.employee_id = :id
    ORDER BY pp.periodo DESC LIMIT 6
");
$payrollHistory->execute([':id' => $id]);
$recentPayroll = $payrollHistory->fetchAll();

$trainingHistory = $db->prepare("
    SELECT th.*, tc.nombre AS curso_nombre, tc.tipo AS curso_tipo
    FROM training_history th
    INNER JOIN training_courses tc ON tc.id = th.course_id
    WHERE th.employee_id = :id
    ORDER BY th.created_at DESC LIMIT 5
");
$trainingHistory->execute([':id' => $id]);
$recentTraining = $trainingHistory->fetchAll();

$asistencia = $db->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN hora_entrada IS NULL THEN 1 ELSE 0 END) AS faltas,
        SUM(CASE WHEN hora_entrada IS NOT NULL AND HOUR(hora_entrada) >= 9 AND MINUTE(hora_entrada) > 5 THEN 1 ELSE 0 END) AS retardos
    FROM attendance_logs
    WHERE employee_id = :id AND fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$asistencia->execute([':id' => $id]);
$attendance = $asistencia->fetch();

$emergencyContacts = $db->prepare("SELECT id, nombre_completo, parentesco, telefono, telefono_alternativo, email, es_principal FROM emergency_contacts WHERE employee_id = :id ORDER BY es_principal DESC, created_at ASC");
$emergencyContacts->execute([':id' => $id]);
$contacts = $emergencyContacts->fetchAll();

$salaryHistory = $db->prepare("
    SELECT sh.*, u.username AS modificado_por_name
    FROM salary_history sh
    LEFT JOIN users u ON u.id = sh.modificado_por
    WHERE sh.employee_id = :id
    ORDER BY sh.created_at DESC LIMIT 10
");
$salaryHistory->execute([':id' => $id]);
$salaryHistoryRows = $salaryHistory->fetchAll();

$contractHistory = $db->prepare("
    SELECT ch.*, u.username AS modificado_por_name
    FROM contract_history ch
    LEFT JOIN users u ON u.id = ch.modificado_por
    WHERE ch.employee_id = :id
    ORDER BY ch.created_at DESC LIMIT 10
");
$contractHistory->execute([':id' => $id]);
$contractHistoryRows = $contractHistory->fetchAll();

$tipoLabels = [
    'vacaciones'        => 'Vacaciones',
    'permiso_con_goce'  => 'Permiso c/goce',
    'permiso_sin_goce'  => 'Permiso s/goce',
    'incapacidad'       => 'Incapacidad',
];
?>

<div class="page-header">
    <h2>Perfil de empleado</h2>
    <div class="header-actions">
        <?php if (can('employees.update')): ?>
            <a href="<?= APP_URL ?>/modules/employees/edit.php?id=<?= $id ?>" class="btn btn-primary">Editar</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/modules/employees/index.php" class="btn btn-link">&larr; Volver</a>
    </div>
</div>

<div class="profile-grid">
    <!-- Foto + datos principales -->
    <div class="card profile-header-card" style="display:flex;align-items:center;gap:20px;">
        <div style="flex-shrink:0;">
            <?php if ($emp['foto_url']): ?>
                <img src="<?= APP_URL ?>/<?= $emp['foto_url'] ?>" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--color-primary);">
            <?php else: ?>
                <div style="width:100px;height:100px;border-radius:50%;background:var(--color-surface-alt);display:flex;align-items:center;justify-content:center;border:3px solid var(--color-border);">
                    <span style="font-size:2.5rem;color:#999;"><?= h(strtoupper(substr($emp['nombre'], 0, 1))) ?></span>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <h3 style="margin:0 0 4px;"><?= h($emp['nombre'] . ' ' . $emp['apellido_paterno'] . ($emp['apellido_materno'] ? ' ' . $emp['apellido_materno'] : '')) ?></h3>
            <p style="margin:0;color:var(--color-text-secondary);">
                <?= h($emp['puesto'] ?? '—') ?>
                <?php if ($emp['departamento']): ?> &middot; <?= h($emp['departamento']) ?><?php endif; ?>
            </p>
            <p style="margin:4px 0 0;font-size:0.85rem;color:var(--color-text-secondary);">
                <?php if ($emp['fecha_ingreso']): ?>Ingreso: <?= formatDate($emp['fecha_ingreso']) ?> &middot; Antigüedad: <?= h($antiguedad ?? '—') ?><?php endif; ?>
                <?php if ($emp['fecha_nacimiento']): ?> &middot; Edad: <?= $edad ?> años<?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Datos personales -->
    <div class="card">
        <h3 class="card-title">Datos personales</h3>
        <dl class="data-list">
            <dt>CURP</dt>
            <dd><code><?= h($emp['curp']) ?></code></dd>
            <dt>RFC</dt>
            <dd><code><?= h($emp['rfc']) ?></code></dd>
            <dt>NSS</dt>
            <dd><code><?= h($emp['nss']) ?></code></dd>
            <?php if ($emp['genero']): ?>
                <dt>Género</dt>
                <dd><?= ['M' => 'Masculino', 'F' => 'Femenino', 'Otro' => 'Otro'][$emp['genero']] ?? $emp['genero'] ?></dd>
            <?php endif; ?>
        </dl>
    </div>

    <!-- Contacto y domicilio -->
    <div class="card">
        <h3 class="card-title">Contacto y domicilio</h3>
        <dl class="data-list">
            <?php if ($emp['email']): ?><dt>Correo</dt><dd><a href="mailto:<?= h($emp['email']) ?>"><?= h($emp['email']) ?></a></dd><?php endif; ?>
            <?php if ($emp['telefono']): ?><dt>Teléfono</dt><dd><?= h($emp['telefono']) ?></dd><?php endif; ?>
            <?php if ($emp['calle']): ?>
                <dt>Dirección</dt>
                <dd>
                    <?= h($emp['calle'] . ' ' . $emp['numero_exterior'] . ($emp['numero_interior'] ? ' Int. ' . $emp['numero_interior'] : '')) ?><br>
                    <?= h($emp['colonia'] ? $emp['colonia'] . ', ' : '') . h($emp['ciudad'] ? $emp['ciudad'] . ', ' : '') . h($emp['estado'] ?? '') ?>
                    <?= $emp['codigo_postal'] ? ' C.P. ' . h($emp['codigo_postal']) : '' ?>
                </dd>
            <?php endif; ?>
        </dl>
    </div>

    <!-- Relación laboral -->
    <div class="card">
        <h3 class="card-title">Relación laboral</h3>
        <dl class="data-list">
            <dt>Tipo de contrato</dt>
            <dd><span class="badge badge-info"><?= h($emp['tipo_contrato'] ?? '—') ?></span></dd>
            <?php if ($emp['salario_base'] !== null): ?>
                <dt>Salario base mensual</dt>
                <dd style="font-size:1.2rem;font-weight:700;color:var(--color-primary);"><?= formatCurrency((float)$emp['salario_base']) ?></dd>
                <dt>Salario diario</dt>
                <dd><?= formatCurrency((float)$emp['salario_base'] / 30) ?></dd>
            <?php endif; ?>
            <?php if ($emp['username']): ?>
                <dt>Usuario del sistema</dt>
                <dd><?= h($emp['username']) ?> <span class="badge badge-secondary"><?= h($emp['user_email'] ?? '') ?></span></dd>
            <?php endif; ?>
        </dl>
    </div>

    <!-- Asistencia reciente -->
    <?php if (can('attendance.read') && $attendance): ?>
    <div class="card">
        <h3 class="card-title">Asistencia (últimos 30 días)</h3>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:80px;text-align:center;padding:12px;background:var(--color-surface-alt);border-radius:var(--radius);">
                <div style="font-size:1.5rem;font-weight:700;"><?= (int)$attendance['total'] - (int)$attendance['faltas'] ?></div>
                <div style="font-size:0.8rem;color:var(--color-text-secondary);">Asistencias</div>
            </div>
            <div style="flex:1;min-width:80px;text-align:center;padding:12px;background:var(--color-surface-alt);border-radius:var(--radius);">
                <div style="font-size:1.5rem;font-weight:700;color:var(--color-danger);"><?= (int)$attendance['faltas'] ?></div>
                <div style="font-size:0.8rem;color:var(--color-text-secondary);">Faltas</div>
            </div>
            <div style="flex:1;min-width:80px;text-align:center;padding:12px;background:var(--color-surface-alt);border-radius:var(--radius);">
                <div style="font-size:1.5rem;font-weight:700;color:var(--color-warning);"><?= (int)$attendance['retardos'] ?></div>
                <div style="font-size:0.8rem;color:var(--color-text-secondary);">Retardos</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Documentos -->
    <div class="card">
        <h3 class="card-title">
            Documentos
            <span class="badge badge-info"><?= $totalDocs ?></span>
        </h3>
        <?php if (count($recentDocs) === 0): ?>
            <p class="text-secondary empty-state">Sin documentos registrados.</p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <?php foreach ($recentDocs as $d): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--color-border);">
                        <div>
                            <span class="badge badge-info" style="font-size:0.75rem;"><?= h($d['tipo_documento']) ?></span>
                            <?php if ($d['hash_firma']): ?><span class="badge badge-success" style="font-size:0.7rem;">Firmado</span><?php endif; ?>
                            <span style="font-size:0.85rem;"><?= h($d['nombre_original']) ?></span>
                        </div>
                        <a href="#" onclick="return downloadDoc(<?= (int)$d['id'] ?>)" class="btn btn-sm btn-ghost">Descargar</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($totalDocs > 5): ?>
                <a href="<?= APP_URL ?>/modules/documents/index.php?employee_id=<?= $id ?>" class="btn btn-link" style="margin-top:8px;">Ver todos (<?= $totalDocs ?>)</a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (can('documents.upload')): ?>
            <a href="<?= APP_URL ?>/modules/documents/upload.php?employee_id=<?= $id ?>" class="btn btn-sm btn-primary" style="margin-top:8px;">+ Subir documento</a>
        <?php endif; ?>
    </div>

    <!-- Vacaciones y ausencias -->
    <div class="card">
        <h3 class="card-title">Vacaciones y ausencias</h3>
        <div style="display:flex;gap:12px;margin-bottom:12px;">
            <div style="flex:1;text-align:center;padding:12px;background:var(--color-surface-alt);border-radius:var(--radius);">
                <div style="font-size:1.5rem;font-weight:700;color:var(--color-success);"><?= $saldoVacacional ?></div>
                <div style="font-size:0.8rem;color:var(--color-text-secondary);">Días disponibles</div>
            </div>
        </div>
        <?php if (count($recentLeave) > 0): ?>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <?php foreach ($recentLeave as $l): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--color-border);font-size:0.85rem;">
                        <div>
                            <span class="badge badge-<?= $l['estatus'] === 'aprobado' ? 'success' : ($l['estatus'] === 'pendiente' ? 'warning' : 'danger') ?>"><?= ucfirst($l['estatus']) ?></span>
                            <?= $tipoLabels[$l['tipo']] ?? h($l['tipo']) ?> (<?= (int)$l['dias_solicitados'] ?> días)
                        </div>
                        <span style="color:var(--color-text-secondary);"><?= formatDate($l['fecha_inicio']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (can('leave.request')): ?>
            <a href="<?= APP_URL ?>/modules/leave/requests.php?employee_id=<?= $id ?>" class="btn btn-link" style="margin-top:8px;">Ver historial</a>
        <?php endif; ?>
    </div>

    <!-- Capacitación -->
    <div class="card">
        <h3 class="card-title">Capacitación</h3>
        <?php if (count($recentTraining) === 0): ?>
            <p class="text-secondary empty-state">Sin cursos registrados.</p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <?php foreach ($recentTraining as $t): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--color-border);font-size:0.85rem;">
                        <div>
                            <span class="badge badge-info"><?= h($t['curso_tipo'] ?? 'Curso') ?></span>
                            <?= h($t['curso_nombre']) ?>
                        </div>
                        <span style="color:var(--color-text-secondary);"><?= formatDate($t['created_at']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (can('performance.read')): ?>
            <a href="<?= APP_URL ?>/modules/performance/training.php?tab=history&employee_id=<?= $id ?>" class="btn btn-link" style="margin-top:8px;">Ver historial completo</a>
        <?php endif; ?>
    </div>

    <!-- Contactos de emergencia -->
    <div class="card">
        <h3 class="card-title">Contactos de emergencia</h3>
        <?php if (count($contacts) === 0): ?>
            <p class="text-secondary empty-state">Sin contactos registrados.</p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($contacts as $c): ?>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:10px;background:var(--color-surface-alt);border-radius:var(--radius);<?= $c['es_principal'] ? 'border-left:3px solid var(--color-primary);' : '' ?>">
                        <div>
                            <div style="font-weight:600;"><?= h($c['nombre_completo']) ?>
                                <?php if ($c['es_principal']): ?><span class="badge badge-primary" style="font-size:0.7rem;">Principal</span><?php endif; ?>
                            </div>
                            <div style="font-size:0.85rem;color:var(--color-text-secondary);"><?= h($c['parentesco']) ?></div>
                            <div style="font-size:0.85rem;">📞 <?= h($c['telefono']) ?>
                                <?php if ($c['telefono_alternativo']): ?> | <?= h($c['telefono_alternativo']) ?><?php endif; ?>
                            </div>
                            <?php if ($c['email']): ?>
                                <div style="font-size:0.85rem;">✉ <?= h($c['email']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Historial salarial -->
    <div class="card">
        <h3 class="card-title">Historial salarial</h3>
        <?php if (count($salaryHistoryRows) === 0): ?>
            <p class="text-secondary empty-state">Sin cambios salariales registrados.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="font-size:0.8rem;">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Anterior</th>
                            <th>Nuevo</th>
                            <th>Cambio</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Modificado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salaryHistoryRows as $sh): ?>
                            <?php
                            $diferencia = $sh['salario_anterior'] ? (float)$sh['salario_nuevo'] - (float)$sh['salario_anterior'] : (float)$sh['salario_nuevo'];
                            $claseCambio = $diferencia >= 0 ? 'color:var(--color-success);' : 'color:var(--color-danger);';
                            ?>
                            <tr>
                                <td><?= formatDate($sh['created_at']) ?></td>
                                <td><?= $sh['salario_anterior'] ? formatCurrency((float)$sh['salario_anterior']) : '—' ?></td>
                                <td><strong><?= formatCurrency((float)$sh['salario_nuevo']) ?></strong></td>
                                <td style="<?= $claseCambio ?>"><?= ($diferencia >= 0 ? '+' : '') . formatCurrency($diferencia) ?></td>
                                <td><span class="badge badge-<?= $sh['tipo_cambio'] === 'incremento' ? 'success' : ($sh['tipo_cambio'] === 'decremento' ? 'danger' : 'info') ?>"><?= ucfirst($sh['tipo_cambio']) ?></span></td>
                                <td><?= h($sh['motivo'] ?? '—') ?></td>
                                <td><?= h($sh['modificado_por_name'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Historial de contratos -->
    <div class="card">
        <h3 class="card-title">Historial de contratos</h3>
        <?php if (count($contractHistoryRows) === 0): ?>
            <p class="text-secondary empty-state">Sin cambios de contrato registrados.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="font-size:0.8rem;">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Anterior</th>
                            <th>Nuevo</th>
                            <th>Vigencia</th>
                            <th>Motivo</th>
                            <th>Modificado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contractHistoryRows as $ch): ?>
                            <tr>
                                <td><?= formatDate($ch['created_at']) ?></td>
                                <td><?= h($ch['tipo_contrato_anterior'] ?? '—') ?></td>
                                <td><span class="badge badge-info"><?= h($ch['tipo_contrato_nuevo']) ?></span></td>
                                <td><?= $ch['fecha_inicio'] ? formatDate($ch['fecha_inicio']) : '—' ?> <?= $ch['fecha_fin'] ? '→ ' . formatDate($ch['fecha_fin']) : ($ch['tipo_contrato_nuevo'] !== 'Temporal' ? '(Indefinido)' : '') ?></td>
                                <td><?= h($ch['motivo'] ?? '—') ?></td>
                                <td><?= h($ch['modificado_por_name'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Nómina reciente -->
    <?php if (can('payroll.read') && count($recentPayroll) > 0): ?>
    <div class="card">
        <h3 class="card-title">Nómina reciente</h3>
        <div class="table-responsive">
            <table class="table" style="font-size:0.8rem;">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Días</th>
                        <th>Bruto</th>
                        <th>ISR</th>
                        <th>Neto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPayroll as $p): ?>
                        <tr>
                            <td><?= h($p['periodo']) ?></td>
                            <td><?= (int)$p['dias_trabajados'] ?></td>
                            <td class="text-right"><?= formatCurrency((float)$p['sueldo_bruto']) ?></td>
                            <td class="text-right" style="color:var(--color-danger);">—<?= formatCurrency((float)$p['isr_retener']) ?></td>
                            <td class="text-right"><strong><?= formatCurrency((float)$p['sueldo_neto']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
