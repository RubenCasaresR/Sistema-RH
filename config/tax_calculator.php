<?php

define('TAX_YEAR', 2025);

/**
 * Cuenta los días laborables (lunes a viernes) dentro de un rango de fechas.
 */
function countWeekdays(string $inicio, string $fin): int
{
    $inicio = new DateTime($inicio);
    $fin = new DateTime($fin);
    if ($inicio > $fin) return 0;
    $dias = 0;
    while ($inicio <= $fin) {
        $wd = (int)$inicio->format('N');
        if ($wd <= 5) $dias++;
        $inicio->modify('+1 day');
    }
    return $dias;
}

function getISRTariff(int $year = TAX_YEAR, string $tipo = 'mensual'): array
{
    $db = getDB();
    $stmt = $db->prepare("
        SELECT limite_inferior, limite_superior, cuota_fija, porcentaje_excedente
        FROM tax_isr_tariff
        WHERE ejercicio = :year AND tipo = :tipo
        ORDER BY limite_inferior ASC
    ");
    $stmt->execute([':year' => $year, ':tipo' => $tipo]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        $stmtYear = $db->prepare("SELECT MAX(ejercicio) AS max_year FROM tax_isr_tariff WHERE tipo = :tipo");
        $stmtYear->execute([':tipo' => $tipo]);
        $maxYear = (int)$stmtYear->fetchColumn();
        if ($maxYear > 0 && $maxYear !== $year) {
            error_log("Tarifa ISR $year no encontrada, usando $maxYear como respaldo.");
            return getISRTariff($maxYear, $tipo);
        }
    }

    return $rows;
}

function getUMA(int $year = TAX_YEAR): array
{
    $db = getDB();
    $stmt = $db->prepare("SELECT valor_diario, valor_mensual FROM tax_uma WHERE ejercicio = :year LIMIT 1");
    $stmt->execute([':year' => $year]);
    $uma = $stmt->fetch();
    if (!$uma) {
        $stmtMax = $db->query("SELECT MAX(ejercicio) AS max_year FROM tax_uma");
        $maxYear = (int)$stmtMax->fetchColumn();
        if ($maxYear > 0 && $maxYear !== $year) {
            error_log("UMA $year no encontrada, usando $maxYear como respaldo.");
            return getUMA($maxYear);
        }
        return ['valor_diario' => 113.14, 'valor_mensual' => 3438.80];
    }
    return $uma;
}

function calculateISR(float $ingresoGravable, int $year = TAX_YEAR, string $tipo = 'mensual'): float
{
    if ($ingresoGravable <= 0.001) return 0;

    $tarifa = getISRTariff($year, $tipo);
    if (empty($tarifa)) return 0;

    foreach ($tarifa as $renglon) {
        $limInf = (float)$renglon['limite_inferior'];
        $limSup = (float)$renglon['limite_superior'];
        if ($ingresoGravable >= $limInf && $ingresoGravable <= $limSup) {
            $excedente = $ingresoGravable - $limInf;
            $impuesto = (float)$renglon['cuota_fija'] + ($excedente * (float)$renglon['porcentaje_excedente'] / 100);
            return round(max(0, $impuesto), 2);
        }
    }

    $ultimo = end($tarifa);
    $excedente = $ingresoGravable - (float)$ultimo['limite_inferior'];
    $impuesto = (float)$ultimo['cuota_fija'] + ($excedente * (float)$ultimo['porcentaje_excedente'] / 100);
    return round(max(0, $impuesto), 2);
}

function calculateSubsidioEmpleo(float $ingresoGravable, int $year = TAX_YEAR, string $tipo = 'mensual'): float
{
    $db = getDB();
    $stmt = $db->prepare("
        SELECT subsidio FROM tax_subsidio_tariff
        WHERE ejercicio = :year AND tipo = :tipo
          AND :ingreso1 >= limite_inferior AND :ingreso2 <= limite_superior
        LIMIT 1
    ");
    $stmt->execute([':year' => $year, ':tipo' => $tipo, ':ingreso1' => $ingresoGravable, ':ingreso2' => $ingresoGravable]);
    $row = $stmt->fetch();
    return $row ? (float)$row['subsidio'] : 0;
}

function calculateISRNeto(float $ingresoGravable, int $year = TAX_YEAR, string $tipo = 'mensual'): array
{
    $isr = calculateISR($ingresoGravable, $year, $tipo);
    $subsidio = calculateSubsidioEmpleo($ingresoGravable, $year, $tipo);
    $isrNeto = round(max(0, $isr - $subsidio), 2);
    return [
        'isr'       => $isr,
        'subsidio'  => $subsidio,
        'isr_neto'  => $isrNeto,
    ];
}

function calculateIMSSObrero(float $salarioDiario, int $diasDelPeriodo, int $year = TAX_YEAR): float
{
    $uma = getUMA($year);
    $umaDiaria = (float)$uma['valor_diario'];
    $sbc = max($umaDiaria, min($salarioDiario, $umaDiaria * 25));

    $cuotaFija = $umaDiaria * 0.00625 * $diasDelPeriodo;

    $excedente = max(0, $sbc - ($umaDiaria * 3));

    $cuotaAdicionalEnfMat = $excedente * 0.0040 * $diasDelPeriodo;
    $cuotaInvalidezVida = $excedente * 0.00625 * $diasDelPeriodo;
    $cuotaCesantiaVejez = $excedente * 0.01125 * $diasDelPeriodo;

    return round($cuotaFija + $cuotaAdicionalEnfMat + $cuotaInvalidezVida + $cuotaCesantiaVejez, 2);
}

function getVacationDays(int $yearsOfWork): int
{
    if ($yearsOfWork < 1) return 0;
    if ($yearsOfWork <= 4) return 12 + ($yearsOfWork - 1) * 2;
    return 20 + (int)(floor(($yearsOfWork - 5) / 5) + 1) * 2;
}

function calculateRetardoDeduction(int $retardos): float
{
    if ($retardos <= 0) return 0;
    $costoUnitario = max(100, ($retardos - 2) * 100);
    return $retardos * $costoUnitario;
}

function calculateAguinaldoProporcional(
    float $salarioDiario,
    string $fechaIngreso,
    string $periodoInicio,
    string $periodoFin
): float {
    $diasAguinaldo = 15;
    $inicio = new DateTime($periodoInicio);
    $fin = new DateTime($periodoFin);
    $diasDelPeriodo = (int)$fin->diff($inicio)->days + 1;

    $aguinaldoAnual = $salarioDiario * $diasAguinaldo;
    $aguinaldoDiario = $aguinaldoAnual / 365;
    $aguinaldoPeriodo = $aguinaldoDiario * $diasDelPeriodo;

    return round($aguinaldoPeriodo, 2);
}

function calculatePrimaVacacionalProporcional(
    float $salarioDiario,
    string $fechaIngreso,
    string $periodoInicio,
    string $periodoFin
): float {
    $fin = new DateTime($periodoFin);
    $ingreso = new DateTime($fechaIngreso);

    // Antigüedad calculada al cierre del período (determinista, no depende de "hoy")
    if ($ingreso > $fin) {
        $anios = 0;
    } else {
        $diff = $ingreso->diff($fin);
        $anios = (int)$diff->y;
    }
    $diasVacaciones = getVacationDays(max(0, $anios));

    $inicio = new DateTime($periodoInicio);
    $diasDelPeriodo = (int)$fin->diff($inicio)->days + 1;

    $primaVacacionalAnual = ($diasVacaciones * $salarioDiario) * 0.25;
    $primaDiaria = $primaVacacionalAnual / 365;
    $primaPeriodo = $primaDiaria * $diasDelPeriodo;

    return round($primaPeriodo, 2);
}

function getPayrollBonuses(int $periodId, int $employeeId): array
{
    $db = getDB();
    $stmt = $db->prepare("SELECT concepto, monto FROM payroll_bonus WHERE period_id = :pid AND employee_id = :eid");
    $stmt->execute([':pid' => $periodId, ':eid' => $employeeId]);
    return $stmt->fetchAll();
}

function getPayrollAdjustments(int $periodId, int $employeeId): array
{
    $db = getDB();
    $stmt = $db->prepare("
        SELECT tipo, concepto, monto FROM payroll_adjustments
        WHERE period_id = :pid AND employee_id = :eid
    ");
    $stmt->execute([':pid' => $periodId, ':eid' => $employeeId]);
    return $stmt->fetchAll();
}

function calculatePayrollForEmployee(
    array $employee,
    string $fechaIngreso,
    string $periodoInicio,
    string $periodoFin,
    int $diasDelPeriodo,
    int $periodId = 0,
    string $tipoPeriodo = 'mensual'
): array {
    $salarioBase = (float)$employee['salario_base'];
    $salarioDiario = $salarioBase / 30;
    $diasDelPeriodo = max(1, $diasDelPeriodo);
    $factorPeriodo = $tipoPeriodo === 'quincenal' ? 2 : 1;

    $db = getDB();
    $eid = (int)$employee['id'];

    // Ejercicio fiscal del período (determinista; no se congela en TAX_YEAR)
    $anioPeriodo = (int)(new DateTime($periodoFin))->format('Y');

    // Determinar el inicio real considerando la fecha de ingreso del empleado
    $inicioReal = $periodoInicio;
    if ($fechaIngreso && $fechaIngreso > $periodoInicio) {
        $inicioReal = $fechaIngreso;
    }
    $diasDelPeriodoReal = $diasDelPeriodo;
    if ($inicioReal !== $periodoInicio) {
        $diasDelPeriodoReal = max(1, (new DateTime($periodoFin))->diff(new DateTime($inicioReal))->days + 1);
    }

    // Días laborables (lun-vie) del período completo y del tramo efectivamente cubierto
    $diasLaborablesTotales = max(1, countWeekdays($periodoInicio, $periodoFin));
    $diasLaborablesReales = max(1, countWeekdays($inicioReal, $periodoFin));

    $umbralRetardo = defined('LATE_THRESHOLD') ? LATE_THRESHOLD : '09:05';

    $stmtA = $db->prepare("
        SELECT
            COUNT(*) AS total_dias,
            SUM(CASE WHEN hora_entrada IS NOT NULL AND hora_salida IS NOT NULL THEN 1 ELSE 0 END) AS dias_completos,
            SUM(CASE WHEN hora_entrada IS NOT NULL AND TIME(hora_entrada) > :umbral THEN 1 ELSE 0 END) AS retardos,
            SUM(CASE WHEN hora_entrada IS NOT NULL AND hora_salida IS NOT NULL
                THEN TIMESTAMPDIFF(MINUTE, hora_entrada, hora_salida) - 480 ELSE 0 END) AS minutos_extra
        FROM attendance_logs
        WHERE employee_id = :eid
          AND fecha BETWEEN :inicio AND :fin
          AND tipo = 'regular'
    ");
    $stmtA->execute([':eid' => $eid, ':inicio' => $inicioReal, ':fin' => $periodoFin, ':umbral' => $umbralRetardo]);
    $asis = $stmtA->fetch();

    $diasConRegistro = (int)($asis['total_dias'] ?? 0);
    $diasCompletos = max(0, (int)($asis['dias_completos'] ?? 0));
    $retardos = max(0, (int)($asis['retardos'] ?? 0));
    $minutosExtra = max(0, (int)($asis['minutos_extra'] ?? 0));
    $horasExtra = round($minutosExtra / 60, 2);

    // Ajustes manuales (faltas, retardos, horas extra, percepciones y deducciones)
    $percepcionesAdicionales = 0;
    $deduccionesAdicionales = 0;
    if ($periodId > 0) {
        $adjustments = getPayrollAdjustments($periodId, $eid);
        foreach ($adjustments as $adj) {
            switch ($adj['tipo']) {
                case 'falta':
                    $faltasManual = (int)$adj['monto'];
                    break;
                case 'retardo':
                    $retardos += (int)$adj['monto'];
                    break;
                case 'hora_extra':
                    $horasExtra += (int)$adj['monto'];
                    break;
                case 'percepcion':
                    $percepcionesAdicionales += (float)$adj['monto'];
                    break;
                case 'deduccion':
                    $deduccionesAdicionales += (float)$adj['monto'];
                    break;
            }
        }
    }

    // Faltas: días laborables sin ningún registro de checador (fines de semana no cuentan)
    $faltas = max(0, $diasLaborablesReales - $diasConRegistro);
    if (isset($faltasManual)) $faltas += $faltasManual;
    $faltas = max(0, min($faltas, $diasLaborablesReales));

    $descuentoRetardos = calculateRetardoDeduction($retardos);

    // Días efectivamente trabajados y salario del período proporcional
    $diasTrabajados = $diasLaborablesReales - $faltas;
    $salarioPeriodo = ($salarioBase / $factorPeriodo) * ($diasTrabajados / $diasLaborablesTotales);
    $descuentoFaltas = 0;

    // Horas extra: dobles (primeras 9) y triples (subsecuentes)
    $horasDobles = min(9, $horasExtra);
    $horasTriples = max(0, $horasExtra - 9);
    $pagoHorasExtra = $horasDobles * ($salarioDiario / 8) * 2 + $horasTriples * ($salarioDiario / 8) * 3;

    $aguinaldoProp = calculateAguinaldoProporcional($salarioDiario, $fechaIngreso, $periodoInicio, $periodoFin);
    $primaVacProp = calculatePrimaVacacionalProporcional($salarioDiario, $fechaIngreso, $periodoInicio, $periodoFin);

    // Bonos desde payroll_bonus
    $totalBonos = 0;
    $bonosDetalle = [];
    if ($periodId > 0) {
        $bonos = getPayrollBonuses($periodId, $eid);
        foreach ($bonos as $b) {
            $monto = (float)$b['monto'];
            $totalBonos += $monto;
            $bonosDetalle[] = $b['concepto'] . ': $' . number_format($monto, 2);
        }
    }

    $percepciones = $salarioPeriodo + $pagoHorasExtra + $aguinaldoProp + $primaVacProp + $totalBonos + $percepcionesAdicionales;

    // Base gravable: sobre lo efectivamente devengado (no sobre el salario base completo)
    $ingresoGravable = $salarioPeriodo + $pagoHorasExtra + $aguinaldoProp + $primaVacProp + $totalBonos + $percepcionesAdicionales;

    // ISR neto con subsidio al empleo (ejercicio del período)
    $isrCalc = calculateISRNeto($ingresoGravable, $anioPeriodo, $tipoPeriodo);
    $isr = $isrCalc['isr_neto'];
    $subsidio = $isrCalc['subsidio'];

    // Subsidio al empleo que excede el ISR: se paga al trabajador (LISR)
    $subsidioCompensable = round(max(0, $subsidio - $isrCalc['isr']), 2);
    if ($subsidioCompensable > 0) {
        $percepciones += $subsidioCompensable;
    }

    $imss = calculateIMSSObrero($salarioDiario, $diasLaborablesReales, $anioPeriodo);

    $deducciones = $isr + $imss + $descuentoRetardos + $deduccionesAdicionales;
    $sueldoNeto = round(max(0, $percepciones - $deducciones), 2);
    $totalIncidencias = round($descuentoRetardos - $pagoHorasExtra, 2);

    return [
        'salario_base'          => $salarioBase,
        'salario_diario'        => round($salarioDiario, 2),
        'salario_periodo'       => round($salarioPeriodo, 2),
        'dias_laborables'       => $diasLaborablesReales,
        'dias_trabajados'       => $diasTrabajados,
        'dias_del_periodo'      => $diasDelPeriodo,
        'faltas'                => $faltas,
        'retardos'              => $retardos,
        'horas_extras'          => $horasExtra,
        'horas_dobles'          => $horasDobles,
        'horas_triples'         => $horasTriples,
        'total_bonos'           => $totalBonos,
        'bonos_detalle'         => $bonosDetalle,
        'pago_horas_extra'      => round($pagoHorasExtra, 2),
        'aguinaldo_proporcional'=> $aguinaldoProp,
        'prima_vacacional'      => $primaVacProp,
        'percepciones_adicionales' => $percepcionesAdicionales,
        'deducciones_adicionales'  => $deduccionesAdicionales,
        'subsidio_compensable'  => $subsidioCompensable,
        'percepciones_total'    => round($percepciones, 2),
        'isr_retener'           => $isr,
        'isr_bruto'             => $isrCalc['isr'],
        'imss_obrero'           => $imss,
        'subsidio_empleo'       => round($subsidio, 2),
        'descuento_faltas'      => round($descuentoFaltas, 2),
        'descuento_retardos'    => $descuentoRetardos,
        'total_deducciones'     => round($deducciones, 2),
        'sueldo_bruto'          => round($percepciones, 2),
        'sueldo_neto'           => $sueldoNeto,
        'total_incidencias'     => $totalIncidencias,
    ];
}
