<?php
require_once __DIR__ . '/../../includes/session.php';
requireAuth();

$db = getDB();
$editMode = false;
$incidencia = null;
$errors = [];
$old = $_GET;

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    requirePermission('control.incidencias.update');
    $stmt = $db->prepare("SELECT * FROM control_incidencias WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $incidencia = $stmt->fetch();
    if (!$incidencia) {
        setFlash('error', 'Incidencia no encontrada.');
        redirect(APP_URL . '/modules/control/incidencias.php');
    }
    $editMode = true;
    $old = $incidencia;
} else {
    requirePermission('control.incidencias.create');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $errors[] = 'Token de seguridad inválido.';
    }

    $old = $_POST;
    $fecha     = trim($_POST['fecha'] ?? '');
    $personas  = trim($_POST['personas'] ?? '');
    $area      = trim($_POST['area'] ?? '');
    $tipo      = $_POST['tipo'] ?? '';
    $desc      = trim($_POST['descripcion'] ?? '');
    $atencion  = trim($_POST['atencion'] ?? '');
    $resultado = $_POST['resultado'] ?? 'en_seguimiento';
    $fechaSeg  = $_POST['fecha_seguimiento'] ?? '';

    if ($fecha === '') $errors[] = 'La fecha es obligatoria.';
    if ($personas === '') $errors[] = 'Las personas involucradas son obligatorias.';
    if ($area === '') $errors[] = 'El área es obligatoria.';
    if ($tipo === '') $errors[] = 'El tipo de incidencia es obligatorio.';
    if ($desc === '') $errors[] = 'La descripción es obligatoria.';

    if (empty($errors)) {
        if ($editMode) {
            $stmt = $db->prepare("UPDATE control_incidencias SET fecha = :fecha, personas_involucradas = :personas, area = :area, tipo_incidencia = :tipo, descripcion = :desc, atencion = :atencion, resultado = :resultado, fecha_seguimiento = :fechaSeg WHERE id = :id");
            $stmt->execute([
                ':fecha' => $fecha, ':personas' => $personas, ':area' => $area,
                ':tipo' => $tipo, ':desc' => $desc, ':atencion' => $atencion !== '' ? $atencion : null,
                ':resultado' => $resultado, ':fechaSeg' => $fechaSeg !== '' ? $fechaSeg : null, ':id' => $id,
            ]);
            logAudit('update', 'control_incidencia', $id, json_encode(['folio' => $incidencia['folio']]));
            setFlash('success', 'Incidencia actualizada exitosamente.');
            redirect(APP_URL . '/modules/control/incidencia_view.php?id=' . $id);
        } else {
            $maxFolio = $db->query("SELECT COALESCE(MAX(folio), 0) FROM control_incidencias")->fetchColumn();
            $folio = (int)$maxFolio + 1;
            $stmt = $db->prepare("INSERT INTO control_incidencias (folio, fecha, personas_involucradas, area, tipo_incidencia, descripcion, atencion, resultado, fecha_seguimiento, registrado_por) VALUES (:folio, :fecha, :personas, :area, :tipo, :desc, :atencion, :resultado, :fechaSeg, :uid)");
            $stmt->execute([
                ':folio' => $folio, ':fecha' => $fecha, ':personas' => $personas, ':area' => $area,
                ':tipo' => $tipo, ':desc' => $desc, ':atencion' => $atencion !== '' ? $atencion : null,
                ':resultado' => $resultado, ':fechaSeg' => $fechaSeg !== '' ? $fechaSeg : null, ':uid' => $_SESSION['user_id'],
            ]);
            $newId = (int)$db->lastInsertId();
            logAudit('create', 'control_incidencia', $newId, json_encode(['folio' => $folio]));
            setFlash('success', 'Incidencia registrada con folio #' . $folio . '.');
            redirect(APP_URL . '/modules/control/incidencia_view.php?id=' . $newId);
        }
    }
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

$csrfToken = generateCSRFToken();
$extraCss = ['control'];
$pageTitle = $editMode ? 'Editar Incidencia' : 'Nueva Incidencia';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-<?= $editMode ? 'pen' : 'plus' ?>" style="color:var(--color-primary);margin-right:8px;"></i> <?= $pageTitle ?></h2>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/modules/control/incidencias.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="" class="form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <fieldset>
            <legend>Datos generales</legend>
            <div class="form-row">
                <div class="form-group" style="flex:0 0 180px;">
                    <label for="fecha">Fecha *</label>
                    <input type="date" id="fecha" name="fecha" value="<?= h($old['fecha'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="tipo">Tipo de incidencia *</label>
                    <select id="tipo" name="tipo" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($tipoLabels as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($old['tipo_incidencia'] ?? $old['tipo'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="resultado">Resultado</label>
                    <select id="resultado" name="resultado">
                        <?php foreach ($resultadoLabels as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($old['resultado'] ?? 'en_seguimiento') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Partes involucradas</legend>
            <div class="form-row">
                <div class="form-group">
                    <label for="personas">Persona(s) involucrada(s) *</label>
                    <input type="text" id="personas" name="personas" value="<?= h($old['personas_involucradas'] ?? $old['personas'] ?? '') ?>" placeholder="Nombres separados por punto y coma" required>
                </div>
                <div class="form-group" style="flex:0 0 200px;">
                    <label for="area">Área / Departamento *</label>
                    <input type="text" id="area" name="area" value="<?= h($old['area'] ?? '') ?>" required>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Detalle</legend>
            <div class="form-group">
                <label for="descripcion">Descripción del hecho *</label>
                <textarea id="descripcion" name="descripcion" rows="4" required><?= h($old['descripcion'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="atencion">¿Cómo se atendió?</label>
                <textarea id="atencion" name="atencion" rows="3"><?= h($old['atencion'] ?? '') ?></textarea>
            </div>
        </fieldset>

        <fieldset>
            <legend>Seguimiento</legend>
            <div class="form-row">
                <div class="form-group" style="flex:0 0 200px;">
                    <label for="fecha_seguimiento">Fecha de seguimiento</label>
                    <input type="date" id="fecha_seguimiento" name="fecha_seguimiento" value="<?= h($old['fecha_seguimiento'] ?? '') ?>">
                </div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $editMode ? 'Actualizar' : 'Registrar' ?></button>
            <a href="<?= APP_URL ?>/modules/control/incidencias.php" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</div>

<?php
$extraJs = ['control'];
require_once __DIR__ . '/../../includes/footer.php';
?>
