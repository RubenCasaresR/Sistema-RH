<?php
require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('control.incidencias.read');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'ID de incidencia no válido.');
    redirect(APP_URL . '/modules/control/incidencias.php');
}

$stmt = $db->prepare("SELECT i.*, u.username AS registrado_por_nombre FROM control_incidencias i LEFT JOIN users u ON u.id = i.registrado_por WHERE i.id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$inc = $stmt->fetch();
if (!$inc) {
    setFlash('error', 'Incidencia no encontrada.');
    redirect(APP_URL . '/modules/control/incidencias.php');
}

$tipoLabels = [
    'conflicto_interpersonal' => 'Conflicto interpersonal',
    'queja' => 'Queja',
    'falta_disciplinaria' => 'Falta disciplinaria',
    'incumplimiento_politica' => 'Incumplimiento de política',
    'otro' => 'Otro',
];
$resultadoLabels = [
    'resuelto' => 'Resuelto',
    'en_seguimiento' => 'En seguimiento',
    'escalado_direccion' => 'Escalado a dirección',
    'sin_resolucion' => 'Sin resolución',
];

$extraCss = ['control'];
$pageTitle = 'Incidencia #' . $inc['folio'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-exclamation-triangle" style="color:var(--color-warning);margin-right:8px;"></i> Incidencia #<?= $inc['folio'] ?></h2>
        <p class="greeting">Registrada el <?= formatDate($inc['fecha']) ?> por <?= h($inc['registrado_por_nombre']) ?></p>
    </div>
    <div class="header-actions">
        <?php if (can('control.incidencias.update')): ?>
            <a href="<?= APP_URL ?>/modules/control/incidencia_form.php?id=<?= $inc['id'] ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen"></i> Editar</a>
        <?php endif; ?>
        <?php if (can('control.incidencias.delete')): ?>
            <button type="button" class="btn btn-danger btn-sm" onclick="deleteIncidencia(<?= $inc['id'] ?>)"><i class="fa-solid fa-trash"></i> Eliminar</button>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/modules/control/incidencias.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(340px, 1fr));gap:16px;">
    <!-- Datos generales -->
    <div class="card">
        <h3 class="card-title"><i class="fa-solid fa-info-circle" style="margin-right:6px;color:var(--color-primary);"></i> Datos generales</h3>
        <dl class="data-list">
            <dt>Folio</dt><dd>#<?= $inc['folio'] ?></dd>
            <dt>Fecha</dt><dd><?= formatDate($inc['fecha']) ?></dd>
            <dt>Tipo</dt><dd><span class="badge-tipo badge-tipo-<?= $inc['tipo_incidencia'] ?>"><?= $tipoLabels[$inc['tipo_incidencia']] ?? $inc['tipo_incidencia'] ?></span></dd>
            <dt>Resultado</dt><dd><span class="badge-resultado badge-resultado-<?= $inc['resultado'] ?>"><?= $resultadoLabels[$inc['resultado']] ?? $inc['resultado'] ?></span></dd>
            <dt>Área</dt><dd><?= h($inc['area']) ?></dd>
            <dt>Fecha seguimiento</dt><dd><?= $inc['fecha_seguimiento'] ? formatDate($inc['fecha_seguimiento']) : '<span class="text-secondary">No definida</span>' ?></dd>
        </dl>
    </div>

    <!-- Partes involucradas -->
    <div class="card">
        <h3 class="card-title"><i class="fa-solid fa-people-group" style="margin-right:6px;color:var(--color-secondary);"></i> Partes involucradas</h3>
        <dl class="data-list">
            <dt>Persona(s)</dt><dd><?= h($inc['personas_involucradas']) ?></dd>
            <dt>Registrado por</dt><dd><?= h($inc['registrado_por_nombre']) ?></dd>
            <dt>Fecha de registro</dt><dd><?= formatDate($inc['created_at']) ?></dd>
        </dl>
    </div>
</div>

<!-- Descripción -->
<div class="card" style="margin-top:16px;">
    <h3 class="card-title"><i class="fa-solid fa-file-lines" style="margin-right:6px;color:var(--color-warning);"></i> Descripción del hecho</h3>
    <p style="line-height:1.7;"><?= nl2br(h($inc['descripcion'])) ?></p>
</div>

<!-- Atención -->
<?php if ($inc['atencion']): ?>
    <div class="card" style="margin-top:16px;">
        <h3 class="card-title"><i class="fa-solid fa-hand-holding-heart" style="margin-right:6px;color:var(--color-primary);"></i> ¿Cómo se atendió?</h3>
        <p style="line-height:1.7;"><?= nl2br(h($inc['atencion'])) ?></p>
    </div>
<?php endif; ?>

<?php
$extraJs = ['control'];
require_once __DIR__ . '/../../includes/footer.php';
?>
