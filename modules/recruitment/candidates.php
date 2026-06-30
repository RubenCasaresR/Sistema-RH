<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('recruitment.read');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$errors = [];
$vacancyId = (int)($_GET['vacancy_id'] ?? 0);

$stmtV = $db->prepare("SELECT * FROM vacancies WHERE id = :id LIMIT 1");
$stmtV->execute([':id' => $vacancyId]);
$vacancy = $stmtV->fetch();

if (!$vacancy) {
    setFlash('danger', 'Vacante no encontrada.');
    redirect(APP_URL . '/modules/recruitment/vacancies.php');
}

$puedeContratar = can('recruitment.hire');
$puedeEditar = can('recruitment.update');

$estatusLabels = ['recibido'=>'Recibido', 'revisado'=>'Revisado', 'entrevista'=>'Entrevista', 'evaluacion'=>'Evaluación', 'aceptado'=>'Aceptado', 'rechazado'=>'Rechazado', 'contratado'=>'Contratado'];
$estatusColors = ['recibido'=>'secondary', 'revisado'=>'info', 'entrevista'=>'warning', 'evaluacion'=>'info', 'aceptado'=>'success', 'rechazado'=>'danger', 'contratado'=>'primary'];
$estatusPermitidos = ['recibido', 'revisado', 'entrevista', 'evaluacion', 'aceptado', 'rechazado'];

$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 20;
$offset = ($pagina - 1) * $porPagina;

// Subir CV y crear candidato
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }

    $nombre = trim($_POST['nombre'] ?? '');
    $apellidoP = trim($_POST['apellido_paterno'] ?? '');
    $apellidoM = trim($_POST['apellido_materno'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $notas = trim($_POST['notas'] ?? '');

    if ($nombre === '') $errors[] = 'El nombre es obligatorio.';
    if ($apellidoP === '') $errors[] = 'Apellido paterno obligatorio.';
    if ($email === '') $errors[] = 'Email obligatorio.';

    $cvRuta = null;
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['cv'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) { $errors[] = 'CV debe ser PDF, DOC o DOCX.'; }
        if ($file['size'] > 5 * 1024 * 1024) { $errors[] = 'CV excede 5 MB.'; }

        if (count($errors) === 0) {
            $uniqueName = 'cv_' . uniqid() . '.' . $ext;
            $uploadDir = 'uploads' . DIRECTORY_SEPARATOR . 'cvs' . DIRECTORY_SEPARATOR;
            $fullPath = __DIR__ . '/../../' . $uploadDir . $uniqueName;
            if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                $cvRuta = $uploadDir . $uniqueName;
            } else { $errors[] = 'Error al subir CV.'; }
        }
    }

    if (count($errors) === 0) {
        try {
            $stmt = $db->prepare("INSERT INTO candidates (vacancy_id, nombre, apellido_paterno, apellido_materno, email, telefono, cv_ruta, notas, estatus) VALUES (:vid, :n, :ap, :am, :e, :t, :cv, :not, 'recibido')");
            $stmt->execute([':vid' => $vacancyId, ':n' => $nombre, ':ap' => $apellidoP, ':am' => $apellidoM, ':e' => $email, ':t' => $telefono, ':cv' => $cvRuta, ':not' => $notas]);
            setFlash('success', 'Candidato registrado.');
            redirect(APP_URL . '/modules/recruitment/candidates.php?vacancy_id=' . $vacancyId);
        } catch (PDOException $e) { error_log('Error candidato: ' . $e->getMessage()); $errors[] = 'Error al guardar.'; }
    }
}

// Cambiar estatus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        setFlash('danger', 'Token de seguridad inválido. Recarga la página e intenta de nuevo.');
        redirect(APP_URL . '/modules/recruitment/candidates.php?vacancy_id=' . $vacancyId);
    }

    $candidateId = (int)($_POST['candidate_id'] ?? 0);
    $nuevoEstatus = $_POST['estatus'] ?? '';
    $notasUpdate = trim($_POST['notas_candidato'] ?? '');

    // No permitir contratado si no tiene permiso
    $permitidos = $estatusPermitidos;
    if ($puedeContratar) {
        $permitidos[] = 'contratado';
    }

    if (in_array($nuevoEstatus, $permitidos, true)) {
        $stmt = $db->prepare("UPDATE candidates SET estatus = :est, notas = :not WHERE id = :id");
        $stmt->execute([':est' => $nuevoEstatus, ':not' => $notasUpdate, ':id' => $candidateId]);

        if ($nuevoEstatus === 'contratado' && $puedeContratar) {
            redirect(APP_URL . '/modules/recruitment/hire.php?candidate_id=' . $candidateId);
        }

        setFlash('success', 'Estatus actualizado.');
        redirect(APP_URL . '/modules/recruitment/candidates.php?vacancy_id=' . $vacancyId);
    }
}

// Total y paginación
$stmtCount = $db->prepare("SELECT COUNT(*) FROM candidates WHERE vacancy_id = :vid");
$stmtCount->execute([':vid' => $vacancyId]);
$totalCand = (int)$stmtCount->fetchColumn();

$stmtC = $db->prepare("SELECT * FROM candidates WHERE vacancy_id = :vid ORDER BY created_at DESC LIMIT $porPagina OFFSET $offset");
$stmtC->execute([':vid' => $vacancyId]);
$candidatos = $stmtC->fetchAll();

$csrfToken = generateCSRFToken();
$totalPaginas = max(1, (int)ceil($totalCand / $porPagina));
?>

<div class="page-header">
    <h2>Candidatos: <?= h($vacancy['titulo']) ?></h2>
    <a href="<?= APP_URL ?>/modules/recruitment/vacancies.php" class="btn btn-link">&larr; Vacantes</a>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
    <h3 class="card-title">Agregar candidato</h3>
    <form method="POST" action="" enctype="multipart/form-data" class="form" novalidate>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="form-row">
            <div class="form-group"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" required></div>
            <div class="form-group"><label for="apellido_paterno">Ap. paterno *</label><input type="text" id="apellido_paterno" name="apellido_paterno" required></div>
            <div class="form-group"><label for="apellido_materno">Ap. materno</label><input type="text" id="apellido_materno" name="apellido_materno"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="email">Email *</label><input type="email" id="email" name="email" required></div>
            <div class="form-group"><label for="telefono">Teléfono</label><input type="text" id="telefono" name="telefono"></div>
        </div>
        <div class="form-group"><label for="cv">CV (PDF, DOC, DOCX — máx 5 MB)</label><input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx"></div>
        <div class="form-group"><label for="notas">Notas</label><textarea id="notas" name="notas" rows="2"></textarea></div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Agregar</button></div>
    </form>
</div>

<div class="card">
    <h3 class="card-title">Candidatos (<?= $totalCand ?>)</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>CV</th>
                    <th>Estatus</th>
                    <th>Registro</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($candidatos) === 0): ?>
                    <tr><td colspan="6" class="empty-state">No hay candidatos registrados para esta vacante. Agrega el primero usando el formulario de la izquierda.</td></tr>
                <?php else: ?>
                    <?php foreach ($candidatos as $c): ?>
                        <tr>
                            <td><?= h($c['nombre'] . ' ' . $c['apellido_paterno']) ?></td>
                            <td><?= h($c['email']) ?></td>
                            <td>
                                <?php if ($c['cv_ruta']): ?>
                                    <a href="<?= APP_URL ?>/api/cv.php?candidate_id=<?= (int)$c['id'] ?>&token=<?= urlencode($csrfToken) ?>" target="_blank">Ver CV</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="" style="display:flex;gap:4px;align-items:center;"
                                      onsubmit="return confirmarCambioEstatus(this, '<?= h(addslashes($c['nombre'] . ' ' . $c['apellido_paterno'])) ?>')">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="candidate_id" value="<?= (int)$c['id'] ?>">
                                    <select name="estatus" class="form-group" style="min-width:120px;padding:4px 6px;font-size:0.8rem;">
                                        <?php foreach ($estatusLabels as $k => $v): ?>
                                            <?php if ($k === 'contratado' && !$puedeContratar) continue; ?>
                                            <option value="<?= $k ?>" <?= $c['estatus'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-ghost">Actualizar</button>
                                </form>
                            </td>
                            <td><?= formatDate($c['created_at']) ?></td>
                            <td class="actions-cell">
                                <?php if ($puedeContratar && $c['estatus'] === 'aceptado'): ?>
                                    <a href="<?= APP_URL ?>/modules/recruitment/hire.php?candidate_id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-primary">Contratar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($c['notas']): ?>
                            <tr style="background:#f8f9fa;"><td colspan="6" style="font-size:0.8rem;color:var(--color-text-secondary);padding:4px 12px;"><?= h($c['notas']) ?></td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($totalPaginas > 1): ?>
            <div style="display:flex;justify-content:center;gap:8px;margin-top:12px;">
                <?php if ($pagina > 1): ?>
                    <a href="?vacancy_id=<?= $vacancyId ?>&p=<?= $pagina - 1 ?>" class="btn btn-sm">&laquo; Anterior</a>
                <?php endif; ?>
                <span style="padding:6px 12px;">Página <?= $pagina ?> de <?= $totalPaginas ?> (<?= $totalCand ?> candidatos)</span>
                <?php if ($pagina < $totalPaginas): ?>
                    <a href="?vacancy_id=<?= $vacancyId ?>&p=<?= $pagina + 1 ?>" class="btn btn-sm">Siguiente &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmarCambioEstatus(form, nombre) {
    var sel = form.querySelector('select[name="estatus"]');
    var val = sel.options[sel.selectedIndex].text;
    return confirm('¿Cambiar estatus de ' + nombre + ' a "' + val + '"?');
}
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
