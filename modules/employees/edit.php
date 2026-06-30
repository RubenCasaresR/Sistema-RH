<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('employees.update');

require_once __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('danger', 'Empleado no válido.');
    redirect(APP_URL . '/modules/employees/index.php');
}

$db = getDB();

$stmt = $db->prepare('SELECT * FROM employees WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$employee = $stmt->fetch();

if (!$employee) {
    setFlash('danger', 'Empleado no encontrado.');
    redirect(APP_URL . '/modules/employees/index.php');
}

$existingContacts = $db->prepare("SELECT id, nombre_completo, parentesco, telefono, telefono_alternativo, email, es_principal FROM emergency_contacts WHERE employee_id = :id ORDER BY es_principal DESC, created_at ASC");
$existingContacts->execute([':id' => $id]);
$emergencyContacts = $existingContacts->fetchAll();

$departments = $db->query("SELECT id, nombre FROM departments WHERE activo = 1 ORDER BY nombre")->fetchAll();
$positions = $db->query("SELECT id, nombre FROM positions WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Usuarios disponibles (incluir el actualmente vinculado + usuarios libres)
$stmtUsers = $db->prepare('
    SELECT u.id, u.username
    FROM users u
    LEFT JOIN employees e ON e.user_id = u.id
    WHERE (e.id IS NULL OR e.id = :id) AND u.activo = 1
');
$stmtUsers->execute([':id' => $id]);
$availableUsers = $stmtUsers->fetchAll();

$estados = [
    'Aguascalientes','Baja California','Baja California Sur','Campeche','Chiapas',
    'Chihuahua','Ciudad de México','Coahuila','Colima','Durango',
    'Estado de México','Guanajuato','Guerrero','Hidalgo','Jalisco',
    'Michoacán','Morelos','Nayarit','Nuevo León','Oaxaca',
    'Puebla','Querétaro','Quintana Roo','San Luis Potosí','Sinaloa',
    'Sonora','Tabasco','Tamaulipas','Tlaxcala','Veracruz',
    'Yucatán','Zacatecas',
];

$errors = [];
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $errors[] = 'Token de seguridad inválido.';
    }

    $data = [
        'nombre'           => trim($_POST['nombre'] ?? ''),
        'apellido_paterno' => trim($_POST['apellido_paterno'] ?? ''),
        'apellido_materno' => trim($_POST['apellido_materno'] ?? ''),
        'curp'             => strtoupper(trim($_POST['curp'] ?? '')),
        'rfc'              => strtoupper(trim($_POST['rfc'] ?? '')),
        'nss'              => trim($_POST['nss'] ?? ''),
        'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? null,
        'genero'           => $_POST['genero'] ?? null,
        'email'            => trim($_POST['email'] ?? ''),
        'telefono'         => trim($_POST['telefono'] ?? ''),
        'calle'            => trim($_POST['calle'] ?? ''),
        'numero_exterior'  => trim($_POST['numero_exterior'] ?? ''),
        'numero_interior'  => trim($_POST['numero_interior'] ?? ''),
        'colonia'          => trim($_POST['colonia'] ?? ''),
        'codigo_postal'    => trim($_POST['codigo_postal'] ?? ''),
        'ciudad'           => trim($_POST['ciudad'] ?? ''),
        'estado'           => $_POST['estado'] ?? '',
        'puesto'           => trim($_POST['puesto'] ?? ''),
        'departamento'     => trim($_POST['departamento'] ?? ''),
        'fecha_ingreso'    => $_POST['fecha_ingreso'] ?? null,
        'salario_base'     => $_POST['salario_base'] ?? null,
        'tipo_contrato'    => $_POST['tipo_contrato'] ?? 'Base',
        'user_id'          => !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null,
    ];

    if ($data['nombre'] === '') $errors[] = 'El nombre es obligatorio.';
    if ($data['apellido_paterno'] === '') $errors[] = 'El apellido paterno es obligatorio.';
    if (!validateCURP($data['curp'])) $errors[] = 'CURP inválido.';
    if (!validateRFC($data['rfc'])) $errors[] = 'RFC inválido.';
    if (!validateNSS($data['nss'])) $errors[] = 'NSS inválido (11 dígitos).';

    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo electrónico no es válido.';
    }

    $checkStmt = $db->prepare('SELECT COUNT(*) FROM employees WHERE (curp = :curp OR rfc = :rfc OR nss = :nss) AND id != :id AND activo = 1');
    $checkStmt->execute([':curp' => $data['curp'], ':rfc' => $data['rfc'], ':nss' => $data['nss'], ':id' => $id]);
    if ($checkStmt->fetchColumn() > 0) {
        $errors[] = 'Ya existe otro empleado activo con ese CURP, RFC o NSS.';
    }

    if ($data['email'] !== '') {
        $stmtEmail = $db->prepare('SELECT COUNT(*) FROM employees WHERE email = :email AND id != :id AND activo = 1');
        $stmtEmail->execute([':email' => $data['email'], ':id' => $id]);
        if ($stmtEmail->fetchColumn() > 0) {
            $errors[] = 'Ya existe otro empleado activo con ese correo electrónico.';
        }
    }

    if ($data['salario_base'] !== null && $data['salario_base'] !== '') {
        $data['salario_base'] = str_replace(',', '', $data['salario_base']);
        if (!is_numeric($data['salario_base']) || (float)$data['salario_base'] < 0) {
            $errors[] = 'El salario debe ser un número positivo.';
        }
    } else {
        $data['salario_base'] = null;
    }

    $fotoUrl = $employee['foto_url'];
    if (isset($_POST['eliminar_foto']) && $employee['foto_url']) {
        $oldPath = __DIR__ . '/../../' . $employee['foto_url'];
        if (file_exists($oldPath)) @unlink($oldPath);
        $fotoUrl = null;
    }
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 2 * 1024 * 1024;
        $mime = mime_content_type($_FILES['foto']['tmp_name']);
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

        if (in_array($mime, $allowedMimes, true) && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && $_FILES['foto']['size'] <= $maxSize) {
            $uniqueName = 'profile_' . uniqid() . '.' . $ext;
            $uploadDir = 'uploads' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR;
            $fullPath = __DIR__ . '/../../' . $uploadDir . $uniqueName;

            if (move_uploaded_file($_FILES['foto']['tmp_name'], $fullPath)) {
                if ($employee['foto_url']) {
                    $oldPath = __DIR__ . '/../../' . $employee['foto_url'];
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
                $fotoUrl = $uploadDir . $uniqueName;
            } else {
                $errors[] = 'Error al subir la foto.';
            }
        } else {
            $errors[] = 'La foto debe ser JPG, PNG o WebP (máx. 2 MB).';
        }
    }

    if (count($errors) === 0) {
        try {
            $stmt = $db->prepare('
                UPDATE employees SET
                    nombre = :nombre, apellido_paterno = :apellido_paterno,
                    apellido_materno = :apellido_materno, curp = :curp, rfc = :rfc, nss = :nss,
                    fecha_nacimiento = :fecha_nacimiento, genero = :genero,
                    email = :email, telefono = :telefono,
                    calle = :calle, numero_exterior = :numero_exterior,
                    numero_interior = :numero_interior, colonia = :colonia,
                    codigo_postal = :codigo_postal, ciudad = :ciudad, estado = :estado,
                    puesto = :puesto, departamento = :departamento,
                    fecha_ingreso = :fecha_ingreso, salario_base = :salario_base,
                    tipo_contrato = :tipo_contrato, foto_url = :foto,
                    user_id = :user_id
                WHERE id = :id
            ');

            $stmt->execute([
                ':nombre'           => $data['nombre'],
                ':apellido_paterno' => $data['apellido_paterno'],
                ':apellido_materno' => $data['apellido_materno'],
                ':curp'             => $data['curp'],
                ':rfc'              => $data['rfc'],
                ':nss'              => $data['nss'],
                ':fecha_nacimiento' => $data['fecha_nacimiento'],
                ':genero'           => $data['genero'],
                ':email'            => $data['email'],
                ':telefono'         => $data['telefono'],
                ':calle'            => $data['calle'],
                ':numero_exterior'  => $data['numero_exterior'],
                ':numero_interior'  => $data['numero_interior'],
                ':colonia'          => $data['colonia'],
                ':codigo_postal'    => $data['codigo_postal'],
                ':ciudad'           => $data['ciudad'],
                ':estado'           => $data['estado'],
                ':puesto'           => $data['puesto'],
                ':departamento'     => $data['departamento'],
                ':fecha_ingreso'    => $data['fecha_ingreso'],
                ':salario_base'     => $data['salario_base'],
                ':tipo_contrato'    => $data['tipo_contrato'],
                ':foto'             => $fotoUrl,
                ':user_id'          => $data['user_id'],
                ':id'               => $id,
            ]);

            // Guardar contactos de emergencia (con transacción)
            $db->beginTransaction();
            try {
                $db->prepare("DELETE FROM emergency_contacts WHERE employee_id = :id")->execute([':id' => $id]);
                $contactNames = $_POST['emergency_name'] ?? [];
                $contactRelations = $_POST['emergency_relation'] ?? [];
                $contactPhones = $_POST['emergency_phone'] ?? [];
                $contactAltPhones = $_POST['emergency_alt_phone'] ?? [];
                $contactEmails = $_POST['emergency_email'] ?? [];
                $contactPrincipal = $_POST['emergency_principal'] ?? [];

                $insertContact = $db->prepare("
                    INSERT INTO emergency_contacts (employee_id, nombre_completo, parentesco, telefono, telefono_alternativo, email, es_principal)
                    VALUES (:emp_id, :nombre, :parentesco, :telefono, :alt_telefono, :email, :principal)
                ");

                foreach ($contactNames as $i => $name) {
                    $name = trim($name);
                    if ($name === '') continue;
                    $insertContact->execute([
                        ':emp_id'      => $id,
                        ':nombre'      => $name,
                        ':parentesco'  => trim($contactRelations[$i] ?? ''),
                        ':telefono'    => trim($contactPhones[$i] ?? ''),
                        ':alt_telefono'=> trim($contactAltPhones[$i] ?? ''),
                        ':email'       => trim($contactEmails[$i] ?? ''),
                        ':principal'   => !empty($contactPrincipal[$i]) ? 1 : 0,
                    ]);
                }
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

            // Registrar cambio salarial si aplica
            $salarioAnterior = $employee['salario_base'];
            $salarioNuevo = $data['salario_base'];
            if ($salarioAnterior != $salarioNuevo) {
                $tipoCambio = $salarioAnterior === null ? 'alta' : (($salarioNuevo > $salarioAnterior) ? 'incremento' : 'decremento');
                $stmtSH = $db->prepare("
                    INSERT INTO salary_history (employee_id, salario_anterior, salario_nuevo, tipo_cambio, motivo, modificado_por)
                    VALUES (:emp_id, :anterior, :nuevo, :tipo, :motivo, :user_id)
                ");
                $stmtSH->execute([
                    ':emp_id'   => $id,
                    ':anterior' => $salarioAnterior,
                    ':nuevo'    => $salarioNuevo,
                    ':tipo'     => $tipoCambio,
                    ':motivo'   => trim($_POST['salary_reason'] ?? ''),
                    ':user_id'  => $_SESSION['user_id'],
                ]);
            }

            // Registrar cambio de contrato si aplica
            $contratoAnterior = $employee['tipo_contrato'];
            $contratoNuevo = $data['tipo_contrato'];
            if ($contratoAnterior !== $contratoNuevo) {
                $stmtCH = $db->prepare("
                    INSERT INTO contract_history (employee_id, tipo_contrato_anterior, tipo_contrato_nuevo, fecha_inicio, fecha_fin, motivo, modificado_por)
                    VALUES (:emp_id, :anterior, :nuevo, :fecha_inicio, :fecha_fin, :motivo, :user_id)
                ");
                $stmtCH->execute([
                    ':emp_id'       => $id,
                    ':anterior'     => $contratoAnterior,
                    ':nuevo'        => $contratoNuevo,
                    ':fecha_inicio' => $data['fecha_ingreso'],
                    ':fecha_fin'    => $_POST['contract_end'] ?? null,
                    ':motivo'       => trim($_POST['contract_reason'] ?? ''),
                    ':user_id'      => $_SESSION['user_id'],
                ]);
            }

            logAudit('update', 'employee', $id, json_encode([
                'nombre' => $data['nombre'] . ' ' . $data['apellido_paterno'],
            ]));

            setFlash('success', 'Empleado actualizado exitosamente.');
            redirect(APP_URL . '/modules/employees/view.php?id=' . $id);
        } catch (PDOException $e) {
            error_log('Error al actualizar empleado: ' . $e->getMessage());
            $errors[] = 'Error al guardar los cambios.';
        }
    }
}

$csrfToken = generateCSRFToken();
$emp = $old ?: $employee;
?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/components.css?v=<?= APP_VERSION ?>">

<div class="page-header">
    <h2>Editar: <?= h($emp['nombre'] . ' ' . $emp['apellido_paterno']) ?></h2>
    <a href="<?= APP_URL ?>/modules/employees/view.php?id=<?= $id ?>" class="btn btn-link">&larr; Volver al perfil</a>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="" class="form" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <fieldset>
            <legend>Datos personales</legend>
            <div class="form-row">
                <div class="form-group" style="flex:0 0 120px;text-align:center;">
                    <label>Foto</label>
                    <div class="photo-upload">
                        <div class="photo-preview" id="photoPreview" style="width:100px;height:100px;border-radius:50%;border:2px solid var(--color-border);margin:0 auto 8px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:var(--color-surface-alt);">
                            <?php if ($emp['foto_url']): ?>
                                <img src="<?= APP_URL ?>/<?= $emp['foto_url'] ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" style="font-size:0.8rem;width:100%;">
                        <?php if ($emp['foto_url']): ?>
                            <label style="font-size:0.75rem;color:var(--color-text-secondary);cursor:pointer;">
                                <input type="checkbox" name="eliminar_foto" value="1"> Eliminar foto
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="flex:1;display:flex;flex-direction:column;gap:12px;">
                    <div class="form-row">
                        <div class="form-group"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" value="<?= h($emp['nombre']) ?>" required></div>
                        <div class="form-group"><label for="apellido_paterno">Apellido paterno *</label><input type="text" id="apellido_paterno" name="apellido_paterno" value="<?= h($emp['apellido_paterno']) ?>" required></div>
                        <div class="form-group"><label for="apellido_materno">Apellido materno</label><input type="text" id="apellido_materno" name="apellido_materno" value="<?= h($emp['apellido_materno']) ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="curp">CURP *</label><input type="text" id="curp" name="curp" maxlength="18" value="<?= h($emp['curp']) ?>" required></div>
                        <div class="form-group"><label for="rfc">RFC *</label><input type="text" id="rfc" name="rfc" maxlength="13" value="<?= h($emp['rfc']) ?>" required></div>
                        <div class="form-group"><label for="nss">NSS *</label><input type="text" id="nss" name="nss" maxlength="11" value="<?= h($emp['nss']) ?>" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="fecha_nacimiento">Fecha de nacimiento</label><input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= h($emp['fecha_nacimiento']) ?>"></div>
                        <div class="form-group"><label for="genero">Género</label>
                            <select id="genero" name="genero">
                                <option value="">Seleccionar</option>
                                <option value="M" <?= $emp['genero'] === 'M' ? 'selected' : '' ?>>Masculino</option>
                                <option value="F" <?= $emp['genero'] === 'F' ? 'selected' : '' ?>>Femenino</option>
                                <option value="Otro" <?= $emp['genero'] === 'Otro' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Contacto y domicilio</legend>
            <div class="form-row">
                <div class="form-group"><label for="email">Correo</label><input type="email" id="email" name="email" value="<?= h($emp['email']) ?>"></div>
                <div class="form-group"><label for="telefono">Teléfono</label><input type="text" id="telefono" name="telefono" value="<?= h($emp['telefono']) ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group flex-2"><label for="calle">Calle</label><input type="text" id="calle" name="calle" value="<?= h($emp['calle']) ?>"></div>
                <div class="form-group"><label for="numero_exterior">No. ext</label><input type="text" id="numero_exterior" name="numero_exterior" value="<?= h($emp['numero_exterior']) ?>"></div>
                <div class="form-group"><label for="numero_interior">No. int</label><input type="text" id="numero_interior" name="numero_interior" value="<?= h($emp['numero_interior']) ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="colonia">Colonia</label><input type="text" id="colonia" name="colonia" value="<?= h($emp['colonia']) ?>"></div>
                <div class="form-group"><label for="codigo_postal">CP</label><input type="text" id="codigo_postal" name="codigo_postal" value="<?= h($emp['codigo_postal']) ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="ciudad">Ciudad</label><input type="text" id="ciudad" name="ciudad" value="<?= h($emp['ciudad']) ?>"></div>
                <div class="form-group"><label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="">Seleccionar</option>
                        <?php foreach ($estados as $e): ?>
                            <option value="<?= $e ?>" <?= ($emp['estado'] ?? '') === $e ? 'selected' : '' ?>><?= $e ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Relación laboral</legend>
            <div class="form-row">
                <div class="form-group">
                    <label for="puesto">Puesto</label>
                    <select id="puesto" name="puesto">
                        <option value="">Seleccionar o escribir</option>
                        <?php foreach ($positions as $p): ?>
                            <option value="<?= h($p['nombre']) ?>" <?= ($emp['puesto'] ?? '') === $p['nombre'] ? 'selected' : '' ?>><?= h($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="departamento">Departamento</label>
                    <select id="departamento" name="departamento">
                        <option value="">Seleccionar</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= h($d['nombre']) ?>" <?= ($emp['departamento'] ?? '') === $d['nombre'] ? 'selected' : '' ?>><?= h($d['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="fecha_ingreso">Fecha de ingreso</label><input type="date" id="fecha_ingreso" name="fecha_ingreso" value="<?= h($emp['fecha_ingreso']) ?>"></div>
                <div class="form-group"><label for="salario_base">Salario base ($)</label><input type="text" id="salario_base" name="salario_base" value="<?= h($emp['salario_base']) ?>"></div>
                <div class="form-group"><label for="tipo_contrato">Contrato</label>
                    <select id="tipo_contrato" name="tipo_contrato">
                        <?php foreach (['Base','Confianza','Temporal','Honorarios','Outsourcing'] as $tc): ?>
                            <option value="<?= $tc ?>" <?= $emp['tipo_contrato'] === $tc ? 'selected' : '' ?>><?= $tc ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="user_id">Usuario vinculado</label>
                <select id="user_id" name="user_id">
                    <option value="">Sin usuario</option>
                    <?php foreach ($availableUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $emp['user_id'] == $u['id'] ? 'selected' : '' ?>><?= h($u['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>

        <!-- Contactos de emergencia -->
        <fieldset>
            <legend>Contactos de emergencia</legend>
            <div id="emergency-contacts">
                <?php if (empty($emergencyContacts)): ?>
                <div class="contact-row" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;padding:8px;background:var(--color-surface-alt);border-radius:var(--radius);">
                    <div class="form-group" style="flex:2;min-width:160px;margin:0;"><label style="font-size:0.8rem;">Nombre completo</label><input type="text" name="emergency_name[]" placeholder="Nombre del contacto"></div>
                    <div class="form-group" style="flex:1;min-width:100px;margin:0;"><label style="font-size:0.8rem;">Parentesco</label><input type="text" name="emergency_relation[]" placeholder="Ej. Esposo(a)"></div>
                    <div class="form-group" style="flex:1;min-width:120px;margin:0;"><label style="font-size:0.8rem;">Teléfono</label><input type="text" name="emergency_phone[]" placeholder="55 1234 5678"></div>
                    <div class="form-group" style="flex:1;min-width:120px;margin:0;"><label style="font-size:0.8rem;">Tel. alternativo</label><input type="text" name="emergency_alt_phone[]" placeholder="Opcional"></div>
                    <div class="form-group" style="flex:1;min-width:140px;margin:0;"><label style="font-size:0.8rem;">Email</label><input type="email" name="emergency_email[]" placeholder="Opcional"></div>
                    <div class="form-group" style="flex:0 0 50px;margin:0;text-align:center;"><label style="font-size:0.8rem;">Principal</label><input type="checkbox" name="emergency_principal[]" value="1" checked></div>
                    <div style="display:flex;align-items:end;padding-bottom:2px;">
                        <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.contact-row').remove()" style="font-size:0.8rem;">✕</button>
                    </div>
                </div>
                <?php else: ?>
                    <?php foreach ($emergencyContacts as $ec): ?>
                    <div class="contact-row" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;padding:8px;background:var(--color-surface-alt);border-radius:var(--radius);">
                        <div class="form-group" style="flex:2;min-width:160px;margin:0;"><label style="font-size:0.8rem;">Nombre completo</label><input type="text" name="emergency_name[]" value="<?= h($ec['nombre_completo']) ?>"></div>
                        <div class="form-group" style="flex:1;min-width:100px;margin:0;"><label style="font-size:0.8rem;">Parentesco</label><input type="text" name="emergency_relation[]" value="<?= h($ec['parentesco']) ?>"></div>
                        <div class="form-group" style="flex:1;min-width:120px;margin:0;"><label style="font-size:0.8rem;">Teléfono</label><input type="text" name="emergency_phone[]" value="<?= h($ec['telefono']) ?>"></div>
                        <div class="form-group" style="flex:1;min-width:120px;margin:0;"><label style="font-size:0.8rem;">Tel. alternativo</label><input type="text" name="emergency_alt_phone[]" value="<?= h($ec['telefono_alternativo']) ?>"></div>
                        <div class="form-group" style="flex:1;min-width:140px;margin:0;"><label style="font-size:0.8rem;">Email</label><input type="email" name="emergency_email[]" value="<?= h($ec['email']) ?>"></div>
                        <div class="form-group" style="flex:0 0 50px;margin:0;text-align:center;"><label style="font-size:0.8rem;">Principal</label><input type="checkbox" name="emergency_principal[]" value="1" <?= $ec['es_principal'] ? 'checked' : '' ?>></div>
                        <div style="display:flex;align-items:end;padding-bottom:2px;">
                            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.contact-row').remove()" style="font-size:0.8rem;">✕</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="addContactRow()" style="margin-top:4px;">+ Agregar contacto</button>
        </fieldset>

        <!-- Campos condicionales para historial -->
        <div id="salary-reason-field" style="display:none;" class="form-group">
            <label for="salary_reason">Motivo del cambio salarial</label>
            <input type="text" id="salary_reason" name="salary_reason" placeholder="Ej. Aumento por méritos, ajuste anual, etc.">
        </div>
        <div id="contract-reason-field" style="display:none;" class="form-group">
            <label for="contract_reason">Motivo del cambio de contrato</label>
            <input type="text" id="contract_reason" name="contract_reason" placeholder="Ej. Cambio de puesto, renovación, etc.">
        </div>
        <div id="contract-end-field" style="display:none;" class="form-group">
            <label for="contract_end">Fecha de fin de contrato</label>
            <input type="date" id="contract_end" name="contract_end">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="<?= APP_URL ?>/modules/employees/view.php?id=<?= $id ?>" class="btn btn-link">Cancelar</a>
        </div>
    </form>
</div>

<script>
document.getElementById('foto')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(ev) {
        const preview = document.getElementById('photoPreview');
        preview.innerHTML = '<img src="' + ev.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
        preview.style.border = '2px solid var(--color-primary)';
    };
    reader.readAsDataURL(file);
});

function addContactRow() {
    const container = document.getElementById('emergency-contacts');
    const row = document.createElement('div');
    row.className = 'contact-row';
    row.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;padding:8px;background:var(--color-surface-alt);border-radius:var(--radius);';
    row.innerHTML = `
        <div class="form-group" style="flex:2;min-width:160px;margin:0;"><label style="font-size:0.8rem;">Nombre completo</label><input type="text" name="emergency_name[]" placeholder="Nombre del contacto"></div>
        <div class="form-group" style="flex:1;min-width:100px;margin:0;"><label style="font-size:0.8rem;">Parentesco</label><input type="text" name="emergency_relation[]" placeholder="Ej. Esposo(a)"></div>
        <div class="form-group" style="flex:1;min-width:120px;margin:0;"><label style="font-size:0.8rem;">Teléfono</label><input type="text" name="emergency_phone[]" placeholder="55 1234 5678"></div>
        <div class="form-group" style="flex:1;min-width:120px;margin:0;"><label style="font-size:0.8rem;">Tel. alternativo</label><input type="text" name="emergency_alt_phone[]" placeholder="Opcional"></div>
        <div class="form-group" style="flex:1;min-width:140px;margin:0;"><label style="font-size:0.8rem;">Email</label><input type="email" name="emergency_email[]" placeholder="Opcional"></div>
        <div class="form-group" style="flex:0 0 50px;margin:0;text-align:center;"><label style="font-size:0.8rem;">Principal</label><input type="checkbox" name="emergency_principal[]" value="1"></div>
        <div style="display:flex;align-items:end;padding-bottom:2px;">
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.contact-row').remove()" style="font-size:0.8rem;">✕</button>
        </div>
    `;
    container.appendChild(row);
}

// Mostrar/ocultar motivo del cambio salarial
const salarioBase = document.getElementById('salario_base');
const salarioOriginal = <?= json_encode($employee['salario_base']) ?>;
if (salarioBase) {
    salarioBase.addEventListener('change', function() {
        document.getElementById('salary-reason-field').style.display = (parseFloat(this.value) !== parseFloat(salarioOriginal)) ? 'block' : 'none';
    });
}

// Mostrar/ocultar motivo del cambio de contrato y fecha de fin
const tipoContrato = document.getElementById('tipo_contrato');
const contratoOriginal = <?= json_encode($employee['tipo_contrato']) ?>;
if (tipoContrato) {
    tipoContrato.addEventListener('change', function() {
        const changed = this.value !== contratoOriginal;
        document.getElementById('contract-reason-field').style.display = changed ? 'block' : 'none';
        document.getElementById('contract-end-field').style.display = (changed && this.value === 'Temporal') ? 'block' : 'none';
    });
}
</script>

<?php
$extraJs = ['validations'];
require_once __DIR__ . '/../../includes/footer.php';
?>
