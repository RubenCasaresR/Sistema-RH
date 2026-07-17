<?php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/tax_calculator.php';
requireAuth();
requirePermission('payroll.calculate');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$errors = [];
$periodId = (int)($_GET['period_id'] ?? 0);

// --- CRUD Bonos ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bonus') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }
    $empId = (int)($_POST['employee_id'] ?? 0);
    $concepto = trim($_POST['concepto'] ?? '');
    $monto = (float)($_POST['monto'] ?? 0);
    if ($empId <= 0) $errors[] = 'Selecciona un empleado.';
    if ($concepto === '') $errors[] = 'Concepto requerido.';
    if ($monto <= 0) $errors[] = 'Monto debe ser mayor a 0.';
    if (count($errors) === 0) {
        $stmt = $db->prepare("INSERT INTO payroll_bonus (period_id, employee_id, concepto, monto) VALUES (:pid, :eid, :concepto, :monto)");
        $stmt->execute([':pid' => $periodId, ':eid' => $empId, ':concepto' => $concepto, ':monto' => $monto]);
        setFlash('success', 'Bono agregado.');
        redirect(APP_URL . '/modules/payroll/calculate.php?period_id=' . $periodId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_bonus') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }
    $bonusId = (int)($_POST['id'] ?? 0);
    $db->prepare("DELETE FROM payroll_bonus WHERE id = :id AND period_id = :pid")
       ->execute([':id' => $bonusId, ':pid' => $periodId]);
    redirect(APP_URL . '/modules/payroll/calculate.php?period_id=' . $periodId);
}

// --- CRUD Ajustes manuales ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_adjustment') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }
    $empId = (int)($_POST['employee_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? '';
    $concepto = trim($_POST['concepto'] ?? '');
    $monto = (float)($_POST['monto'] ?? 0);
    $tiposValidos = ['percepcion', 'deduccion', 'falta', 'retardo', 'hora_extra'];
    if ($empId <= 0) $errors[] = 'Selecciona un empleado.';
    if (!in_array($tipo, $tiposValidos)) $errors[] = 'Tipo inválido.';
    if ($concepto === '') $errors[] = 'Concepto requerido.';
    if ($monto <= 0) $errors[] = 'Monto debe ser mayor a 0.';
    if (count($errors) === 0) {
        $stmt = $db->prepare("INSERT INTO payroll_adjustments (period_id, employee_id, tipo, concepto, monto) VALUES (:pid, :eid, :tipo, :concepto, :monto)");
        $stmt->execute([':pid' => $periodId, ':eid' => $empId, ':tipo' => $tipo, ':concepto' => $concepto, ':monto' => $monto]);
        setFlash('success', 'Incidencia agregada.');
        redirect(APP_URL . '/modules/payroll/calculate.php?period_id=' . $periodId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_adjustment') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }
    $adjId = (int)($_POST['id'] ?? 0);
    $db->prepare("DELETE FROM payroll_adjustments WHERE id = :id AND period_id = :pid")
       ->execute([':id' => $adjId, ':pid' => $periodId]);
    redirect(APP_URL . '/modules/payroll/calculate.php?period_id=' . $periodId);
}

$stmtP = $db->prepare("SELECT * FROM payroll_periods WHERE id = :id LIMIT 1");
$stmtP->execute([':id' => $periodId]);
$period = $stmtP->fetch();

if (!$period) {
    setFlash('danger', 'Período no encontrado.');
    redirect(APP_URL . '/modules/payroll/index.php');
}

$resultado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calculate') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }

    try {
        $db->beginTransaction();

        $db->prepare("DELETE FROM payroll_items WHERE period_id = :pid")->execute([':pid' => $periodId]);

        $emps = $db->query("SELECT id, salario_base, fecha_ingreso FROM employees WHERE activo = 1 AND salario_base IS NOT NULL AND salario_base > 0")->fetchAll();

        $insertStmt = $db->prepare("
            INSERT INTO payroll_items
                (period_id, employee_id, salario_base, salario_diario, total_bonos, total_deducciones, total_incidencias,
                 sueldo_bruto, sueldo_neto, dias_trabajados, faltas, retardos, descuento_retardos, horas_extras,
                 isr_retener, imss_obrero, subsidio_empleo, aguinaldo_proporcional, prima_vacacional,
                 percepciones_total, deducciones_total)
            VALUES
                (:pid, :eid, :sb, :sd, :bonos, :td, :ti,
                 :bruto, :neto, :dt, :faltas, :retardos, :dr, :he,
                 :isr, :imss, :subsidio, :aguinaldo, :prima,
                 :perc, :ded)
        ");

        $totalNeto = 0;
        $totalISR = 0;
        $totalIMSS = 0;
        $totalAguinaldo = 0;
        $totalPrima = 0;
        $empleadosProcesados = 0;
        $diasDelPeriodo = (new DateTime($period['fecha_fin']))->diff(new DateTime($period['fecha_inicio']))->days + 1;
        $tipoPeriodo = $period['tipo_periodo'] ?? 'mensual';

        foreach ($emps as $emp) {
            $fechaIngreso = $emp['fecha_ingreso'] ?? $period['fecha_inicio'];
            $calc = calculatePayrollForEmployee(
                $emp,
                $fechaIngreso,
                $period['fecha_inicio'],
                $period['fecha_fin'],
                $diasDelPeriodo,
                $periodId,
                $tipoPeriodo
            );

            $insertStmt->execute([
                ':pid'       => $periodId,
                ':eid'       => (int)$emp['id'],
                ':sb'        => $calc['salario_base'],
                ':sd'        => $calc['salario_diario'],
                ':bonos'     => $calc['total_bonos'],
                ':td'        => $calc['total_deducciones'],
                ':ti'        => $calc['total_incidencias'],
                ':bruto'     => $calc['sueldo_bruto'],
                ':neto'      => $calc['sueldo_neto'],
                ':dt'        => $calc['dias_trabajados'],
                ':faltas'    => $calc['faltas'],
                ':retardos'  => $calc['retardos'],
                ':dr'        => $calc['descuento_retardos'],
                ':he'        => $calc['horas_extras'],
                ':isr'       => $calc['isr_retener'],
                ':imss'      => $calc['imss_obrero'],
                ':subsidio'  => $calc['subsidio_empleo'],
                ':aguinaldo' => $calc['aguinaldo_proporcional'],
                ':prima'     => $calc['prima_vacacional'],
                ':perc'      => $calc['percepciones_total'],
                ':ded'       => $calc['total_deducciones'],
            ]);

            $totalNeto += $calc['sueldo_neto'];
            $totalISR += $calc['isr_retener'];
            $totalIMSS += $calc['imss_obrero'];
            $totalAguinaldo += $calc['aguinaldo_proporcional'];
            $totalPrima += $calc['prima_vacacional'];
            $empleadosProcesados++;
        }

        $db->prepare("UPDATE payroll_periods SET estatus = 'calculado' WHERE id = :id")->execute([':id' => $periodId]);
        $db->commit();

        logAudit('calculate', 'payroll', $periodId, json_encode([
            'periodo'  => $period['periodo'],
            'empleados' => $empleadosProcesados,
            'total_neto' => $totalNeto,
            'total_isr' => $totalISR,
            'total_imss' => $totalIMSS,
        ]));

        $resultado = [
            'empleados' => $empleadosProcesados,
            'total_neto' => $totalNeto,
            'total_isr' => $totalISR,
            'total_imss' => $totalIMSS,
            'total_aguinaldo' => $totalAguinaldo,
            'total_prima' => $totalPrima,
        ];

        setFlash('success', "Nómina calculada: $empleadosProcesados empleados, total neto \$" . number_format($totalNeto, 2));
        redirect(APP_URL . '/modules/payroll/calculate.php?period_id=' . $periodId);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Error cálculo nómina: ' . $e->getMessage());
        $errors[] = 'Error al calcular nómina. Consulta el registro del sistema.';
    }
}

$stmtI = $db->prepare("
    SELECT pi.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.puesto, e.departamento
    FROM payroll_items pi
    INNER JOIN employees e ON e.id = pi.employee_id
    WHERE pi.period_id = :pid
    ORDER BY e.apellido_paterno
");
$stmtI->execute([':pid' => $periodId]);
$items = $stmtI->fetchAll();

$employees = $db->query("SELECT id, nombre, apellido_paterno FROM employees WHERE activo = 1 ORDER BY apellido_paterno")->fetchAll();

$bonusList = $db->prepare("SELECT b.*, e.nombre, e.apellido_paterno FROM payroll_bonus b INNER JOIN employees e ON e.id = b.employee_id WHERE b.period_id = :pid ORDER BY e.apellido_paterno");
$bonusList->execute([':pid' => $periodId]);
$bonusList = $bonusList->fetchAll();

$adjList = $db->prepare("SELECT a.*, e.nombre, e.apellido_paterno FROM payroll_adjustments a INNER JOIN employees e ON e.id = a.employee_id WHERE a.period_id = :pid ORDER BY e.apellido_paterno");
$adjList->execute([':pid' => $periodId]);
$adjList = $adjList->fetchAll();

$totales = [
    'empleados' => count($items),
    'salarios'  => array_sum(array_column($items, 'salario_base')),
    'bruto'     => array_sum(array_column($items, 'sueldo_bruto')),
    'neto'      => array_sum(array_column($items, 'sueldo_neto')),
    'isr'       => array_sum(array_column($items, 'isr_retener')),
    'imss'      => array_sum(array_column($items, 'imss_obrero')),
    'subsidio'  => array_sum(array_column($items, 'subsidio_empleo')),
    'aguinaldo' => array_sum(array_column($items, 'aguinaldo_proporcional')),
    'prima'     => array_sum(array_column($items, 'prima_vacacional')),
    'faltas'    => array_sum(array_column($items, 'faltas')),
    'retardos'  => array_sum(array_column($items, 'retardos')),
    'he'        => array_sum(array_column($items, 'horas_extras')),
    'bonos'     => array_sum(array_column($items, 'total_bonos')),
    'deducciones' => array_sum(array_column($items, 'deducciones_total')),
    'percepciones' => array_sum(array_column($items, 'percepciones_total')),
];

$csrfToken = generateCSRFToken();
?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/payroll.css?v=<?= APP_VERSION ?>">

<div class="page-header">
    <h2>Nómina: <?= h($period['periodo']) ?></h2>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/modules/payroll/index.php" class="btn btn-link">&larr; Períodos</a>
        <?php if (can('payroll.export') && count($items) > 0): ?>
            <a href="<?= APP_URL ?>/modules/payroll/export.php?period_id=<?= $periodId ?>" class="btn btn-secondary">Exportar CSV</a>
        <?php endif; ?>
        <?php if ($period['estatus'] === 'abierto' || $period['estatus'] === 'calculado'): ?>
            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('¿Calcular nómina? Se sobrescribirán los datos existentes para este período.')">
                <input type="hidden" name="action" value="calculate">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <button type="submit" class="btn btn-primary" id="btnCalcular">Calcular nómina</button>
            </form>
            <script>document.getElementById('btnCalcular')?.addEventListener('click', function(){ this.disabled=true; this.textContent='Calculando…'; this.form.submit(); });</script>
        <?php endif; ?>
    </div>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($totales['empleados'] > 0): ?>
<div class="dashboard-grid" style="margin-bottom:20px;">
    <div class="kpi-card">
        <span class="kpi-label">Empleados</span>
        <span class="kpi-value"><?= $totales['empleados'] ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Percepciones totales</span>
        <span class="kpi-value"><?= formatCurrency($totales['percepciones']) ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Deducciones totales</span>
        <span class="kpi-value" style="color:var(--color-danger);"><?= formatCurrency($totales['deducciones']) ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Total neto</span>
        <span class="kpi-value" style="color:var(--color-success);"><?= formatCurrency($totales['neto']) ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">ISR</span>
        <span class="kpi-value"><?= formatCurrency($totales['isr']) ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">IMSS obrero</span>
        <span class="kpi-value"><?= formatCurrency($totales['imss']) ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Aguinaldo prop.</span>
        <span class="kpi-value"><?= formatCurrency($totales['aguinaldo']) ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Prima vacacional</span>
        <span class="kpi-value"><?= formatCurrency($totales['prima']) ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Subsidio empleo</span>
        <span class="kpi-value" style="color:var(--color-success);"><?= formatCurrency($totales['subsidio']) ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Faltas totales</span>
        <span class="kpi-value" style="color:var(--color-danger);"><?= (int)$totales['faltas'] ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Horas extra</span>
        <span class="kpi-value" style="color:var(--color-success);"><?= (int)$totales['he'] ?>h</span>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-payroll">
            <thead>
                <tr>
                    <th rowspan="2">Empleado</th>
                    <th rowspan="2">Puesto</th>
                    <th rowspan="2">Salario diario</th>
                    <th rowspan="2">Días</th>
                    <th rowspan="2">Faltas</th>
                    <th colspan="5">Percepciones</th>
                    <th colspan="3">Deducciones</th>
                    <th rowspan="2">Sueldo neto</th>
                    <th rowspan="2">Recibo</th>
                </tr>
                <tr>
                    <th>Salario</th>
                    <th>Hrs extra</th>
                    <th>Bonos</th>
                    <th>Aguinaldo</th>
                    <th>Prima vac.</th>
                    <th>ISR</th>
                    <th>IMSS</th>
                    <th>Retardos</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) === 0): ?>
                    <tr><td colspan="15" class="empty-state">
                        Sin datos. <?= $period['estatus'] === 'abierto' ? 'Presiona "Calcular nómina" para procesar.' : '' ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($items as $idx => $i): ?>
                        <?php
                        $sd = (float)$i['salario_diario'];
                        $he = (int)$i['horas_extras'];
                        $hd = min(9, $he);
                        $ht = max(0, $he - 9);
                        $horasExtraPay = $hd * ($sd / 8) * 2 + $ht * ($sd / 8) * 3;
                        ?>
                        <tr>
                            <td><strong><?= h($i['nombre'] . ' ' . $i['apellido_paterno']) ?></strong></td>
                            <td><?= h($i['puesto'] ?? '—') ?></td>
                            <td class="text-right"><?= formatCurrency($sd) ?></td>
                            <td class="text-center"><?= (int)$i['dias_trabajados'] ?> / <?= (int)$i['dias_trabajados'] + (int)$i['faltas'] ?></td>
                            <td class="text-center">
                                <span class="badge badge-<?= $i['faltas'] > 0 ? 'danger' : 'success' ?>"><?= (int)$i['faltas'] ?></span>
                            </td>
                            <td class="text-right"><?= formatCurrency($sd * (int)$i['dias_trabajados']) ?></td>
                            <td class="text-right" title="<?= $hd ?>h dobles + <?= $ht ?>h triples"><?= $he > 0 ? formatCurrency($horasExtraPay) : '—' ?></td>
                            <td class="text-right"><?= (float)$i['total_bonos'] > 0 ? formatCurrency((float)$i['total_bonos']) : '—' ?></td>
                            <td class="text-right"><?= (float)$i['aguinaldo_proporcional'] > 0 ? formatCurrency((float)$i['aguinaldo_proporcional']) : '—' ?></td>
                            <td class="text-right"><?= (float)$i['prima_vacacional'] > 0 ? formatCurrency((float)$i['prima_vacacional']) : '—' ?></td>
                            <td class="text-right" title="ISR bruto: <?= formatCurrency((float)$i['isr_retener'] + (float)$i['subsidio_empleo']) ?>, Subsidio: <?= formatCurrency((float)$i['subsidio_empleo']) ?>"><?= formatCurrency((float)$i['isr_retener']) ?></td>
                            <td class="text-right"><?= formatCurrency((float)$i['imss_obrero']) ?></td>
                            <td class="text-center">
                                <span class="badge badge-<?= (int)$i['retardos'] > 0 ? 'warning' : 'success' ?>"><?= (int)$i['retardos'] ?></span>
                                <?php if ((float)$i['descuento_retardos'] > 0): ?><br><small style="color:var(--color-danger);"><?= formatCurrency((float)$i['descuento_retardos']) ?></small><?php endif; ?>
                            </td>
                            <td class="text-right"><strong><?= formatCurrency((float)$i['sueldo_neto']) ?></strong></td>
                            <td><button type="button" class="btn btn-sm btn-ghost" onclick="openRecibo(<?= $idx ?>)">Recibo</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if ($totales['empleados'] > 0): ?>
            <tfoot>
                <tr>
                    <th colspan="5">Totales</th>
                    <th class="text-center"><?= (int)$totales['faltas'] ?></th>
                    <th class="text-right"><?= formatCurrency(array_sum(array_map(function($i) { return (float)$i['salario_base'] / 30 * (int)$i['dias_trabajados']; }, $items))) ?></th>
                    <th class="text-right"><?= formatCurrency(array_sum(array_map(function($i) { $sd = (float)$i['salario_diario']; $he = (int)$i['horas_extras']; $hd = min(9, $he); $ht = max(0, $he - 9); return $hd * ($sd / 8) * 2 + $ht * ($sd / 8) * 3; }, $items))) ?></th>
                    <th class="text-right"><?= formatCurrency(array_sum(array_column($items, 'total_bonos'))) ?></th>
                    <th class="text-right"><?= formatCurrency($totales['aguinaldo']) ?></th>
                    <th class="text-right"><?= formatCurrency($totales['prima']) ?></th>
                    <th class="text-right"><?= formatCurrency($totales['isr']) ?></th>
                    <th class="text-right"><?= formatCurrency($totales['imss']) ?></th>
                    <th class="text-center"><?= (int)$totales['retardos'] ?></th>
                    <th class="text-right"><strong><?= formatCurrency($totales['neto']) ?></strong></th>
                    <th></th>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Gestión de Bonos -->
<div class="card">
    <h3 class="card-title">Bonos por empleado</h3>
    <?php if ($period['estatus'] === 'abierto' || $period['estatus'] === 'calculado'): ?>
    <form method="POST" action="" class="form-row" style="margin-bottom:16px;">
        <input type="hidden" name="action" value="add_bonus">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="form-group">
            <select name="employee_id" required>
                <option value="">Empleado…</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= h($emp['nombre'] . ' ' . $emp['apellido_paterno']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <input type="text" name="concepto" placeholder="Concepto (ej. Bono puntualidad)" required>
        </div>
        <div class="form-group" style="min-width:120px;">
            <input type="number" name="monto" placeholder="Monto" step="0.01" min="0.01" required>
        </div>
        <div class="form-group" style="min-width:auto;">
            <button type="submit" class="btn btn-primary">Agregar bono</button>
        </div>
    </form>
    <?php endif; ?>
    <?php if (count($bonusList) > 0): ?>
    <div class="table-responsive">
        <table class="table" style="font-size:0.85rem;">
            <thead><tr><th>Empleado</th><th>Concepto</th><th>Monto</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($bonusList as $b): ?>
                <tr>
                    <td><?= h($b['nombre'] . ' ' . $b['apellido_paterno']) ?></td>
                    <td><?= h($b['concepto']) ?></td>
                    <td class="text-right"><?= formatCurrency((float)$b['monto']) ?></td>
                    <td class="actions-cell">
                        <form method="POST" action="" style="display:inline" onsubmit="return confirm('¿Eliminar este bono?')">
                            <input type="hidden" name="action" value="delete_bonus">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="empty-state">Sin bonos registrados.</p>
    <?php endif; ?>
</div>

<!-- Gestión de Incidencias Manuales -->
<div class="card">
    <h3 class="card-title">Incidencias manuales</h3>
    <p class="text-secondary" style="margin-bottom:12px;font-size:0.85rem;">Agrega percepciones, deducciones, faltas, retardos u horas extra adicionales por empleado.</p>
    <?php if ($period['estatus'] === 'abierto' || $period['estatus'] === 'calculado'): ?>
    <form method="POST" action="" class="form-row" style="margin-bottom:16px;">
        <input type="hidden" name="action" value="add_adjustment">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="form-group">
            <select name="employee_id" required>
                <option value="">Empleado…</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= h($emp['nombre'] . ' ' . $emp['apellido_paterno']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <select name="tipo" required>
                <option value="">Tipo…</option>
                <option value="percepcion">Percepción adicional</option>
                <option value="deduccion">Deducción adicional</option>
                <option value="falta">Falta</option>
                <option value="retardo">Retardo</option>
                <option value="hora_extra">Hora extra</option>
            </select>
        </div>
        <div class="form-group">
            <input type="text" name="concepto" placeholder="Concepto" required>
        </div>
        <div class="form-group" style="min-width:120px;">
            <input type="number" name="monto" placeholder="Monto / Cantidad" step="0.01" min="0.01" required>
        </div>
        <div class="form-group" style="min-width:auto;">
            <button type="submit" class="btn btn-primary">Agregar</button>
        </div>
    </form>
    <?php endif; ?>
    <?php if (count($adjList) > 0): ?>
    <div class="table-responsive">
        <table class="table" style="font-size:0.85rem;">
            <thead><tr><th>Empleado</th><th>Tipo</th><th>Concepto</th><th>Valor</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($adjList as $a): ?>
                <tr>
                    <td><?= h($a['nombre'] . ' ' . $a['apellido_paterno']) ?></td>
                    <td><span class="badge badge-info"><?= h($a['tipo']) ?></span></td>
                    <td><?= h($a['concepto']) ?></td>
                    <td class="text-right"><?= $a['tipo'] === 'falta' || $a['tipo'] === 'retardo' || $a['tipo'] === 'hora_extra' ? (int)$a['monto'] : formatCurrency((float)$a['monto']) ?></td>
                    <td class="actions-cell">
                        <form method="POST" action="" style="display:inline" onsubmit="return confirm('¿Eliminar esta incidencia?')">
                            <input type="hidden" name="action" value="delete_adjustment">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="empty-state">Sin incidencias registradas.</p>
    <?php endif; ?>
</div>

<?php if ($totales['empleados'] > 0): ?>
<!-- Desglose interactivo por empleado -->
<div class="card">
    <h3 class="card-title">Desglose por empleado</h3>
    <div class="form-group" style="max-width:300px;margin-bottom:16px;">
        <label for="selectEmpleado">Seleccionar empleado</label>
        <select id="selectEmpleado" onchange="mostrarDesglose(this.value)">
            <option value="">— Seleccionar —</option>
            <?php foreach ($items as $idx => $i): ?>
                <option value="<?= $idx ?>"><?= h($i['nombre'] . ' ' . $i['apellido_paterno']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div id="desgloseContainer"></div>
</div>

<script>
const itemsData = <?= json_encode($items) ?>;
function mostrarDesglose(idx) {
    const container = document.getElementById('desgloseContainer');
    if (idx === '') { container.innerHTML = '<p class="empty-state">Selecciona un empleado para ver su desglose.</p>'; return; }
    const i = itemsData[idx];
    const sd = parseFloat(i.salario_diario);
    const he = parseInt(i.horas_extras);
    const hd = Math.min(9, he);
    const ht = Math.max(0, he - 9);
    const hePay = hd * (sd / 8) * 2 + ht * (sd / 8) * 3;
    const descRetardos = parseFloat(i.descuento_retardos) || 0;
    const tieneBonos = parseFloat(i.total_bonos) > 0;
    container.innerHTML = `
    <div class="payroll-breakdown">
        <div class="breakdown-section">
            <h4>Percepciones</h4>
            <div class="breakdown-row"><span>Salario del período</span><span>${fmt(sd * i.dias_trabajados)}</span></div>
            ${he > 0 ? `<div class="breakdown-row"><span>Horas extra (${hd}h dobles + ${ht}h triples)</span><span>${fmt(hePay)}</span></div>` : ''}
            ${tieneBonos ? `<div class="breakdown-row"><span>Bonos</span><span>${fmt(i.total_bonos)}</span></div>` : ''}
            ${parseFloat(i.aguinaldo_proporcional) > 0 ? `<div class="breakdown-row"><span>Aguinaldo proporcional</span><span>${fmt(i.aguinaldo_proporcional)}</span></div>` : ''}
            ${parseFloat(i.prima_vacacional) > 0 ? `<div class="breakdown-row"><span>Prima vacacional</span><span>${fmt(i.prima_vacacional)}</span></div>` : ''}
            <div class="breakdown-row breakdown-total"><span>Total percepciones</span><span>${fmt(i.percepciones_total)}</span></div>
        </div>
        <div class="breakdown-section">
            <h4>Deducciones</h4>
            <div class="breakdown-row"><span>ISR (LISR Art. 96)</span><span>—${fmt(i.isr_retener)}</span></div>
            ${parseFloat(i.subsidio_empleo) > 0 ? `<div class="breakdown-row" style="color:var(--color-success);"><span>Subsidio al empleo</span><span>+${fmt(i.subsidio_empleo)}</span></div>` : ''}
            <div class="breakdown-row"><span>IMSS (cuota obrera)</span><span>—${fmt(i.imss_obrero)}</span></div>
            ${i.faltas > 0 ? `<div class="breakdown-row"><span>Faltas (${i.faltas} días no laborados)</span><span>—</span></div>` : ''}
            ${descRetardos > 0 ? `<div class="breakdown-row"><span>Retardos (${i.retardos})</span><span>—${fmt(descRetardos)}</span></div>` : ''}
            <div class="breakdown-row breakdown-total"><span>Total deducciones</span><span>—${fmt(i.deducciones_total)}</span></div>
        </div>
        <div class="breakdown-section breakdown-net">
            <h4>Sueldo neto</h4>
            <div class="breakdown-row breakdown-total" style="font-size:1.3em;"><span>Total a pagar</span><span>${fmt(i.sueldo_neto)}</span></div>
        </div>
    </div>`;
}
function fmt(val) { return '$' + parseFloat(val).toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}); }
</script>
<?php endif; ?>

<!-- Modal recibo individual -->
<div id="modalRecibo" class="modal">
    <div class="modal-content" style="max-width:600px;">
        <span class="modal-close" onclick="document.getElementById('modalRecibo').classList.remove('modal-open')">&times;</span>
        <div id="reciboContent"></div>
        <div class="form-actions" style="margin-top:16px;">
            <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir</button>
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalRecibo').classList.remove('modal-open')">Cerrar</button>
        </div>
    </div>
</div>

<script>
function openRecibo(idx) {
    const i = itemsData[idx];
    const sd = parseFloat(i.salario_diario);
    const he = parseInt(i.horas_extras);
    const hd = Math.min(9, he);
    const ht = Math.max(0, he - 9);
    const hePay = hd * (sd / 8) * 2 + ht * (sd / 8) * 3;
    const descRetardos = parseFloat(i.descuento_retardos) || 0;
    const tieneBonos = parseFloat(i.total_bonos) > 0;
    document.getElementById('reciboContent').innerHTML = `
    <div style="text-align:center;margin-bottom:20px;border-bottom:2px solid #000;padding-bottom:12px;">
        <h2 style="margin:0;">Recibo de Nómina</h2>
        <p style="margin:4px 0 0;font-size:0.9rem;">Período: ${i.periodo || '<?= h($period['periodo']) ?>'}</p>
    </div>
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
        <div><strong>Empleado:</strong> ${i.nombre} ${i.apellido_paterno} ${i.apellido_materno || ''}</div>
        <div><strong>Puesto:</strong> ${i.puesto || '—'}</div>
    </div>
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
        <div><strong>Departamento:</strong> ${i.departamento || '—'}</div>
        <div><strong>Salario diario:</strong> ${fmt(sd)}</div>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px;">
        <tr style="background:#f0f0f0;"><th style="text-align:left;padding:8px;border:1px solid #ccc;">Concepto</th><th style="text-align:right;padding:8px;border:1px solid #ccc;">Importe</th></tr>
        <tr><td style="padding:6px 8px;border:1px solid #ccc;">Días trabajados</td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;">${i.dias_trabajados} / ${i.dias_trabajados + i.faltas}</td></tr>
        <tr><td style="padding:6px 8px;border:1px solid #ccc;">Salario del período</td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;">${fmt(sd * i.dias_trabajados)}</td></tr>
        ${he > 0 ? `<tr><td style="padding:6px 8px;border:1px solid #ccc;">Horas extra (${hd}h dobles + ${ht}h triples)</td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;">${fmt(hePay)}</td></tr>` : ''}
        ${tieneBonos ? `<tr><td style="padding:6px 8px;border:1px solid #ccc;">Bonos</td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;">${fmt(i.total_bonos)}</td></tr>` : ''}
        ${parseFloat(i.aguinaldo_proporcional) > 0 ? `<tr><td style="padding:6px 8px;border:1px solid #ccc;">Aguinaldo proporcional</td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;">${fmt(i.aguinaldo_proporcional)}</td></tr>` : ''}
        ${parseFloat(i.prima_vacacional) > 0 ? `<tr><td style="padding:6px 8px;border:1px solid #ccc;">Prima vacacional</td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;">${fmt(i.prima_vacacional)}</td></tr>` : ''}
        <tr style="background:#e8f5e9;"><td style="padding:6px 8px;border:1px solid #ccc;"><strong>Total percepciones</strong></td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;"><strong>${fmt(i.percepciones_total)}</strong></td></tr>
        <tr><td style="padding:6px 8px;border:1px solid #ccc;">ISR (LISR Art. 96)</td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;">—${fmt(i.isr_retener)}</td></tr>
        ${parseFloat(i.subsidio_empleo) > 0 ? `<tr style="color:var(--color-success);"><td style="padding:6px 8px;border:1px solid #ccc;">Subsidio al empleo</td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;">+${fmt(i.subsidio_empleo)}</td></tr>` : ''}
        <tr><td style="padding:6px 8px;border:1px solid #ccc;">IMSS (cuota obrera)</td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;">—${fmt(i.imss_obrero)}</td></tr>
        ${i.faltas > 0 ? `<tr><td style="padding:6px 8px;border:1px solid #ccc;">Faltas (${i.faltas} días no laborados)</td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;">—</td></tr>` : ''}
        ${descRetardos > 0 ? `<tr><td style="padding:6px 8px;border:1px solid #ccc;">Descuento retardos (${i.retardos})</td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;">—${fmt(descRetardos)}</td></tr>` : ''}
        <tr style="background:#fbe9e7;"><td style="padding:6px 8px;border:1px solid #ccc;"><strong>Total deducciones</strong></td><td style="text-align:right;padding:6px 8px;border:1px solid #ccc;"><strong>—${fmt(i.deducciones_total)}</strong></td></tr>
        <tr style="background:#e3f2fd;"><td style="padding:10px 8px;border:1px solid #ccc;font-size:1.1em;"><strong>Sueldo neto</strong></td><td style="text-align:right;padding:10px 8px;border:1px solid #ccc;font-size:1.1em;"><strong>${fmt(i.sueldo_neto)}</strong></td></tr>
    </table>
    `;
    document.getElementById('modalRecibo').classList.add('modal-open');
}
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
