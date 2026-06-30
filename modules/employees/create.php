<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('employees.create');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$stmtUsers = $db->query('
    SELECT u.id, u.username
    FROM users u
    LEFT JOIN employees e ON e.user_id = u.id
    WHERE e.id IS NULL AND u.activo = 1
');
$availableUsers = $stmtUsers->fetchAll();

$departments = $db->query("SELECT id, nombre FROM departments WHERE activo = 1 ORDER BY nombre")->fetchAll();
$positions = $db->query("SELECT id, nombre FROM positions WHERE activo = 1 ORDER BY nombre")->fetchAll();

$roles = array_keys(getRolePermissions());

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
        'user_id'          => !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null,
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
    ];

    if ($data['nombre'] === '') $errors[] = 'El nombre es obligatorio.';
    if ($data['apellido_paterno'] === '') $errors[] = 'El apellido paterno es obligatorio.';
    if (!validateCURP($data['curp'])) $errors[] = 'El CURP no tiene un formato válido (18 caracteres).';
    if (!validateRFC($data['rfc'])) $errors[] = 'El RFC no tiene un formato válido.';
    if (!validateNSS($data['nss'])) $errors[] = 'El NSS debe contener exactamente 11 dígitos.';

    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo electrónico no es válido.';
    }

    $checkStmt = $db->prepare('SELECT COUNT(*) FROM employees WHERE (curp = :curp OR rfc = :rfc OR nss = :nss) AND activo = 1');
    $checkStmt->execute([':curp' => $data['curp'], ':rfc' => $data['rfc'], ':nss' => $data['nss']]);
    if ($checkStmt->fetchColumn() > 0) {
        $errors[] = 'Ya existe un empleado activo con ese CURP, RFC o NSS.';
    }

    if ($data['email'] !== '') {
        $stmtEmail = $db->prepare('SELECT COUNT(*) FROM employees WHERE email = :email AND activo = 1');
        $stmtEmail->execute([':email' => $data['email']]);
        if ($stmtEmail->fetchColumn() > 0) {
            $errors[] = 'Ya existe un empleado activo con ese correo electrónico.';
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

    // Crear nuevo usuario si se seleccionó la opción
    $newUserId = null;
    if ($_POST['user_id'] === 'new') {
        $newUsername = trim($_POST['new_username'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';
        $newRole = $_POST['new_role'] ?? 'Empleado';

        if ($newUsername === '') {
            $errors[] = 'El nombre de usuario es obligatorio.';
        } else {
            $checkUser = $db->prepare("SELECT id FROM users WHERE username = :u");
            $checkUser->execute([':u' => $newUsername]);
            if ($checkUser->fetch()) {
                $errors[] = 'El nombre de usuario ya existe.';
            }
        }
        if ($newPassword === '') {
            $errors[] = 'La contraseña es obligatoria.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($newPassword !== $newPasswordConfirm) {
            $errors[] = 'Las contraseñas no coinciden.';
        }
        if (!in_array($newRole, $roles, true)) {
            $errors[] = 'Rol seleccionado no válido.';
        }

        if (count($errors) === 0) {
            $insertUser = $db->prepare("INSERT INTO users (username, password, role, activo) VALUES (:u, :p, :r, 1)");
            $insertUser->execute([
                ':u' => $newUsername,
                ':p' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':r' => $newRole,
            ]);
            $newUserId = (int)$db->lastInsertId();
        }
    }

    $fotoUrl = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $fotoResult = handlePhotoUpload($_FILES['foto'], $errors);
        if ($fotoResult) $fotoUrl = $fotoResult;
    }

    if (count($errors) === 0) {
        try {
            $stmt = $db->prepare('
                INSERT INTO employees (
                    user_id, nombre, apellido_paterno, apellido_materno,
                    curp, rfc, nss, fecha_nacimiento, genero,
                    email, telefono, calle, numero_exterior, numero_interior,
                    colonia, codigo_postal, ciudad, estado,
                    puesto, departamento, fecha_ingreso, salario_base, tipo_contrato,
                    foto_url
                ) VALUES (
                    :user_id, :nombre, :apellido_paterno, :apellido_materno,
                    :curp, :rfc, :nss, :fecha_nacimiento, :genero,
                    :email, :telefono, :calle, :numero_exterior, :numero_interior,
                    :colonia, :codigo_postal, :ciudad, :estado,
                    :puesto, :departamento, :fecha_ingreso, :salario_base, :tipo_contrato,
                    :foto
                )
            ');

            $stmt->execute([
                ':user_id'          => $newUserId ?? $data['user_id'],
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
            ]);

            $newId = (int)$db->lastInsertId();

            // Guardar contactos de emergencia (con transacción)
            $db->beginTransaction();
            try {
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
                        ':emp_id'      => $newId,
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

            // Registrar salario inicial en historial
            if ($data['salario_base'] !== null && $data['salario_base'] !== '') {
                $stmtSH = $db->prepare("
                    INSERT INTO salary_history (employee_id, salario_nuevo, tipo_cambio, motivo, modificado_por)
                    VALUES (:emp_id, :salario, 'alta', 'Salario inicial', :user_id)
                ");
                $stmtSH->execute([
                    ':emp_id'   => $newId,
                    ':salario'  => $data['salario_base'],
                    ':user_id'  => $_SESSION['user_id'],
                ]);
            }

            // Registrar contrato inicial en historial
            $stmtCH = $db->prepare("
                INSERT INTO contract_history (employee_id, tipo_contrato_nuevo, fecha_inicio, motivo, modificado_por)
                VALUES (:emp_id, :contrato, :fecha_ingreso, 'Contrato inicial', :user_id)
            ");
            $stmtCH->execute([
                ':emp_id'        => $newId,
                ':contrato'      => $data['tipo_contrato'],
                ':fecha_ingreso' => $data['fecha_ingreso'],
                ':user_id'       => $_SESSION['user_id'],
            ]);

            logAudit('create', 'employee', $newId, json_encode([
                'nombre' => $data['nombre'] . ' ' . $data['apellido_paterno'],
                'curp'   => $data['curp'],
            ]));

            setFlash('success', 'Empleado registrado exitosamente.');
            redirect(APP_URL . '/modules/employees/view.php?id=' . $newId);
        } catch (PDOException $e) {
            error_log('Error al crear empleado: ' . $e->getMessage());
            $errors[] = 'Error al guardar. Verifique que los datos no estén duplicados.';
        }
    }
}

function handlePhotoUpload(array $file, array &$errors): ?string
{
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 2 * 1024 * 1024;
    $mime = mime_content_type($file['tmp_name']);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($mime, $allowedMimes, true)) {
        $errors[] = 'La foto debe ser JPG, PNG o WebP.';
        return null;
    }
    if ($file['size'] > $maxSize) {
        $errors[] = 'La foto no debe exceder 2 MB.';
        return null;
    }
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $errors[] = 'Extensión de foto no válida.';
        return null;
    }

    $uniqueName = 'profile_' . uniqid() . '.' . $ext;
    $uploadDir = 'uploads' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR;
    $fullPath = __DIR__ . '/../../' . $uploadDir . $uniqueName;

    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        return $uploadDir . $uniqueName;
    }

    $errors[] = 'Error al subir la foto.';
    return null;
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <h2>Nuevo empleado</h2>
    <a href="<?= APP_URL ?>/modules/employees/index.php" class="btn btn-link">&larr; Volver al listado</a>
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
                        <div class="photo-preview" id="photoPreview" style="width:100px;height:100px;border-radius:50%;border:2px dashed var(--color-border);margin:0 auto 8px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:var(--color-surface-alt);">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        </div>
                        <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" style="font-size:0.8rem;width:100%;">
                    </div>
                </div>
                <div style="flex:1;display:flex;flex-direction:column;gap:12px;">
                    <div class="form-row">
                        <div class="form-group"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" value="<?= h($old['nombre'] ?? '') ?>" required></div>
                        <div class="form-group"><label for="apellido_paterno">Apellido paterno *</label><input type="text" id="apellido_paterno" name="apellido_paterno" value="<?= h($old['apellido_paterno'] ?? '') ?>" required></div>
                        <div class="form-group"><label for="apellido_materno">Apellido materno</label><input type="text" id="apellido_materno" name="apellido_materno" value="<?= h($old['apellido_materno'] ?? '') ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="curp">CURP *</label><input type="text" id="curp" name="curp" maxlength="18" value="<?= h($old['curp'] ?? '') ?>" required placeholder="18 caracteres"></div>
                        <div class="form-group"><label for="rfc">RFC *</label><input type="text" id="rfc" name="rfc" maxlength="13" value="<?= h($old['rfc'] ?? '') ?>" required placeholder="13 caracteres"></div>
                        <div class="form-group"><label for="nss">NSS *</label><input type="text" id="nss" name="nss" maxlength="11" value="<?= h($old['nss'] ?? '') ?>" required placeholder="11 dígitos"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="fecha_nacimiento">Fecha de nacimiento</label><input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= h($old['fecha_nacimiento'] ?? '') ?>"></div>
                        <div class="form-group"><label for="genero">Género</label>
                            <select id="genero" name="genero">
                                <option value="">Seleccionar</option>
                                <option value="M" <?= ($old['genero'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
                                <option value="F" <?= ($old['genero'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
                                <option value="Otro" <?= ($old['genero'] ?? '') === 'Otro' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Contacto y domicilio</legend>
            <div class="form-row">
                <div class="form-group"><label for="email">Correo electrónico</label><input type="email" id="email" name="email" value="<?= h($old['email'] ?? '') ?>"></div>
                <div class="form-group"><label for="telefono">Teléfono</label><input type="text" id="telefono" name="telefono" value="<?= h($old['telefono'] ?? '') ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group flex-2"><label for="calle">Calle</label><input type="text" id="calle" name="calle" value="<?= h($old['calle'] ?? '') ?>"></div>
                <div class="form-group"><label for="numero_exterior">No. ext</label><input type="text" id="numero_exterior" name="numero_exterior" value="<?= h($old['numero_exterior'] ?? '') ?>"></div>
                <div class="form-group"><label for="numero_interior">No. int</label><input type="text" id="numero_interior" name="numero_interior" value="<?= h($old['numero_interior'] ?? '') ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="colonia">Colonia</label><input type="text" id="colonia" name="colonia" value="<?= h($old['colonia'] ?? '') ?>"></div>
                <div class="form-group"><label for="codigo_postal">Código postal</label><input type="text" id="codigo_postal" name="codigo_postal" value="<?= h($old['codigo_postal'] ?? '') ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="ciudad">Ciudad</label><input type="text" id="ciudad" name="ciudad" value="<?= h($old['ciudad'] ?? '') ?>"></div>
                <div class="form-group"><label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="">Seleccionar</option>
                        <?php foreach ($estados as $e): ?>
                            <option value="<?= $e ?>" <?= ($old['estado'] ?? '') === $e ? 'selected' : '' ?>><?= $e ?></option>
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
                            <option value="<?= h($p['nombre']) ?>" <?= ($old['puesto'] ?? '') === $p['nombre'] ? 'selected' : '' ?>><?= h($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="departamento">Departamento</label>
                    <select id="departamento" name="departamento">
                        <option value="">Seleccionar</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= h($d['nombre']) ?>" <?= ($old['departamento'] ?? '') === $d['nombre'] ? 'selected' : '' ?>><?= h($d['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="fecha_ingreso">Fecha de ingreso</label><input type="date" id="fecha_ingreso" name="fecha_ingreso" value="<?= h($old['fecha_ingreso'] ?? '') ?>"></div>
                <div class="form-group"><label for="salario_base">Salario base ($)</label><input type="text" id="salario_base" name="salario_base" value="<?= h($old['salario_base'] ?? '') ?>" placeholder="0.00"></div>
                <div class="form-group"><label for="tipo_contrato">Tipo de contrato</label>
                    <select id="tipo_contrato" name="tipo_contrato">
                        <option value="Base" <?= ($old['tipo_contrato'] ?? 'Base') === 'Base' ? 'selected' : '' ?>>Base</option>
                        <option value="Confianza" <?= ($old['tipo_contrato'] ?? '') === 'Confianza' ? 'selected' : '' ?>>Confianza</option>
                        <option value="Temporal" <?= ($old['tipo_contrato'] ?? '') === 'Temporal' ? 'selected' : '' ?>>Temporal</option>
                        <option value="Honorarios" <?= ($old['tipo_contrato'] ?? '') === 'Honorarios' ? 'selected' : '' ?>>Honorarios</option>
                        <option value="Outsourcing" <?= ($old['tipo_contrato'] ?? '') === 'Outsourcing' ? 'selected' : '' ?>>Outsourcing</option>
                    </select>
                </div>
            </div>
            <div class="form-row" style="align-items:end;">
                <div class="form-group">
                    <label for="user_id">Vincular con usuario del sistema</label>
                    <select id="user_id" name="user_id">
                        <option value="">Sin vínculo</option>
                        <?php foreach ($availableUsers as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= ($old['user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= h($u['username']) ?></option>
                        <?php endforeach; ?>
                        <option value="new">+ Crear nuevo usuario</option>
                    </select>
                </div>
            </div>
            <div id="new-user-fields" style="display:none;border:1px solid var(--color-border);padding:12px;border-radius:var(--radius);margin-top:8px;">
                <div class="form-row">
                    <div class="form-group"><label for="new_username">Usuario</label><input type="text" id="new_username" name="new_username" placeholder="Nombre de usuario"></div>
                    <div class="form-group"><label for="new_password">Contraseña</label><input type="password" id="new_password" name="new_password" placeholder="Contraseña"></div>
                    <div class="form-group"><label for="new_password_confirm">Confirmar contraseña</label><input type="password" id="new_password_confirm" name="new_password_confirm" placeholder="Confirmar"></div>
                    <div class="form-group"><label for="new_role">Rol</label>
                        <select id="new_role" name="new_role">
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= h($r['name']) ?>"><?= h($r['display_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Contactos de emergencia</legend>
            <div id="emergency-contacts">
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
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="addContactRow()" style="margin-top:4px;">+ Agregar contacto</button>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar empleado</button>
            <a href="<?= APP_URL ?>/modules/employees/index.php" class="btn btn-link">Cancelar</a>
        </div>
    </form>
</div>

<script>
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

document.getElementById('user_id')?.addEventListener('change', function() {
    document.getElementById('new-user-fields').style.display = this.value === 'new' ? 'block' : 'none';
});
</script>

<?php
$extraJs = ['validations'];
require_once __DIR__ . '/../../includes/footer.php';
?>
