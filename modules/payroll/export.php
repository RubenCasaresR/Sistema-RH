<?php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/tax_calculator.php';
requireAuth();
requirePermission('payroll.export');

$periodId = (int)($_GET['period_id'] ?? 0);
if ($periodId <= 0) {
    setFlash('danger', 'Período inválido.');
    redirect(APP_URL . '/modules/payroll/index.php');
}

$db = getDB();

$stmtP = $db->prepare("SELECT * FROM payroll_periods WHERE id = :id LIMIT 1");
$stmtP->execute([':id' => $periodId]);
$period = $stmtP->fetch();

if (!$period) {
    setFlash('danger', 'Período no encontrado.');
    redirect(APP_URL . '/modules/payroll/index.php');
}

$stmt = $db->prepare("
    SELECT pi.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.curp, e.rfc, e.nss, e.puesto, e.departamento
    FROM payroll_items pi
    INNER JOIN employees e ON e.id = pi.employee_id
    WHERE pi.period_id = :pid
    ORDER BY e.apellido_paterno
");
$stmt->execute([':pid' => $periodId]);
$items = $stmt->fetchAll();

if (count($items) === 0) {
    setFlash('warning', 'No hay datos para exportar. Calcula la nómina primero.');
    redirect(APP_URL . '/modules/payroll/calculate.php?period_id=' . $periodId);
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="nomina_' . $period['periodo'] . '.csv"');

$output = fopen('php://output', 'w');

fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, [
    'Nombre', 'CURP', 'RFC', 'NSS', 'Puesto', 'Departamento',
    'Salario Base', 'Salario Diario', 'Días Trabajados', 'Faltas', 'Retardos', 'Horas Extra',
    'Salario Período', 'Pago Horas Extra', 'Aguinaldo Proporcional', 'Prima Vacacional',
    'Total Percepciones',
    'ISR', 'IMSS Obrero', 'Descuento Faltas', 'Subsidio Empleo',
    'Total Deducciones',
    'Sueldo Neto',
]);

// Sanitizar campo CSV contra formula injection
function csvCell(string $value): string
{
    $value = trim($value);
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"])) {
        return "'" . $value;
    }
    return $value;
}

foreach ($items as $i) {
    $hePay = (int)$i['horas_extras'] * ((float)$i['salario_diario'] / 8) * 2;
    $salarioPeriodo = (float)$i['salario_base'] / 30 * (int)$i['dias_trabajados'];
    $descFaltas = (int)$i['faltas'] * (float)$i['salario_diario'];

    fputcsv($output, [
        csvCell($i['nombre'] . ' ' . $i['apellido_paterno'] . ($i['apellido_materno'] ? ' ' . $i['apellido_materno'] : '')),
        csvCell($i['curp']),
        csvCell($i['rfc']),
        csvCell($i['nss']),
        csvCell($i['puesto'] ?? ''),
        csvCell($i['departamento'] ?? ''),
        csvCell(number_format((float)$i['salario_base'], 2)),
        csvCell(number_format((float)$i['salario_diario'], 2)),
        csvCell((string)(int)$i['dias_trabajados']),
        csvCell((string)(int)$i['faltas']),
        csvCell((string)(int)$i['retardos']),
        csvCell((string)(int)$i['horas_extras']),
        csvCell(number_format($salarioPeriodo, 2)),
        csvCell(number_format($hePay, 2)),
        csvCell(number_format((float)$i['aguinaldo_proporcional'], 2)),
        csvCell(number_format((float)$i['prima_vacacional'], 2)),
        csvCell(number_format((float)$i['percepciones_total'], 2)),
        csvCell(number_format((float)$i['isr_retener'], 2)),
        csvCell(number_format((float)$i['imss_obrero'], 2)),
        csvCell(number_format($descFaltas, 2)),
        csvCell(number_format((float)$i['subsidio_empleo'], 2)),
        csvCell(number_format((float)$i['deducciones_total'], 2)),
        csvCell(number_format((float)$i['sueldo_neto'], 2)),
    ]);
}

fclose($output);
exit;
