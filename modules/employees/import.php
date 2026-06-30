<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('employees.create');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$errors = [];
$results = [];

// Descarga de plantilla CSV
if (isset($_GET['action']) && $_GET['action'] === 'template') {
    $cols = ['nombre','apellido_paterno','apellido_materno','curp','rfc','nss','fecha_nacimiento','genero','email','telefono','calle','numero_exterior','numero_interior','colonia','codigo_postal','ciudad','estado','puesto','departamento','fecha_ingreso','salario_base','tipo_contrato','user_id'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="plantilla_empleados.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, $cols);
    fputcsv($out, ['Juan','Pérez','López','JUAP800101HDFRRN01','PELJ800101XXX','12345678901','1980-01-01','M','juan@ejemplo.com','5512345678','Av. Reforma','123','A','Centro','06600','Ciudad de México','CDMX','Desarrollador','TI','2024-01-01','25000.00','Base','']);
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm'])) {
        $csvHeaders = json_decode(base64_decode($_POST['csv_headers']), true) ?? [];
        $csvRows = json_decode(base64_decode($_POST['csv_data']), true) ?? [];
        $headers = $csvHeaders;
        $rows = $csvRows;

        if (empty($headers) || empty($rows)) {
            $errors[] = 'Los datos del CSV no están disponibles. Vuelve a subir el archivo.';
        } else {
            $imported = 0;
            $failed   = 0;

            $insertEmp = $db->prepare("
                INSERT INTO employees (
                    user_id, nombre, apellido_paterno, apellido_materno, curp, rfc, nss,
                    fecha_nacimiento, genero, email, telefono,
                    calle, numero_exterior, numero_interior, colonia, codigo_postal, ciudad, estado,
                    puesto, departamento, fecha_ingreso, salario_base, tipo_contrato
                ) VALUES (
                    :user_id, :nombre, :apellido_paterno, :apellido_materno, :curp, :rfc, :nss,
                    :fecha_nacimiento, :genero, :email, :telefono,
                    :calle, :numero_exterior, :numero_interior, :colonia, :codigo_postal, :ciudad, :estado,
                    :puesto, :departamento, :fecha_ingreso, :salario_base, :tipo_contrato
                )
            ");

            $insertSal = $db->prepare("
                INSERT INTO salary_history (employee_id, salario_nuevo, tipo_cambio, motivo, modificado_por)
                VALUES (:emp_id, :salario, 'alta', 'Salario inicial (importación)', :user_id)
            ");

            $insertCont = $db->prepare("
                INSERT INTO contract_history (employee_id, tipo_contrato_nuevo, fecha_inicio, motivo, modificado_por)
                VALUES (:emp_id, :contrato, :fecha_inicio, 'Contrato inicial (importación)', :user_id)
            ");

            foreach ($rows as $row) {
                $row = array_pad($row, count($headers), '');
                $record = array_combine($headers, array_map('trim', $row));

                $curp = $record['curp'] ?? '';
                if ($curp === '') { $failed++; continue; }

                $check = $db->prepare("SELECT id FROM employees WHERE curp = :curp");
                $check->execute([':curp' => $curp]);
                if ($check->fetch()) { $failed++; continue; }

                $csvUserId = $record['user_id'] ?? '';
                $importUserId = null;
                if ($csvUserId !== '') {
                    $uidCheck = $db->prepare("SELECT id FROM users WHERE id = :id AND activo = 1");
                    $uidCheck->execute([':id' => (int)$csvUserId]);
                    if ($uidCheck->fetch()) {
                        $importUserId = (int)$csvUserId;
                    }
                }

                $empData = [
                    ':user_id'            => $importUserId,
                    ':nombre'             => $record['nombre'] ?? '',
                    ':apellido_paterno'   => $record['apellido_paterno'] ?? '',
                    ':apellido_materno'   => $record['apellido_materno'] ?? '',
                    ':curp'               => $curp,
                    ':rfc'                => $record['rfc'] ?? '',
                    ':nss'                => $record['nss'] ?? '',
                    ':fecha_nacimiento'   => $record['fecha_nacimiento'] ?? null,
                    ':genero'             => $record['genero'] ?? '',
                    ':email'              => $record['email'] ?? '',
                    ':telefono'           => $record['telefono'] ?? '',
                    ':calle'              => $record['calle'] ?? '',
                    ':numero_exterior'    => $record['numero_exterior'] ?? '',
                    ':numero_interior'    => $record['numero_interior'] ?? '',
                    ':colonia'            => $record['colonia'] ?? '',
                    ':codigo_postal'      => $record['codigo_postal'] ?? '',
                    ':ciudad'             => $record['ciudad'] ?? '',
                    ':estado'             => $record['estado'] ?? '',
                    ':puesto'             => $record['puesto'] ?? '',
                    ':departamento'       => $record['departamento'] ?? '',
                    ':fecha_ingreso'      => $record['fecha_ingreso'] ?? null,
                    ':salario_base'       => $record['salario_base'] !== '' ? $record['salario_base'] : null,
                    ':tipo_contrato'      => $record['tipo_contrato'] ?? 'Base',
                ];

                try {
                    $insertEmp->execute($empData);
                    $newId = (int)$db->lastInsertId();

                    if ($empData[':salario_base'] !== null) {
                        $insertSal->execute([
                            ':emp_id'   => $newId,
                            ':salario'  => $empData[':salario_base'],
                            ':user_id'  => $_SESSION['user_id'],
                        ]);
                    }

                    $insertCont->execute([
                        ':emp_id'       => $newId,
                        ':contrato'     => $empData[':tipo_contrato'],
                        ':fecha_inicio' => $empData[':fecha_ingreso'],
                        ':user_id'      => $_SESSION['user_id'],
                    ]);

                    logAudit('create', 'employee', $newId, json_encode([
                        'nombre' => $empData[':nombre'] . ' ' . $empData[':apellido_paterno'],
                        'curp'   => $curp,
                    ]));
                    $imported++;
                } catch (Exception $e) {
                    $failed++;
                }
            }

            $results = [
                'total'    => count($rows),
                'imported' => $imported,
                'failed'   => $failed,
            ];
        }
    } else {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Selecciona un archivo CSV válido.';
        } else {
            $tmpPath = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($tmpPath, 'r');
            if (!$handle) {
                $errors[] = 'No se pudo leer el archivo.';
            } else {
                $headers = fgetcsv($handle);
                if (!$headers || count($headers) < 5) {
                    $errors[] = 'El CSV debe tener al menos las columnas: nombre, apellido_paterno, curp.';
                    fclose($handle);
                } else {
                    $headers = array_map('trim', $headers);
                    $rows = [];
                    while (($data = fgetcsv($handle)) !== false) {
                        $rows[] = $data;
                    }
                    fclose($handle);

                    if (empty($rows)) {
                        $errors[] = 'El archivo CSV no contiene datos.';
                    } else {
                        $preview = [];
                        foreach ($rows as $row) {
                            $row = array_pad($row, count($headers), '');
                            $preview[] = array_combine($headers, array_map('trim', $row));
                        }
                        $csvHeadersEncoded = base64_encode(json_encode($headers));
                        $csvDataEncoded = base64_encode(json_encode($rows));
                    }
                }
            }
        }
    }
}

$departments = $db->query("SELECT DISTINCT departamento FROM employees WHERE departamento IS NOT NULL AND departamento != '' ORDER BY departamento")->fetchAll(PDO::FETCH_COLUMN);
$positions = $db->query("SELECT id, nombre FROM positions WHERE activo = 1 ORDER BY nombre")->fetchAll();
?>

<div class="page-header">
    <h1>Importar empleados desde CSV</h1>
    <div class="page-actions">
        <a href="<?= APP_URL ?>/modules/employees/index.php" class="btn btn-link">&larr; Volver al listado</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><p><?= h($e) ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($results)): ?>
    <div class="card">
        <div class="card-body">
            <h3>Resultados de la importación</h3>
            <ul>
                <li>Total de registros: <strong><?= $results['total'] ?></strong></li>
                <li>Importados: <strong style="color:var(--color-success)"><?= $results['imported'] ?></strong></li>
                <li>Fallidos (duplicados o inválidos): <strong style="color:var(--color-danger)"><?= $results['failed'] ?></strong></li>
            </ul>
            <a href="<?= APP_URL ?>/modules/employees/index.php" class="btn btn-primary">Ir al listado</a>
        </div>
    </div>
<?php elseif (isset($preview)): ?>
    <div class="card">
        <div class="card-header">
            <h3>Vista previa — <?= count($preview) ?> registro(s) encontrados</h3>
        </div>
        <div class="card-body">
            <div style="overflow-x:auto;max-height:400px;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Apellido Paterno</th>
                            <th>CURP</th>
                            <th>Puesto</th>
                            <th>Departamento</th>
                            <th>Salario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview as $i => $row): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= h($row['nombre'] ?? '') ?></td>
                            <td><?= h($row['apellido_paterno'] ?? '') ?></td>
                            <td><?= h($row['curp'] ?? '') ?></td>
                            <td><?= h($row['puesto'] ?? '') ?></td>
                            <td><?= h($row['departamento'] ?? '') ?></td>
                            <td><?= h($row['salario_base'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" style="margin-top:16px;">
                <input type="hidden" name="confirm" value="1">
                <input type="hidden" name="csv_headers" value="<?= h($csvHeadersEncoded) ?>">
                <input type="hidden" name="csv_data" value="<?= h($csvDataEncoded) ?>">
                <button type="submit" class="btn btn-primary">Confirmar importación</button>
                <a href="<?= APP_URL ?>/modules/employees/import.php" class="btn btn-link">Cancelar</a>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($results) && !isset($preview)): ?>
<div class="card">
    <div class="card-body">
        <p>Sube un archivo CSV con las siguientes columnas (el orden puede variar):</p>
        <div style="overflow-x:auto;font-size:0.85rem;">
            <table class="table">
                <thead>
                    <tr><th>Columna</th><th>Requerido</th><th>Descripción</th></tr>
                </thead>
                <tbody>
                    <tr><td>nombre</td><td>Sí</td><td>Nombre(s) del empleado</td></tr>
                    <tr><td>apellido_paterno</td><td>Sí</td><td>Primer apellido</td></tr>
                    <tr><td>apellido_materno</td><td>No</td><td>Segundo apellido</td></tr>
                    <tr><td>curp</td><td>Sí (único)</td><td>CURP del empleado</td></tr>
                    <tr><td>rfc</td><td>No</td><td>RFC</td></tr>
                    <tr><td>nss</td><td>No</td><td>Número de Seguro Social</td></tr>
                    <tr><td>fecha_nacimiento</td><td>No</td><td>YYYY-MM-DD</td></tr>
                    <tr><td>genero</td><td>No</td><td>M, F u Otro</td></tr>
                    <tr><td>email</td><td>No</td><td>Correo electrónico</td></tr>
                    <tr><td>telefono</td><td>No</td><td>Teléfono</td></tr>
                    <tr><td>calle</td><td>No</td><td>Calle del domicilio</td></tr>
                    <tr><td>numero_exterior</td><td>No</td><td>Número exterior</td></tr>
                    <tr><td>numero_interior</td><td>No</td><td>Número interior</td></tr>
                    <tr><td>colonia</td><td>No</td><td>Colonia</td></tr>
                    <tr><td>codigo_postal</td><td>No</td><td>Código Postal</td></tr>
                    <tr><td>ciudad</td><td>No</td><td>Ciudad / Municipio</td></tr>
                    <tr><td>estado</td><td>No</td><td>Estado</td></tr>
                    <tr><td>puesto</td><td>No</td><td>Puesto del empleado</td></tr>
                    <tr><td>departamento</td><td>No</td><td>Departamento</td></tr>
                    <tr><td>fecha_ingreso</td><td>No</td><td>Fecha de ingreso YYYY-MM-DD</td></tr>
                    <tr><td>salario_base</td><td>No</td><td>Salario base (número)</td></tr>
                    <tr><td>tipo_contrato</td><td>No</td><td>Base, Confianza, Temporal, Honorarios, Outsourcing</td></tr>
                    <tr><td>user_id</td><td>No</td><td>ID del usuario del sistema a vincular</td></tr>
                </tbody>
            </table>
        </div>
        <div style="display:flex;gap:16px;align-items:center;margin-top:16px;">
            <a href="?action=template" class="btn btn-link">Descargar plantilla CSV</a>
        </div>
        <form method="post" enctype="multipart/form-data" style="margin-top:16px;">
            <div class="form-group">
                <label for="csv_file">Archivo CSV</label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
            </div>
            <div class="form-actions" style="border:none;padding:0;margin-top:8px;">
                <button type="submit" class="btn btn-primary">Previsualizar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
