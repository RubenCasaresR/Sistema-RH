<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('recruitment.read');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$errors = [];
$puedeCrear = can('recruitment.create');
$puedeEditar = can('recruitment.update');

$estatusLabels = ['abierta' => 'Abierta', 'en_proceso' => 'En proceso', 'cerrada' => 'Cerrada', 'cancelada' => 'Cancelada'];
$estatusColors = ['abierta' => 'success', 'en_proceso' => 'info', 'cerrada' => 'secondary', 'cancelada' => 'danger'];

$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 20;
$offset = ($pagina - 1) * $porPagina;

// Crear / editar vacante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }

    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $requisitos = trim($_POST['requisitos'] ?? '');
    $departamento = trim($_POST['departamento'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $salarioMin = $_POST['salario_min'] ?? null;
    $salarioMax = $_POST['salario_max'] ?? null;
    $estatus = $_POST['estatus'] ?? 'abierta';

    if ($titulo === '') $errors[] = 'El título es obligatorio.';

    if (count($errors) === 0) {
        try {
            if ($_POST['action'] === 'create' && $puedeCrear) {
                $stmt = $db->prepare("INSERT INTO vacancies (titulo, descripcion, requisitos, departamento, ubicacion, salario_min, salario_max, estatus, created_by) VALUES (:t, :d, :r, :dep, :ub, :smin, :smax, :est, :uid)");
                $stmt->execute([':t' => $titulo, ':d' => $descripcion, ':r' => $requisitos, ':dep' => $departamento, ':ub' => $ubicacion, ':smin' => $salarioMin ?: null, ':smax' => $salarioMax ?: null, ':est' => $estatus, ':uid' => (int)$_SESSION['user_id']]);
                setFlash('success', 'Vacante creada.');
            } elseif ($_POST['action'] === 'update' && $puedeEditar) {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare("UPDATE vacancies SET titulo=:t, descripcion=:d, requisitos=:r, departamento=:dep, ubicacion=:ub, salario_min=:smin, salario_max=:smax, estatus=:est WHERE id=:id");
                $stmt->execute([':t' => $titulo, ':d' => $descripcion, ':r' => $requisitos, ':dep' => $departamento, ':ub' => $ubicacion, ':smin' => $salarioMin ?: null, ':smax' => $salarioMax ?: null, ':est' => $estatus, ':id' => $id]);
                setFlash('success', 'Vacante actualizada.');
            }
            redirect(APP_URL . '/modules/recruitment/vacancies.php');
        } catch (PDOException $e) {
            error_log('Error vacante: ' . $e->getMessage()); $errors[] = 'Error al guardar.';
        }
    }
}

$stmtCount = $db->query("SELECT COUNT(*) FROM vacancies")->fetchColumn();
$totalVac = (int)$stmtCount;

$stmt = $db->prepare("
    SELECT v.*, u.username AS creador, COALESCE(cnt.total, 0) AS total_candidatos
    FROM vacancies v
    INNER JOIN users u ON u.id = v.created_by
    LEFT JOIN (SELECT vacancy_id, COUNT(*) AS total FROM candidates GROUP BY vacancy_id) cnt ON cnt.vacancy_id = v.id
    ORDER BY v.created_at DESC
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute();
$vacantes = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
$totalPaginas = max(1, (int)ceil($totalVac / $porPagina));
?>

<div class="page-header">
    <h2>Vacantes</h2>
    <?php if ($puedeCrear): ?>
        <button class="btn btn-primary" onclick="document.getElementById('modalCreate').classList.add('modal-open')">+ Nueva vacante</button>
    <?php endif; ?>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Departamento</th>
                    <th>Ubicación</th>
                    <th>Candidatos</th>
                    <th>Estatus</th>
                    <th>Creada</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($vacantes) === 0): ?>
                    <tr><td colspan="7" class="empty-state">
                        Sin vacantes registradas.
                        <?php if ($puedeCrear): ?>
                            <br><a href="#" onclick="document.getElementById('modalCreate').classList.add('modal-open');return false;" class="btn btn-link">Crear la primera vacante</a>
                        <?php endif; ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($vacantes as $v):
                        $esInactiva = in_array($v['estatus'], ['cerrada', 'cancelada'], true);
                    ?>
                        <tr style="<?= $esInactiva ? 'opacity:0.6;' : '' ?>">
                            <td><strong><?= h($v['titulo']) ?></strong></td>
                            <td><?= h($v['departamento'] ?? '—') ?></td>
                            <td><?= h($v['ubicacion'] ?? '—') ?></td>
                            <td>
                                <a href="<?= APP_URL ?>/modules/recruitment/candidates.php?vacancy_id=<?= (int)$v['id'] ?>">
                                    <?= (int)$v['total_candidatos'] ?> candidatos
                                </a>
                            </td>
                            <td><span class="badge badge-<?= $estatusColors[$v['estatus']] ?>"><?= $estatusLabels[$v['estatus']] ?></span></td>
                            <td><?= formatDate($v['created_at']) ?></td>
                            <td class="actions-cell">
                                <a href="<?= APP_URL ?>/modules/recruitment/candidates.php?vacancy_id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-ghost">Candidatos</a>
                                <?php if ($puedeEditar): ?>
                                    <button class="btn btn-sm btn-ghost" onclick="editarVacante(<?= (int)$v['id'] ?>)">Editar</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($totalPaginas > 1): ?>
            <div style="display:flex;justify-content:center;gap:8px;margin-top:12px;">
                <?php if ($pagina > 1): ?>
                    <a href="?p=<?= $pagina - 1 ?>" class="btn btn-sm">&laquo; Anterior</a>
                <?php endif; ?>
                <span style="padding:6px 12px;">Página <?= $pagina ?> de <?= $totalPaginas ?> (<?= $totalVac ?> vacantes)</span>
                <?php if ($pagina < $totalPaginas): ?>
                    <a href="?p=<?= $pagina + 1 ?>" class="btn btn-sm">Siguiente &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal crear vacante -->
<div id="modalCreate" class="modal">
    <div class="modal-content" style="max-width:600px;">
        <span class="modal-close" onclick="this.closest('.modal').classList.remove('modal-open')">&times;</span>
        <h3>Nueva vacante</h3>
        <form method="POST" action="" class="form" novalidate>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="form-group">
                <label for="titulo">Título *</label>
                <input type="text" id="titulo" name="titulo" required maxlength="150">
            </div>
            <div class="form-row">
                <div class="form-group"><label for="departamento">Departamento</label><input type="text" id="departamento" name="departamento"></div>
                <div class="form-group"><label for="ubicacion">Ubicación</label><input type="text" id="ubicacion" name="ubicacion"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="salario_min">Salario mínimo ($)</label><input type="text" id="salario_min" name="salario_min"></div>
                <div class="form-group"><label for="salario_max">Salario máximo ($)</label><input type="text" id="salario_max" name="salario_max"></div>
            </div>
            <div class="form-group"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="4"></textarea></div>
            <div class="form-group"><label for="requisitos">Requisitos</label><textarea id="requisitos" name="requisitos" rows="4"></textarea></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary">Crear</button></div>
        </form>
    </div>
</div>

<!-- Modal editar vacante -->
<div id="modalEdit" class="modal">
    <div class="modal-content" style="max-width:600px;">
        <span class="modal-close" onclick="this.closest('.modal').classList.remove('modal-open')">&times;</span>
        <h3>Editar vacante</h3>
        <form method="POST" action="" class="form" novalidate id="formEdit">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group"><label for="edit_titulo">Título *</label><input type="text" id="edit_titulo" name="titulo" required maxlength="150"></div>
            <div class="form-row">
                <div class="form-group"><label for="edit_departamento">Departamento</label><input type="text" id="edit_departamento" name="departamento"></div>
                <div class="form-group"><label for="edit_ubicacion">Ubicación</label><input type="text" id="edit_ubicacion" name="ubicacion"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="edit_salario_min">Salario mín ($)</label><input type="text" id="edit_salario_min" name="salario_min"></div>
                <div class="form-group"><label for="edit_salario_max">Salario máx ($)</label><input type="text" id="edit_salario_max" name="salario_max"></div>
            </div>
            <div class="form-group"><label for="edit_estatus">Estatus</label>
                <select id="edit_estatus" name="estatus">
                    <?php foreach ($estatusLabels as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="edit_descripcion">Descripción</label><textarea id="edit_descripcion" name="descripcion" rows="3"></textarea></div>
            <div class="form-group"><label for="edit_requisitos">Requisitos</label><textarea id="edit_requisitos" name="requisitos" rows="3"></textarea></div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="btnGuardarEdit">Guardar</button>
                <span id="editLoading" style="display:none;font-size:0.85rem;color:var(--color-text-secondary);">Cargando...</span>
            </div>
        </form>
    </div>
</div>

<script>
function editarVacante(id) {
    var loading = document.getElementById('editLoading');
    var btn = document.getElementById('btnGuardarEdit');
    loading.style.display = 'inline';
    btn.disabled = true;
    fetch(APP_URL + '/api/recruitment.php?action=get_vacancy&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            loading.style.display = 'none';
            btn.disabled = false;
            if (d.success) {
                var v = d.data;
                document.getElementById('edit_id').value = v.id;
                document.getElementById('edit_titulo').value = v.titulo;
                document.getElementById('edit_departamento').value = v.departamento || '';
                document.getElementById('edit_ubicacion').value = v.ubicacion || '';
                document.getElementById('edit_salario_min').value = v.salario_min || '';
                document.getElementById('edit_salario_max').value = v.salario_max || '';
                document.getElementById('edit_estatus').value = v.estatus;
                document.getElementById('edit_descripcion').value = v.descripcion || '';
                document.getElementById('edit_requisitos').value = v.requisitos || '';
                document.getElementById('modalEdit').classList.add('modal-open');
            } else {
                alert('Error al cargar la vacante.');
            }
        })
        .catch(function() {
            loading.style.display = 'none';
            btn.disabled = false;
            alert('Error de conexión al cargar la vacante.');
        });
}
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
