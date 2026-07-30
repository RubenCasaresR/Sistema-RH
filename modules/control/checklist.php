<?php
require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('control.checklist');

$db = getDB();
$anio = (int)($_GET['anio'] ?? (int)date('Y'));
$mes  = (int)($_GET['mes'] ?? (int)date('m'));
if ($mes < 1 || $mes > 12) $mes = (int)date('m');

$stmt = $db->prepare("SELECT * FROM control_checklist WHERE anio = :anio AND mes = :mes ORDER BY FIELD(frecuencia, 'semanal','mensual','bimestral','semestral','permanente'), semana, id");
$stmt->execute([':anio' => $anio, ':mes' => $mes]);
$items = $stmt->fetchAll();

$meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

$estatusOpciones = [
    'completado'  => '✅ Completado',
    'en_proceso'  => '⏳ En proceso',
    'no_realizado'=> '❌ No realizado',
    'na'          => 'N/A',
];

$secciones = [
    'semanal'    => ['label' => 'Semanal', 'icon' => 'fa-calendar-day'],
    'mensual'    => ['label' => 'Mensual', 'icon' => 'fa-calendar'],
    'bimestral'  => ['label' => 'Bimestral / Semestral', 'icon' => 'fa-calendar-days'],
    'semestral'  => ['label' => 'Bimestral / Semestral', 'icon' => 'fa-calendar-check'],
    'permanente' => ['label' => 'Permanente', 'icon' => 'fa-thumbtack'],
];

$grupos = [];
foreach ($items as $item) {
    $key = $item['frecuencia'];
    if ($key === 'bimestral' || $key === 'semestral') $key = 'bimestral_semestral';
    $grupos[$key][] = $item;
}

$csrfToken = generateCSRFToken();
$extraCss = ['control'];
$pageTitle = 'Checklist Mensual';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-list-check" style="color:var(--color-primary);margin-right:8px;"></i> Checklist Mensual</h2>
        <p class="greeting">Control de tareas operativas — <?= $meses[$mes - 1] . ' ' . $anio ?></p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="saveChecklistBulk()"><i class="fa-solid fa-save"></i> Guardar cambios</button>
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

<!-- Secciones del checklist -->
<?php foreach ($grupos as $grupoKey => $grupoItems): ?>
    <?php if (empty($grupoItems)) continue; ?>
    <?php
    if ($grupoKey === 'bimestral_semestral') {
        $secLabel = 'Bimestral / Semestral';
        $secIcon = 'fa-calendar-days';
    } else {
        $secLabel = $secciones[$grupoKey]['label'] ?? ucfirst($grupoKey);
        $secIcon = $secciones[$grupoKey]['icon'] ?? 'fa-list';
    }
    ?>
    <div class="checklist-seccion">
        <div class="checklist-seccion-header">
            <i class="fa-solid <?= $secIcon ?>"></i> <?= $secLabel ?>
        </div>
        <div class="card" style="border-radius:0 0 var(--radius) var(--radius);margin-top:0;">
            <div class="table-responsive">
                <table class="checklist-table">
                    <thead>
                        <tr>
                            <?php if ($grupoKey === 'semanal'): ?>
                                <th class="td-semana">Semana</th>
                            <?php endif; ?>
                            <th class="td-tarea">Tarea</th>
                            <th class="td-estatus">Estatus</th>
                            <th class="td-fecha">Fecha completado</th>
                            <th class="td-notas">Notas / Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grupoItems as $item): ?>
                            <tr class="checklist-row" data-id="<?= $item['id'] ?>">
                                <?php if ($grupoKey === 'semanal'): ?>
                                    <td class="td-semana"><?= h($item['semana'] ?? '') ?></td>
                                <?php endif; ?>
                                <td class="td-tarea" style="font-weight:500;"><?= h($item['descripcion_tarea']) ?></td>
                                <td class="td-estatus">
                                    <select class="cl-estatus">
                                        <?php foreach ($estatusOpciones as $k => $v): ?>
                                            <option value="<?= $k ?>" <?= $item['estatus'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="td-fecha">
                                    <input type="date" class="cl-fecha" value="<?= h($item['fecha_completado'] ?? '') ?>">
                                </td>
                                <td class="td-notas">
                                    <textarea class="cl-notas" rows="1"><?= h($item['notas'] ?? '') ?></textarea>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (empty($items)): ?>
    <div class="card">
        <p class="text-center empty-state" style="padding:24px;">No hay tareas en el checklist para <?= $meses[$mes - 1] . ' ' . $anio ?>.</p>
    </div>
<?php endif; ?>

<!-- Nota informativa -->
<div class="card" style="margin-top:16px;">
    <p style="font-size:0.85rem;color:var(--color-text-secondary);padding:4px 0;">
        <i class="fa-solid fa-info-circle" style="margin-right:4px;"></i>
        En la columna "Estatus" seleccione: ✅ Completado / ⏳ En proceso / ❌ No realizado / N/A. Haga clic en "Guardar cambios" cuando termine.
    </p>
</div>

<?php
$extraJs = ['control'];
require_once __DIR__ . '/../../includes/footer.php';
?>
