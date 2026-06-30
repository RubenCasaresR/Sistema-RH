<?php
/**
 * Subida de documentos para empleados.
 * Soporta carga masiva y versionado automático: si el empleado ya tiene
 * un documento del mismo tipo, el actual se archiva como versión anterior.
 */

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('documents.upload');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$errors = [];
$successes = [];

$allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
$maxSize = 5 * 1024 * 1024;

$selectedEmpId = (int)($_GET['employee_id'] ?? ($_POST['employee_id'] ?? 0));
$emps = $db->query("SELECT id, nombre, apellido_paterno, apellido_materno FROM employees WHERE activo = 1 ORDER BY apellido_paterno")->fetchAll();
$tiposDoc = ['Contrato', 'INE', 'Comprobante de domicilio', 'Acta de nacimiento', 'Constancia', 'Certificado', 'Otro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $errors[] = 'Token de seguridad inválido.';
    }

    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $tipoDocumento = trim($_POST['tipo_documento'] ?? '');
    $firmar = isset($_POST['firmar']);
    $notas = trim($_POST['notas'] ?? '');

    if ($employeeId <= 0) $errors[] = 'Seleccione un empleado.';
    if ($tipoDocumento === '') $errors[] = 'Seleccione el tipo de documento.';

    if (empty($_FILES['archivos']['name'][0])) {
        $errors[] = 'Seleccione al menos un archivo.';
    }

    if (count($errors) === 0) {
        $files = $_FILES['archivos'];
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $fileCount; $i++) {
            $originalName = basename($files['name'][$i]);
            $tmpName = $files['tmp_name'][$i];
            $fileError = $files['error'][$i];
            $fileSize = $files['size'][$i];

            if ($fileError !== UPLOAD_ERR_OK) {
                $errors[] = "Error al subir '$originalName'. Código: $fileError";
                continue;
            }

            $mimeType = mime_content_type($tmpName);
            if (!in_array($mimeType, $allowedMimes, true)) {
                $errors[] = "'$originalName': Tipo no permitido ($mimeType). Solo PDF, JPG y PNG.";
                continue;
            }

            if ($fileSize > $maxSize) {
                $errors[] = "'$originalName': Excede el límite de 5 MB.";
                continue;
            }

            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                $errors[] = "'$originalName': Extensión no válida.";
                continue;
            }

            $uniqueName = uniqid('doc_', true) . '.' . $ext;
            $uploadDir = 'uploads' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR;
            $fullPath = __DIR__ . '/../../' . $uploadDir . $uniqueName;
            $relativePath = $uploadDir . $uniqueName;

            if (!move_uploaded_file($tmpName, $fullPath)) {
                $errors[] = "'$originalName': Error al mover archivo.";
                continue;
            }

            $hashFirma = null;
            $fechaFirma = null;
            if ($firmar) {
                $hashFirma = hash_file('sha256', $fullPath);
                $fechaFirma = date('Y-m-d H:i:s');
            }

            try {
                $db->beginTransaction();

                // Auto-versioning: check if employee already has a doc of this type
                $stmt = $db->prepare("SELECT id, nombre_original, nombre_archivo, archivo_ruta, mime_type, peso_bytes, hash_firma, fecha_firma FROM employee_documents WHERE employee_id = :eid AND tipo_documento = :tipo LIMIT 1");
                $stmt->execute([':eid' => $employeeId, ':tipo' => $tipoDocumento]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Archive current version
                    $maxVer = $db->prepare("SELECT COALESCE(MAX(version_number), 0) + 1 as next_ver FROM document_versions WHERE document_id = :did");
                    $maxVer->execute([':did' => $existing['id']]);
                    $nextVer = (int)$maxVer->fetch()['next_ver'];

                    $stmtArch = $db->prepare("INSERT INTO document_versions (document_id, version_number, nombre_original, nombre_archivo, archivo_ruta, mime_type, peso_bytes, hash_firma, fecha_firma, subido_por) VALUES (:did, :ver, :nom, :arch, :ruta, :mime, :peso, :hash, :ff, :uid)");
                    $stmtArch->execute([
                        ':did' => $existing['id'],
                        ':ver' => $nextVer,
                        ':nom' => $existing['nombre_original'],
                        ':arch' => $existing['nombre_archivo'],
                        ':ruta' => $existing['archivo_ruta'],
                        ':mime' => $existing['mime_type'],
                        ':peso' => $existing['peso_bytes'],
                        ':hash' => $existing['hash_firma'],
                        ':ff' => $existing['fecha_firma'],
                        ':uid' => $_SESSION['user_id'] ?? null,
                    ]);

                    // Update existing document with new file
                    $stmtUpd = $db->prepare("UPDATE employee_documents SET nombre_original = :nom, nombre_archivo = :arch, archivo_ruta = :ruta, mime_type = :mime, peso_bytes = :peso, hash_firma = :hash, fecha_firma = :ff, notas = :notas WHERE id = :id");
                    $stmtUpd->execute([
                        ':nom' => $originalName,
                        ':arch' => $uniqueName,
                        ':ruta' => $relativePath,
                        ':mime' => $mimeType,
                        ':peso' => $fileSize,
                        ':hash' => $hashFirma,
                        ':ff' => $fechaFirma,
                        ':notas' => $notas,
                        ':id' => $existing['id'],
                    ]);

                    $docId = (int)$existing['id'];
                    $actionMsg = "actualizado (v{$nextVer})";
                } else {
                    // New document
                    $stmtIns = $db->prepare("
                        INSERT INTO employee_documents (employee_id, tipo_documento, nombre_original, nombre_archivo, archivo_ruta, mime_type, peso_bytes, hash_firma, fecha_firma, notas)
                        VALUES (:eid, :tipo, :nom, :arch, :ruta, :mime, :peso, :hash, :ff, :notas)
                    ");
                    $stmtIns->execute([
                        ':eid' => $employeeId,
                        ':tipo' => $tipoDocumento,
                        ':nom' => $originalName,
                        ':arch' => $uniqueName,
                        ':ruta' => $relativePath,
                        ':mime' => $mimeType,
                        ':peso' => $fileSize,
                        ':hash' => $hashFirma,
                        ':ff' => $fechaFirma,
                        ':notas' => $notas,
                    ]);

                    $docId = (int)$db->lastInsertId();
                    $actionMsg = "nuevo";
                }

                logAudit('create', 'document', $docId, json_encode([
                    'employee_id' => $employeeId,
                    'tipo' => $tipoDocumento,
                    'file' => $originalName,
                    'firmado' => $hashFirma ? true : false,
                    'action' => $actionMsg,
                ]));

                $db->commit();
                $successes[] = "'$originalName' subido $actionMsg." . ($hashFirma ? ' (Con firma digital)' : '');

            } catch (PDOException $e) {
                $db->rollBack();
                error_log("Error al guardar documento '$originalName': " . $e->getMessage());
                @unlink($fullPath);
                $errors[] = "'$originalName': Error al guardar el registro.";
            }
        }

        if (count($errors) === 0) {
            setFlash('success', implode('<br>', $successes));
            redirect(APP_URL . '/modules/documents/index.php');
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <h2>Subir documento(s)</h2>
    <a href="<?= APP_URL ?>/modules/documents/index.php" class="btn btn-link">&larr; Volver</a>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if (count($successes) > 0): ?>
    <div class="alert alert-success">
        <ul><?php foreach ($successes as $s): ?><li><?= h($s) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
    <form method="POST" action="" enctype="multipart/form-data" class="form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="form-group">
            <label for="employee_name">Empleado *</label>
            <input type="text" list="employeeList" id="employee_name" autocomplete="off" placeholder="Escribir para buscar" required value="<?php $selName2 = ''; foreach ($emps as $e) { if ($selectedEmpId === (int)$e['id']) { $selName2 = h($e['apellido_paterno'] . ' ' . ($e['apellido_materno'] ?? '') . ', ' . $e['nombre']); break; } } echo $selName2; ?>">
            <input type="hidden" name="employee_id" id="employee_id_hidden" value="<?= $selectedEmpId > 0 ? $selectedEmpId : '' ?>">
            <datalist id="employeeList">
                <?php foreach ($emps as $e): ?>
                    <option value="<?= h($e['apellido_paterno'] . ' ' . ($e['apellido_materno'] ?? '') . ', ' . $e['nombre']) ?>" data-id="<?= (int)$e['id'] ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="form-group">
            <label for="tipo_documento">Tipo de documento *</label>
            <select id="tipo_documento" name="tipo_documento" required>
                <option value="">Seleccionar</option>
                <?php foreach ($tiposDoc as $t): ?>
                    <option value="<?= $t ?>" <?= ($_POST['tipo_documento'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <small style="color:var(--color-text-secondary);">Si el empleado ya tiene un documento de este tipo, el anterior se archivará como versión previa.</small>
        </div>

        <div class="form-group">
            <label for="notas">Notas (opcional)</label>
            <textarea id="notas" name="notas" rows="2" maxlength="500" placeholder="Notas adicionales sobre el documento..."><?= h($_POST['notas'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label>Archivos (PDF, JPG, PNG — máx. 5 MB c/u) *</label>
            <div class="drop-zone" id="dropZone">
                <div class="drop-zone-content" id="dropZoneContent">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <p class="drop-zone-text">Arrastra archivos aquí</p>
                    <p class="drop-zone-subtext">o haz clic para seleccionar</p>
                    <p class="drop-zone-hint">PDF, JPG, PNG — hasta 5 MB c/u</p>
                </div>
                <div class="drop-zone-preview" id="filePreview" style="display:none;"></div>
                <input type="file" id="archivos" name="archivos[]" accept=".pdf,.jpg,.jpeg,.png" multiple hidden>
            </div>
        </div>

<script>
(function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('archivos');
    const preview = document.getElementById('filePreview');
    const content = document.getElementById('dropZoneContent');

    function updatePreview(files) {
        preview.innerHTML = '';
        if (files.length === 0) {
            preview.style.display = 'none';
            content.style.display = '';
            return;
        }
        content.style.display = 'none';
        preview.style.display = 'block';
        const list = document.createElement('div');
        list.className = 'file-list';
        for (const f of files) {
            const size = f.size > 1048576
                ? (f.size / 1048576).toFixed(2) + ' MB'
                : (f.size / 1024).toFixed(1) + ' KB';
            const ext = f.name.split('.').pop().toLowerCase();
            const icon = ext === 'pdf' ? '📄' : '🖼️';
            const item = document.createElement('div');
            item.className = 'file-item';
            item.innerHTML = `<span class="file-icon">${icon}</span>
                <span class="file-name">${f.name}</span>
                <span class="file-size">${size}</span>`;
            list.appendChild(item);
        }
        preview.appendChild(list);
        const total = files.length;
        const label = document.createElement('p');
        label.style.cssText = 'margin:8px 0 0;font-size:0.85rem;color:var(--color-text-secondary);';
        label.textContent = total + ' archivo' + (total !== 1 ? 's' : '') + ' seleccionado' + (total !== 1 ? 's' : '');
        preview.appendChild(label);
    }

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    dropZone.addEventListener('dragenter', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        if (!dropZone.contains(e.relatedTarget)) {
            dropZone.classList.remove('drag-over');
        }
    });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            updatePreview(fileInput.files);
        }
    });

    fileInput.addEventListener('change', () => {
        updatePreview(fileInput.files);
    });

    document.querySelector('form').addEventListener('submit', (e) => {
        if (fileInput.files.length === 0) {
            e.preventDefault();
            dropZone.style.borderColor = '#e74c3c';
            dropZone.style.background = 'rgba(231,76,60,0.05)';
            setTimeout(() => {
                dropZone.style.borderColor = '';
                dropZone.style.background = '';
            }, 2000);
            return;
        }
        const btn = document.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Subiendo...';
    });

    const empInput = document.getElementById('employee_name');
    const empHidden = document.getElementById('employee_id_hidden');
    if (empInput && empHidden) {
        empInput.addEventListener('input', function() {
            const opts = document.querySelectorAll('#employeeList option');
            let found = false;
            for (const opt of opts) {
                if (opt.value === this.value) {
                    empHidden.value = opt.dataset.id;
                    found = true;
                    break;
                }
            }
            if (!found) empHidden.value = '';
        });
    }
})();
</script>

<style>
.drop-zone {
    border: 2px dashed var(--color-border, #ccc);
    border-radius: 12px;
    padding: 32px 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: var(--color-bg-secondary, #fafafa);
    min-height: 140px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.drop-zone:hover {
    border-color: var(--color-primary, #4361ee);
    background: rgba(67, 97, 238, 0.04);
}
.drop-zone.drag-over {
    border-color: var(--color-primary, #4361ee);
    background: rgba(67, 97, 238, 0.1);
    border-style: solid;
    transform: scale(1.01);
}
.drop-zone-content {
    pointer-events: none;
}
.drop-zone-content svg {
    color: var(--color-text-secondary, #888);
    margin-bottom: 8px;
}
.drop-zone-text {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--color-text, #333);
    margin: 0 0 4px;
}
.drop-zone-subtext {
    font-size: 0.85rem;
    color: var(--color-text-secondary, #888);
    margin: 0 0 4px;
}
.drop-zone-hint {
    font-size: 0.75rem;
    color: var(--color-text-secondary, #aaa);
    margin: 0;
}
.drop-zone-preview {
    width: 100%;
    text-align: left;
}
.file-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.file-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--color-bg, #fff);
    border-radius: 8px;
    border: 1px solid var(--color-border, #eee);
}
.file-icon {
    font-size: 1.2rem;
    flex-shrink: 0;
}
.file-name {
    flex: 1;
    font-size: 0.9rem;
    word-break: break-all;
    color: var(--color-text, #333);
}
.file-size {
    font-size: 0.8rem;
    color: var(--color-text-secondary, #888);
    white-space: nowrap;
}
</style>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="firmar" value="1" <?= isset($_POST['firmar']) ? 'checked' : '' ?>>
                Firmar digitalmente (generar hash SHA-256)
            </label>
            <small style="color:var(--color-text-secondary);">Al marcar esta opción, se generará un hash de integridad para cada documento.</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Subir documento(s)</button>
            <a href="<?= APP_URL ?>/modules/documents/index.php" class="btn btn-link">Cancelar</a>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
