<?php

define('TAX_YEAR', 2025);

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
    return $stmt->fetchAll();
}

function getUMA(int $year = TAX_YEAR): array
{
    $db = getDB();
    $stmt = $db->prepare("SELECT valor_diario, valor_mensual FROM tax_uma WHERE ejercicio = :year LIMIT 1");
    $stmt->execute([':year' => $year]);
    $uma = $stmt->fetch();
    if (!$uma) {
        return ['valor_diario' => 113.14, 'valor_mensual' => 3438.80];
    }
    return $uma;
}

function calculateISR(float $ingresoGravable, int $year = TAX_YEAR, string $tipo = 'mensual'): float
{
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

    $ingreso = new DateTime($fechaIngreso);
    $hoy = new DateTime();
    $diasTrabajadosAnio = min(365, (int)$hoy->diff($ingreso)->days);
    $diasTrabajadosAnio = max(1, $diasTrabajadosAnio);

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
    $ingreso = new DateTime($fechaIngreso);
    $hoy = new DateTime();
    $anios = (int)$ingreso->diff($hoy)->y;
    $diasVacaciones = getVacationDays(max(1, $anios));

    $inicio = new DateTime($periodoInicio);
    $fin = new DateTime($periodoFin);
    $diasDelPeriodo = (int)$fin->diff($inicio)->days + 1;

    $primaVacacionalAnual = ($diasVacaciones * $salarioDiario) * 0.25;
    $primaDiaria = $primaVacacionalAnual / 365;
    $primaPeriodo = $primaDiaria * $diasDelPeriodo;

    return round($primaPeriodo, 2);
}

function calculatePayrollForEmployee(
    array $employee,
    string $fechaIngreso,
    string $periodoInicio,
    string $periodoFin,
    int $diasDelPeriodo
): array {
    $salarioBase = (float)$employee['salario_base'];
    $salarioDiario = $salarioBase / 30;
    $diasDelPeriodo = max(1, $diasDelPeriodo);

    $db = getDB();
    $eid = (int)$employee['id'];

    $stmtA = $db->prepare("
        SELECT
            COUNT(*) AS total_dias,
            SUM(CASE WHEN hora_entrada IS NULL THEN 1 ELSE 0 END) AS faltas,
            SUM(CASE WHEN hora_entrada IS NOT NULL AND HOUR(hora_entrada) >= 9 AND MINUTE(hora_entrada) > 5 THEN 1 ELSE 0 END) AS retardos,
            SUM(CASE WHEN hora_entrada IS NOT NULL AND hora_salida IS NOT NULL
                THEN TIMESTAMPDIFF(HOUR, hora_entrada, hora_salida) - 8 ELSE 0 END) AS horas_extra
        FROM attendance_logs
        WHERE employee_id = :eid
          AND fecha BETWEEN :inicio AND :fin
          AND tipo = 'regular'
    ");
    $stmtA->execute([':eid' => $eid, ':inicio' => $periodoInicio, ':fin' => $periodoFin]);
    $asis = $stmtA->fetch();

    $faltas = max(0, (int)($asis['faltas'] ?? 0));
    $retardos = max(0, (int)($asis['retardos'] ?? 0));
    $horasExtra = max(0, (int)($asis['horas_extra'] ?? 0));
    $diasTrabajados = max(0, $diasDelPeriodo - $faltas);

    $salarioPeriodo = $salarioDiario * $diasTrabajados;
    $pagoHorasExtra = $horasExtra * ($salarioDiario / 8) * 2;
    $descuentoFaltas = $faltas * $salarioDiario;

    $aguinaldoProp = calculateAguinaldoProporcional($salarioDiario, $fechaIngreso, $periodoInicio, $periodoFin);
    $primaVacProp = calculatePrimaVacacionalProporcional($salarioDiario, $fechaIngreso, $periodoInicio, $periodoFin);

    $percepciones = $salarioPeriodo + $pagoHorasExtra + $aguinaldoProp + $primaVacProp;
    $ingresoMensual = $salarioBase + $pagoHorasExtra + $aguinaldoProp + $primaVacProp;

    $isr = calculateISR($ingresoMensual);
    $imss = calculateIMSSObrero($salarioDiario, $diasDelPeriodo);

    $subsidio = 0;

    $deducciones = $isr + $imss + $descuentoFaltas;
    $sueldoNeto = round(max(0, $percepciones - $deducciones), 2);
    $totalIncidencias = round($descuentoFaltas - $pagoHorasExtra, 2);

    return [
        'salario_base'          => $salarioBase,
        'salario_diario'        => round($salarioDiario, 2),
        'dias_trabajados'       => $diasTrabajados,
        'dias_del_periodo'      => $diasDelPeriodo,
        'faltas'                => $faltas,
        'retardos'              => $retardos,
        'horas_extras'          => $horasExtra,
        'total_bonos'           => 0,
        'pago_horas_extra'      => round($pagoHorasExtra, 2),
        'aguinaldo_proporcional'=> $aguinaldoProp,
        'prima_vacacional'      => $primaVacProp,
        'percepciones_total'    => round($percepciones, 2),
        'isr_retener'           => $isr,
        'imss_obrero'           => $imss,
        'subsidio_empleo'       => round($subsidio, 2),
        'descuento_faltas'      => round($descuentoFaltas, 2),
        'total_deducciones'     => round($deducciones, 2),
        'sueldo_bruto'          => round($percepciones, 2),
        'sueldo_neto'           => $sueldoNeto,
        'total_incidencias'     => $totalIncidencias,
    ];
}
