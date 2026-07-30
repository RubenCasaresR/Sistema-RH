<?php
require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('control.indicadores');

$db = getDB();
$anio = (int)($_GET['anio'] ?? (int)date('Y'));
$mes  = (int)($_GET['mes'] ?? (int)date('m'));
if ($mes < 1 || $mes > 12) $mes = (int)date('m');

$stmt = $db->prepare("SELECT categoria, indicador, valor, calculado_auto FROM control_indicadores WHERE anio = :anio AND mes = :mes ORDER BY categoria, indicador");
$stmt->execute([':anio' => $anio, ':mes' => $mes]);
$indicadores = $stmt->fetchAll();

$meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

$cats = [
    'asistencia'    => ['label' => 'Asistencia', 'icon' => 'fa-clock', 'color' => '#dc2626'],
    'personal'      => ['label' => 'Personal', 'icon' => 'fa-users', 'color' => '#059669'],
    'clima'         => ['label' => 'Clima y Conflictos', 'icon' => 'fa-people-arrows', 'color' => '#d97706'],
    'expedientes'   => ['label' => 'Expedientes', 'icon' => 'fa-folder-open', 'color' => '#7c3aed'],
    'reclutamiento' => ['label' => 'Reclutamiento', 'icon' => 'fa-user-plus', 'color' => '#2563eb'],
];

$porCategoria = [];
foreach ($indicadores as $ind) {
    $porCategoria[$ind['categoria']][] = $ind;
}

$extraCss = ['control'];
$pageTitle = 'Indicadores Mensuales';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-chart-line" style="color:var(--color-primary);margin-right:8px;"></i> Indicadores Mensuales</h2>
        <p class="greeting">Seguimiento de KPIs de Recursos Humanos</p>
    </div>
    <div class="header-actions">
        <?php if (can('control.calcular')): ?>
            <button type="button" class="btn btn-primary" onclick="calcularIndicadores(<?= $anio ?>, <?= $mes ?>)">
                <i class="fa-solid fa-calculator"></i> Recalcular indicadores
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros -->
<div class="card">
    <form method="GET" class="filter-bar">
        <label style="font-size:0.85rem;font-weight:500;">Período:</label>
        <select name="mes">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $mes ? 'selected' : '' ?>><?= $meses[$m - 1] ?></option>
            <?php endfor; ?>
        </select>
        <select name="anio">
            <?php for ($a = (int)date('Y') - 2; $a <= (int)date('Y') + 1; $a++): ?>
                <option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-filter"></i> Filtrar</button>
    </form>
</div>

<!-- Indicadores por categoría -->
<?php foreach ($cats as $catKey => $catInfo): ?>
    <div class="card" style="margin-top:16px;">
        <h3 class="card-title">
            <i class="fa-solid <?= $catInfo['icon'] ?>" style="margin-right:6px;color:<?= $catInfo['color'] ?>;"></i>
            <?= $catInfo['label'] ?>
        </h3>
        <?php if (empty($porCategoria[$catKey])): ?>
            <p class="text-center empty-state" style="padding:20px;">No hay datos calculados para este período. Haga clic en "Recalcular indicadores".</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Indicador</th>
                            <th style="text-align:right;">Valor</th>
                            <th style="text-align:center;">Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($porCategoria[$catKey] as $ind): ?>
                            <tr>
                                <td style="font-weight:500;"><?= h($ind['indicador']) ?></td>
                                <td style="text-align:right;font-weight:600;font-size:1rem;"><?= number_format($ind['valor'], 2) ?></td>
                                <td style="text-align:center;">
                                    <?php if ($ind['calculado_auto']): ?>
                                        <span class="badge badge-success">Automático</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Manual</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php
$extraJs = ['control'];
require_once __DIR__ . '/../../includes/footer.php';
?>
