<?php
/**
 * Contratación: conversión de candidato a empleado.
 * Toma los datos del candidato y crea el registro en employees.
 */

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('recruitment.hire');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$errors = [];
$candidateId = (int)($_GET['candidate_id'] ?? 0);

$stmtC = $db->prepare("SELECT c.*, v.titulo AS vacante_titulo FROM candidates c INNER JOIN vacancies v ON v.id = c.vacancy_id WHERE c.id = :id LIMIT 1");
$stmtC->execute([':id' => $candidateId]);
$candidate = $stmtC->fetch();

if (!$candidate) {
    setFlash('danger', 'Candidato no encontrado.');
    redirect(APP_URL . '/modules/recruitment/vacancies.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) { $errors[] = 'Token inválido.'; }

    $puesto = trim($_POST['puesto'] ?? $candidate['vacante_titulo']);
    $departamento = trim($_POST['departamento'] ?? '');
    $fechaIngreso = $_POST['fecha_ingreso'] ?? date('Y-m-d');
    $salarioBase = $_POST['salario_base'] ?? null;
    $tipoContrato = $_POST['tipo_contrato'] ?? 'Base';
    $curp = strtoupper(trim($_POST['curp'] ?? ''));
    $rfc = strtoupper(trim($_POST['rfc'] ?? ''));
    $nss = trim($_POST['nss'] ?? '');

    if (!validateCURP($curp)) $errors[] = 'CURP inválido.';
    if (!validateRFC($rfc)) $errors[] = 'RFC inválido.';
    if (!validateNSS($nss)) $errors[] = 'NSS inválido.';
    if ($puesto === '') $errors[] = 'Puesto obligatorio.';

    if (count($errors) === 0) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO employees (nombre, apellido_paterno, apellido_materno, curp, rfc, nss, email, telefono, puesto, departamento, fecha_ingreso, salario_base, tipo_contrato, activo)
                VALUES (:n, :ap, :am, :curp, :rfc, :nss, :email, :tel, :p, :dep, :fi, :sal, :tc, 1)
            ");
            $stmt->execute([
                ':n'    => $candidate['nombre'], ':ap' => $candidate['apellido_paterno'], ':am' => $candidate['apellido_materno'],
                ':curp' => $curp, ':rfc' => $rfc, ':nss' => $nss,
                ':email' => $candidate['email'], ':tel' => $candidate['telefono'],
                ':p'    => $puesto, ':dep' => $departamento,
                ':fi'   => $fechaIngreso, ':sal' => $salarioBase ?: null, ':tc' => $tipoContrato
            ]);
            $newEmployeeId = (int)$db->lastInsertId();

            // Actualizar estatus del candidato
            $stmtUp = $db->prepare("UPDATE candidates SET estatus = 'contratado', notas = CONCAT(COALESCE(notas,''), '\nContratado como empleado #', :eid) WHERE id = :id");
            $stmtUp->execute([':eid' => $newEmployeeId, ':id' => $candidateId]);

            $db->commit();

            logAudit('hire', 'candidate', $candidateId, json_encode([
                'candidato'   => $candidate['nombre'] . ' ' . $candidate['apellido_paterno'],
                'employee_id' => $newEmployeeId,
                'puesto'      => $puesto,
                'vacante'     => $candidate['vacante_titulo'],
            ]));

            setFlash('success', 'Candidato contratado exitosamente como empleado.');
            redirect(APP_URL . '/modules/employees/view.php?id=' . $newEmployeeId);
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Error contratación: ' . $e->getMessage());

            // Verificar si es por duplicado
            if ($e->getCode() == 23000) {
                $errors[] = 'Ya existe un empleado con ese CURP, RFC o NSS.';
            } else {
                $errors[] = 'Error al contratar. Intente de nuevo.';
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <h2>Contratar: <?= h($candidate['nombre'] . ' ' . $candidate['apellido_paterno']) ?></h2>
    <a href="<?= APP_URL ?>/modules/recruitment/candidates.php?vacancy_id=<?= (int)$candidate['vacancy_id'] ?>" class="btn btn-link">&larr; Volver</a>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
    <h3 class="card-title">Datos del candidato</h3>
    <p><strong>Nombre:</strong> <?= h($candidate['nombre'] . ' ' . $candidate['apellido_paterno'] . ($candidate['apellido_materno'] ? ' ' . $candidate['apellido_materno'] : '')) ?></p>
    <p><strong>Email:</strong> <?= h($candidate['email']) ?></p>
    <p><strong>Teléfono:</strong> <?= h($candidate['telefono'] ?? '—') ?></p>
    <p><strong>Vacante:</strong> <?= h($candidate['vacante_titulo']) ?></p>

    <form method="POST" action="" class="form" novalidate style="margin-top:20px;">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="form-row">
            <div class="form-group"><label for="curp">CURP *</label><input type="text" id="curp" name="curp" maxlength="18" required></div>
            <div class="form-group"><label for="rfc">RFC *</label><input type="text" id="rfc" name="rfc" maxlength="13" required></div>
            <div class="form-group"><label for="nss">NSS *</label><input type="text" id="nss" name="nss" maxlength="11" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="puesto">Puesto *</label><input type="text" id="puesto" name="puesto" value="<?= h($candidate['vacante_titulo']) ?>" required></div>
            <div class="form-group"><label for="departamento">Departamento</label><input type="text" id="departamento" name="departamento"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="fecha_ingreso">Fecha de ingreso *</label><input type="date" id="fecha_ingreso" name="fecha_ingreso" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-group"><label for="salario_base">Salario base ($)</label><input type="text" id="salario_base" name="salario_base"></div>
            <div class="form-group"><label for="tipo_contrato">Contrato</label>
                <select id="tipo_contrato" name="tipo_contrato">
                    <?php foreach (['Base','Confianza','Temporal','Honorarios','Outsourcing','Becario'] as $tc): ?>
                        <option value="<?= $tc ?>"><?= $tc ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Confirmar contratación</button>
            <a href="<?= APP_URL ?>/modules/recruitment/candidates.php?vacancy_id=<?= (int)$candidate['vacancy_id'] ?>" class="btn btn-link">Cancelar</a>
        </div>
    </form>
</div>

<?php
$extraJs = ['validations'];
require_once __DIR__ . '/../../includes/footer.php';
?>
