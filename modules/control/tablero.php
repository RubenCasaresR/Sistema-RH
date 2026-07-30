<?php
require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('control.tablero');

$db = getDB();
$anio = (int)($_GET['anio'] ?? (int)date('Y'));
if ($anio < 2000 || $anio > 2100) $anio = (int)date('Y');

$tareas = $db->query("SELECT id, categoria, nombre, orden FROM control_tareas WHERE activo = 1 ORDER BY FIELD(categoria, 'semanal','mensual','bimestral','semestral','permanente'), orden")->fetchAll();

$stmt = $db->prepare("SELECT tarea_id, mes, estatus, notas FROM control_avance WHERE anio = :anio");
$stmt->execute([':anio' => $anio]);
$avances = [];
foreach ($stmt->fetchAll() as $row) {
    $avances[$row['tarea_id']][$row['mes']] = ['estatus' => $row['estatus'], 'notas' => $row['notas']];
}

$mesesCorto = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
$estatusIconos = ['pendiente' => '-', 'en_proceso' => '⏳', 'completado' => '✅', 'no_realizado' => '❌', 'na' => 'N/A'];

$extraCss = ['control'];
$pageTitle = 'Tablero Anual';
require_once __DIR__ . '/../../includes/header.php';

$categorias = [
    'semanal'   => ['label' => 'Rutina Semanal', 'icon' => 'fa-calendar-day'],
    'mensual'   => ['label' => 'Rutina Mensual', 'icon' => 'fa-calendar'],
    'bimestral' => ['label' => 'Rutina Bimestral', 'icon' => 'fa-calendar-days'],
    'semestral' => ['label' => 'Rutina Semestral', 'icon' => 'fa-calendar-check'],
    'permanente'=> ['label' => 'Tareas Permanentes', 'icon' => 'fa-thumbtack'],
];

$grupos = [];
foreach ($tareas as $t) {
    $grupos[$t['categoria']][] = $t;
}
?>

<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-th" style="color:var(--color-primary);margin-right:8px;"></i> Tablero Anual de Control</h2>
        <p class="greeting">Seguimiento de tareas de Recursos Humanos — <?= $anio ?></p>
    </div>
    <div class="header-actions">
        <a href="?anio=<?= $anio - 1 ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> <?= $anio - 1 ?></a>
        <span style="font-weight:600;font-size:1.1rem;"><?= $anio ?></span>
        <a href="?anio=<?= $anio + 1 ?>" class="btn btn-secondary btn-sm"><?= $anio + 1 ?> <i class="fa-solid fa-chevron-right"></i></a>
    </div>
</div>

<div class="card" style="overflow-x:auto;">
    <table class="tablero-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Tarea</th>
                <th>Frecuencia</th>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <th><?= $mesesCorto[$m - 1] ?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <?php $num = 0; ?>
            <?php foreach ($categorias as $catKey => $catInfo): ?>
                <?php if (empty($grupos[$catKey])) continue; ?>
                <tr class="tablero-seccion">
                    <td colspan="15"><i class="fa-solid <?= $catInfo['icon'] ?>" style="margin-right:6px;"></i> <?= $catInfo['label'] ?></td>
                </tr>
                <?php foreach ($grupos[$catKey] as $t): ?>
                    <?php $num++; ?>
                    <tr>
                        <td><?= $num ?></td>
                        <td><?= h($t['nombre']) ?></td>
                        <td><?= ucfirst($t['categoria']) ?></td>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <?php
                            $av = $avances[$t['id']][$m] ?? ['estatus' => 'pendiente', 'notas' => null];
                            $est = $av['estatus'];
                            $icon = $estatusIconos[$est] ?? '-';
                            ?>
                            <td onclick="openTableroModal(<?= $t['id'] ?>, '<?= h(addslashes($t['nombre'])) ?>', <?= $m ?>, <?= $anio ?>, '<?= $est ?>', '<?= h(addslashes($av['notas'] ?? '')) ?>')" title="<?= ucfirst($est) ?><?= $av['notas'] ? ' — ' . h($av['notas']) : '' ?>">
                                <span class="status-badge status-<?= $est ?>"><?= $icon ?></span>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Leyenda -->
<div class="card" style="margin-top:16px;">
    <h3 class="card-title"><i class="fa-solid fa-info-circle" style="margin-right:6px;color:var(--color-primary);"></i> Leyenda de estatus</h3>
    <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
        <span><span class="status-badge status-completado">✅</span> Completado</span>
        <span><span class="status-badge status-en_proceso">⏳</span> En proceso</span>
        <span><span class="status-badge status-no_realizado">❌</span> No realizado</span>
        <span><span class="status-badge status-pendiente">—</span> Pendiente</span>
        <span><span class="status-badge status-na">N/A</span> No aplica</span>
    </div>
</div>

<!-- Modal para actualizar estatus -->
<div id="modalTablero" class="modal">
    <div class="modal-content" style="max-width:440px;">
        <span class="modal-close" onclick="document.getElementById('modalTablero').classList.remove('modal-open')">&times;</span>
        <h3 style="margin-bottom:4px;">Actualizar avance</h3>
        <p id="tableroTareaNombre" style="font-weight:600;color:var(--color-primary);"></p>
        <p id="tableroMesLabel" style="font-size:0.85rem;color:var(--color-text-secondary);margin-bottom:12px;"></p>
        <input type="hidden" id="tableroTareaId">
        <input type="hidden" id="tableroAnio">
        <input type="hidden" id="tableroMes">

        <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:4px;">Estatus</label>
        <div class="modal-estatus-grid">
            <div class="modal-estatus-btn" data-estatus="completado" onclick="selectTableroEstatus(this)">✅ Completado</div>
            <div class="modal-estatus-btn" data-estatus="en_proceso" onclick="selectTableroEstatus(this)">⏳ En proceso</div>
            <div class="modal-estatus-btn" data-estatus="no_realizado" onclick="selectTableroEstatus(this)">❌ No realizado</div>
            <div class="modal-estatus-btn" data-estatus="na" onclick="selectTableroEstatus(this)">N/A</div>
        </div>

        <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:4px;">Notas (opcional)</label>
        <textarea id="tableroNotas" rows="2" style="width:100%;padding:8px;border:1.5px solid var(--color-border);border-radius:8px;font-size:0.85rem;"></textarea>

        <div class="form-actions" style="margin-top:16px;">
            <button type="button" class="btn btn-primary" onclick="saveTableroAvance()"><i class="fa-solid fa-save"></i> Guardar</button>
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalTablero').classList.remove('modal-open')">Cancelar</button>
        </div>
    </div>
</div>

<?php
$extraJs = ['control'];
require_once __DIR__ . '/../../includes/footer.php';
?>
