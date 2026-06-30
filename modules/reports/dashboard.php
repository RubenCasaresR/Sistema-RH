<?php
require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('reports.dashboard');

$extraCss = ['dashboard'];
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// === KPIs ===
$plantillaActiva = $db->query("SELECT COUNT(*) FROM employees WHERE activo = 1")->fetchColumn();
$altasMes = $db->query("SELECT COUNT(*) FROM employees WHERE activo = 1 AND MONTH(fecha_ingreso) = MONTH(CURDATE()) AND YEAR(fecha_ingreso) = YEAR(CURDATE())")->fetchColumn();
$bajasMes = $db->query("SELECT COUNT(*) FROM employees WHERE activo = 0 AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())")->fetchColumn();
$rotacion = $plantillaActiva > 0 ? round(($altasMes + $bajasMes) / $plantillaActiva * 100, 1) : 0;
$diasDelMes = (int)(new DateTime())->format('t');
$totalFaltas = $db->query("SELECT COUNT(*) FROM attendance_logs WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND hora_entrada IS NULL")->fetchColumn();
$ausentismo = ($plantillaActiva > 0 && $diasDelMes > 0) ? round($totalFaltas / ($diasDelMes * $plantillaActiva) * 100, 1) : 0;
$vacPendientes = $db->query("SELECT COUNT(*) FROM leave_requests WHERE estatus = 'pendiente'")->fetchColumn();
$antiguedadPromedio = $db->query("SELECT ROUND(AVG(DATEDIFF(CURDATE(), fecha_ingreso) / 365), 1) FROM employees WHERE activo = 1 AND fecha_ingreso IS NOT NULL")->fetchColumn();

// Cumpleaños del mes
$cumples = $db->query("
    SELECT nombre, apellido_paterno, fecha_nacimiento, DAY(fecha_nacimiento) AS dia
    FROM employees
    WHERE activo = 1 AND MONTH(fecha_nacimiento) = MONTH(CURDATE())
    ORDER BY DAY(fecha_nacimiento)
")->fetchAll();

// Distribución por departamento
$deptos = $db->query("SELECT departamento, COUNT(*) AS total FROM employees WHERE activo = 1 AND departamento IS NOT NULL AND departamento != '' GROUP BY departamento ORDER BY total DESC")->fetchAll();

// Asistencia últimos 7 días
$asistenciaSemanal = $db->query("
    SELECT fecha,
           SUM(CASE WHEN hora_entrada IS NOT NULL THEN 1 ELSE 0 END) AS presentes,
           SUM(CASE WHEN hora_entrada IS NULL THEN 1 ELSE 0 END) AS ausentes
    FROM attendance_logs
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
      AND tipo = 'regular'
    GROUP BY fecha
    ORDER BY fecha
")->fetchAll();

// Últimos períodos de nómina
$periodosNomina = $db->query("SELECT periodo, total_neto FROM (
    SELECT pp.periodo, SUM(pi.sueldo_neto) AS total_neto
    FROM payroll_items pi
    INNER JOIN payroll_periods pp ON pp.id = pi.period_id
    GROUP BY pp.id, pp.periodo
    ORDER BY pp.periodo DESC
    LIMIT 6
) AS sub ORDER BY periodo")->fetchAll();
$periodosNomina = array_reverse($periodosNomina);

$flash = getFlash();
$hour = (int)date('H');
if ($hour < 12) {
    $greeting = 'Buenos días';
} elseif ($hour < 19) {
    $greeting = 'Buenas tardes';
} else {
    $greeting = 'Buenas noches';
}
$userName = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? '';
$diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$mesesAnio = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fechaLarga = $diasSemana[(int)date('w')] . ', ' . (int)date('d') . ' de ' . $mesesAnio[(int)date('n') - 1] . ' de ' . date('Y');
?>
<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= h($flash['message']) ?></div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h2>Dashboard</h2>
        <p class="greeting"><?= h($greeting) ?>, <?= h($userName) ?> &middot; <span class="text-secondary"><?= $fechaLarga ?></span></p>
    </div>
</div>

<!-- KPIs -->
<div class="dashboard-grid">
    <div class="kpi-card">
        <div class="kpi-icon kpi-icon-emerald">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="kpi-body">
            <span class="kpi-value"><?= $plantillaActiva ?></span>
            <span class="kpi-label">Plantilla activa</span>
            <span class="kpi-trend up"><i class="fa-solid fa-arrow-up"></i> <?= $altasMes ?> altas este mes</span>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon kpi-icon-amber">
            <i class="fa-solid fa-arrows-spin"></i>
        </div>
        <div class="kpi-body">
            <span class="kpi-value"><?= $rotacion ?>%</span>
            <span class="kpi-label">Rotación mensual</span>
            <span class="kpi-trend <?= $rotacion > 5 ? 'down' : 'up' ?>"><i class="fa-solid fa-<?= $rotacion > 5 ? 'arrow-down' : 'arrow-up' ?>"></i> <?= $altasMes ?> altas / <?= $bajasMes ?> bajas</span>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon kpi-icon-blue">
            <i class="fa-solid fa-bed"></i>
        </div>
        <div class="kpi-body">
            <span class="kpi-value"><?= $ausentismo ?>%</span>
            <span class="kpi-label">Ausentismo</span>
            <span class="kpi-trend <?= $ausentismo > 5 ? 'down' : 'up' ?>"><i class="fa-solid fa-<?= $ausentismo > 5 ? 'arrow-down' : 'arrow-up' ?>"></i> <?= $totalFaltas ?> faltas este mes</span>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon kpi-icon-emerald">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div class="kpi-body">
            <span class="kpi-value"><?= $vacPendientes ?></span>
            <span class="kpi-label">Vacaciones pendientes</span>
            <span class="kpi-trend"><i class="fa-regular fa-clock"></i> <?= $antiguedadPromedio ?: 0 ?> años antigüedad prom.</span>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="charts-grid">
    <div class="chart-container">
        <div class="chart-header">
            <i class="fa-solid fa-chart-bar" style="color:var(--color-primary);"></i>
            <h3>Asistencia (últimos 7 días)</h3>
        </div>
        <div class="chart-body"><canvas id="chartAsistencia"></canvas></div>
    </div>
    <div class="chart-container">
        <div class="chart-header">
            <i class="fa-solid fa-building" style="color:var(--color-secondary);"></i>
            <h3>Distribución por departamento</h3>
        </div>
        <div class="chart-body"><canvas id="chartDeptos"></canvas></div>
    </div>
</div>

<?php if (count($periodosNomina) > 0): ?>
    <div class="chart-container chart-full">
        <div class="chart-header">
            <i class="fa-solid fa-file-invoice-dollar" style="color:var(--color-primary);"></i>
            <h3>Nómina mensual</h3>
        </div>
        <div class="chart-body chart-body-lg"><canvas id="chartNomina"></canvas></div>
    </div>
<?php endif; ?>

<!-- Cumpleaños -->
<?php if (count($cumples) > 0): ?>
    <div class="card">
        <h3 class="card-title"><i class="fa-solid fa-cake-candles" style="margin-right:6px;color:var(--color-warning);"></i> Cumpleaños del mes</h3>
        <div class="birthday-scroll">
            <?php foreach ($cumples as $c): ?>
                <div class="birthday-chip">
                    <span class="birthday-avatar"><?= h(strtoupper(substr($c['nombre'], 0, 1))) ?></span>
                    <div class="birthday-info">
                        <span class="birthday-name"><?= h($c['nombre'] . ' ' . $c['apellido_paterno']) ?></span>
                        <span class="birthday-date"><?= formatDate($c['fecha_nacimiento']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Acceso rápido -->
<div class="card">
    <h3 class="card-title"><i class="fa-solid fa-rocket" style="margin-right:6px;color:var(--color-primary);"></i> Acceso rápido</h3>
    <div class="quick-grid">
        <?php if (can('employees.create')): ?>
            <a href="<?= APP_URL ?>/modules/employees/create.php" class="quick-item">
                <span class="quick-icon" style="background:var(--color-primary-light);color:var(--color-primary);"><i class="fa-solid fa-user-plus"></i></span>
                <span class="quick-label">Nuevo empleado</span>
            </a>
        <?php endif; ?>
        <?php if (can('attendance.clock')): ?>
            <a href="<?= APP_URL ?>/modules/attendance/clock.php" class="quick-item">
                <span class="quick-icon" style="background:#dbeafe;color:var(--color-secondary);"><i class="fa-solid fa-clock"></i></span>
                <span class="quick-label">Reloj checador</span>
            </a>
        <?php endif; ?>
        <?php if (can('leave.request')): ?>
            <a href="<?= APP_URL ?>/modules/leave/requests.php" class="quick-item">
                <span class="quick-icon" style="background:#fef3c7;color:var(--color-warning);"><i class="fa-solid fa-umbrella-beach"></i></span>
                <span class="quick-label">Vacaciones</span>
            </a>
        <?php endif; ?>
        <?php if (can('payroll.calculate')): ?>
            <a href="<?= APP_URL ?>/modules/payroll/index.php" class="quick-item">
                <span class="quick-icon" style="background:#ecfdf5;color:var(--color-primary);"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                <span class="quick-label">Nómina</span>
            </a>
        <?php endif; ?>
        <?php if (can('recruitment.read')): ?>
            <a href="<?= APP_URL ?>/modules/recruitment/vacancies.php" class="quick-item">
                <span class="quick-icon" style="background:#f3e8ff;color:#7c3aed;"><i class="fa-solid fa-briefcase"></i></span>
                <span class="quick-label">Vacantes</span>
            </a>
        <?php endif; ?>
        <?php if (can('documents.upload')): ?>
            <a href="<?= APP_URL ?>/modules/documents/index.php" class="quick-item">
                <span class="quick-icon" style="background:#fce7f3;color:#db2777;"><i class="fa-solid fa-file-lines"></i></span>
                <span class="quick-label">Documentos</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<script>
window.DASHBOARD_DATA = <?= json_encode([
    'asistencia' => [
        'labels'    => array_map(function($a) { return date('d/m', strtotime($a['fecha'])); }, $asistenciaSemanal),
        'presentes' => array_map(function($a) { return (int)$a['presentes']; }, $asistenciaSemanal),
        'ausentes'  => array_map(function($a) { return (int)$a['ausentes']; }, $asistenciaSemanal),
    ],
    'deptos' => [
        'labels' => array_map(function($d) { return $d['departamento']; }, $deptos),
        'data'   => array_map(function($d) { return (int)$d['total']; }, $deptos),
    ],
    'nomina' => [
        'labels' => array_map(function($p) { return $p['periodo']; }, $periodosNomina),
        'data'   => array_map(function($p) { return (float)$p['total_neto']; }, $periodosNomina),
    ],
]) ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<?php
$extraJs = ['dashboard'];
require_once __DIR__ . '/../../includes/footer.php';
?>
