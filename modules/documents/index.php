<?php
/**
 * Listado de documentos por empleado.
 * Incluye: búsqueda avanzada, vista previa modal, versiones, exportar CSV.
 */

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('documents.read');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$employeeId = (int)($_GET['employee_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$tipo = trim($_GET['tipo'] ?? '');
$fechaFrom = trim($_GET['fecha_from'] ?? '');
$fechaTo = trim($_GET['fecha_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$params = [];
$where = 'WHERE 1=1';

if ($employeeId > 0) {
    $where .= ' AND d.employee_id = :emp_id';
    $params[':emp_id'] = $employeeId;
}

if ($search !== '') {
    $where .= ' AND (d.nombre_original LIKE :search OR d.tipo_documento LIKE :search2)';
    $params[':search'] = "%$search%";
    $params[':search2'] = "%$search%";
}

if ($tipo !== '') {
    $where .= ' AND d.tipo_documento = :tipo';
    $params[':tipo'] = $tipo;
}

if ($fechaFrom !== '') {
    $where .= ' AND d.created_at >= :fecha_from';
    $params[':fecha_from'] = $fechaFrom . ' 00:00:00';
}

if ($fechaTo !== '') {
    $where .= ' AND d.created_at <= :fecha_to';
    $params[':fecha_to'] = $fechaTo . ' 23:59:59';
}

$stmtCount = $db->prepare("SELECT COUNT(*) FROM employee_documents d INNER JOIN employees e ON e.id = d.employee_id $where");
$stmtCount->execute($params);
$totalDocs = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalDocs / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT d.*, e.nombre, e.apellido_paterno, e.apellido_materno,
           (SELECT COUNT(*) FROM document_versions WHERE document_id = d.id) as version_count
    FROM employee_documents d
    INNER JOIN employees e ON e.id = d.employee_id
    $where
    ORDER BY d.created_at DESC
    LIMIT :lim OFFSET :off
");
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$docs = $stmt->fetchAll();

$emps = $db->query("SELECT id, nombre, apellido_paterno, apellido_materno FROM employees WHERE activo = 1 ORDER BY apellido_paterno")->fetchAll();
$tiposDoc = $db->query("SELECT DISTINCT tipo_documento FROM employee_documents ORDER BY tipo_documento")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-header">
    <h2>Documentos</h2>
    <div class="page-header-actions">
        <button type="button" class="btn btn-link" onclick="exportCSV()">Exportar CSV</button>
        <?php if (can('documents.upload')): ?>
            <a href="<?= APP_URL ?>/modules/documents/upload.php" class="btn btn-primary">+ Subir documento(s)</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <form method="GET" action="" class="search-form" id="filterForm">
        <div class="search-form-row">
            <div class="form-group">
                <label for="employee_name">Empleado</label>
                <input type="text" list="employeeList" id="employee_name" autocomplete="off" placeholder="Todos (escribir para buscar)" value="<?php $selName = ''; foreach ($emps as $e) { if ($employeeId === (int)$e['id']) { $selName = h($e['apellido_paterno'] . ' ' . ($e['apellido_materno'] ?? '') . ', ' . $e['nombre']); break; } } echo $selName; ?>">
                <input type="hidden" name="employee_id" id="employee_id_hidden" value="<?= $employeeId > 0 ? $employeeId : '' ?>">
                <datalist id="employeeList">
                    <?php foreach ($emps as $e): ?>
                        <option value="<?= h($e['apellido_paterno'] . ' ' . ($e['apellido_materno'] ?? '') . ', ' . $e['nombre']) ?>" data-id="<?= (int)$e['id'] ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label for="search">Buscar</label>
                <input type="search" id="search" name="search" placeholder="Nombre de archivo..." value="<?= h($search) ?>">
            </div>
            <div class="form-group">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tiposDoc as $t): ?>
                        <option value="<?= h($t) ?>" <?= $tipo === $t ? 'selected' : '' ?>><?= h($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="fecha_from">Desde</label>
                <input type="date" id="fecha_from" name="fecha_from" value="<?= h($fechaFrom) ?>">
            </div>
            <div class="form-group">
                <label for="fecha_to">Hasta</label>
                <input type="date" id="fecha_to" name="fecha_to" value="<?= h($fechaTo) ?>">
            </div>
            <div class="form-group" style="align-self:flex-end;">
                <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                <a href="<?= APP_URL ?>/modules/documents/index.php" class="btn btn-sm btn-link">Limpiar</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table" id="docsTable">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Tipo</th>
                    <th>Archivo</th>
                    <th>Peso</th>
                    <th>Firma digital</th>
                    <th>Versiones</th>
                    <th>Subido</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($docs) === 0): ?>
                    <tr><td colspan="8" class="empty-state">No hay documentos registrados.</td></tr>
                <?php else: ?>
                    <?php $deleteCsrf = $_SESSION['csrf_token'] ?? generateCSRFToken(); ?>
                    <?php foreach ($docs as $d): ?>
                        <?php
                        $peso = $d['peso_bytes'] > 1048576
                            ? round($d['peso_bytes'] / 1048576, 2) . ' MB'
                            : round($d['peso_bytes'] / 1024, 1) . ' KB';
                        $ext = strtolower(pathinfo($d['nombre_original'], PATHINFO_EXTENSION));
                        $canPreview = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png']);
                        ?>
                        <tr>
                            <td><?= h($d['nombre'] . ' ' . $d['apellido_paterno']) ?></td>
                            <td><span class="badge badge-info"><?= h($d['tipo_documento']) ?></span></td>
                            <td><a href="#" data-id="<?= (int)$d['id'] ?>" onclick="return downloadDoc(this)"><?= h($d['nombre_original']) ?></a></td>
                            <td><?= $peso ?></td>
                            <td>
                                <?php if ($d['hash_firma']): ?>
                                    <span class="badge badge-success" title="SHA-256: <?= h($d['hash_firma']) ?>">Firmado</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Sin firma</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$d['version_count'] > 0): ?>
                                    <a href="#" data-id="<?= (int)$d['id'] ?>" onclick="return showVersions(this)" class="badge badge-warning" style="cursor:pointer;">
                                        <?= (int)$d['version_count'] ?> versión(es)
                                    </a>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Actual</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($d['created_at']) ?></td>
                            <td class="actions-cell">
                                <?php if ($canPreview): ?>
                                    <a href="#" data-id="<?= (int)$d['id'] ?>" onclick="return previewDoc(this)" class="btn btn-sm btn-ghost">Vista previa</a>
                                <?php endif; ?>
                                <a href="#" data-id="<?= (int)$d['id'] ?>" onclick="return downloadDoc(this)" class="btn btn-sm btn-ghost">Descargar</a>
                                <?php if (can('documents.delete')): ?>
                                    <form method="POST" action="<?= APP_URL ?>/modules/documents/delete.php" style="display:inline">
                                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $deleteCsrf ?>">
                                        <button type="submit" class="btn btn-sm btn-ghost"
                                                onclick="return confirm('¿Eliminar el documento &quot;<?= h($d['nombre_original']) ?>&quot; (<?= h($d['tipo_documento']) ?>)?')">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:1px solid var(--color-border);">
        <span style="font-size:0.85rem;color:var(--color-text-secondary);">
            Mostrando <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalDocs) ?> de <?= $totalDocs ?> documentos
        </span>
        <div style="display:flex;gap:4px;">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-sm btn-link">&laquo; Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-link' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-sm btn-link">Siguiente &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Preview Modal -->
<div class="modal" id="previewModal">
    <div class="modal-content" style="max-width:90vw;width:900px;">
        <div class="modal-header">
            <h3 id="previewTitle">Vista previa</h3>
            <button class="modal-close" onclick="closeModal('previewModal')">&times;</button>
        </div>
        <div class="modal-body" style="min-height:400px;display:flex;align-items:center;justify-content:center;">
            <div id="previewContainer" style="width:100%;height:70vh;"></div>
        </div>
        <div class="modal-footer">
            <a href="#" id="previewDownloadBtn" class="btn btn-sm btn-primary">Descargar</a>
            <button class="btn btn-sm btn-link" onclick="closeModal('previewModal')">Cerrar</button>
        </div>
    </div>
</div>

<!-- Versions Modal -->
<div class="modal" id="versionsModal">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h3>Historial de versiones</h3>
            <button class="modal-close" onclick="closeModal('versionsModal')">&times;</button>
        </div>
        <div class="modal-body" id="versionsContainer">
            <p class="empty-state">Cargando...</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-sm btn-link" onclick="closeModal('versionsModal')">Cerrar</button>
        </div>
    </div>
</div>

<script>
function getToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function previewDoc(el) {
    const id = el.dataset.id;
    const csrf = getToken();
    const title = document.getElementById('previewTitle');
    const container = document.getElementById('previewContainer');
    const downloadBtn = document.getElementById('previewDownloadBtn');

    title.textContent = 'Vista previa';
    container.innerHTML = '<p class="empty-state">Cargando...</p>';
    downloadBtn.href = '#';

    fetch(`${APP_URL}/api/files.php?id=${id}&token=${csrf}&preview=1`)
        .then(res => {
            const contentType = res.headers.get('Content-Type') || '';
            const disposition = res.headers.get('Content-Disposition') || '';
            const filename = disposition.match(/filename="?(.+?)"?$/)?.[1] || 'documento';

            if (!res.ok) {
                container.innerHTML = '<p class="empty-state">Error al cargar el documento.</p>';
                return;
            }

            title.textContent = filename;
            downloadBtn.href = `${APP_URL}/api/files.php?id=${id}&token=${csrf}`;

            if (contentType.startsWith('image/')) {
                res.blob().then(blob => {
                    const url = URL.createObjectURL(blob);
                    container.innerHTML = `<img src="${url}" style="max-width:100%;max-height:70vh;object-fit:contain;" alt="${filename}">`;
                });
            } else if (contentType === 'application/pdf') {
                res.blob().then(blob => {
                    const url = URL.createObjectURL(blob);
                    container.innerHTML = `<iframe src="${url}" style="width:100%;height:70vh;border:none;"></iframe>`;
                });
            } else {
                container.innerHTML = '<p class="empty-state">Vista previa no disponible para este tipo de archivo.</p>';
            }
        })
        .catch(() => {
            container.innerHTML = '<p class="empty-state">Error de conexión.</p>';
        });

    openModal('previewModal');
    return false;
}

function showVersions(el) {
    const id = el.dataset.id;
    const container = document.getElementById('versionsContainer');
    container.innerHTML = '<p class="empty-state">Cargando...</p>';
    openModal('versionsModal');

    fetch(`${APP_URL}/api/documents.php?action=versions&id=${id}`)
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) {
                container.innerHTML = '<p class="empty-state">Error al cargar versiones.</p>';
                return;
            }

            const current = resp.data.current;
            const versions = resp.data.versions;

            let html = '<table class="table"><thead><tr><th>Versión</th><th>Archivo</th><th>Peso</th><th>Firma</th><th>Subido por</th><th>Fecha</th><th>Acción</th></tr></thead><tbody>';

            const pesoCurrent = current.peso_bytes > 1048576
                ? (current.peso_bytes / 1048576).toFixed(2) + ' MB'
                : (current.peso_bytes / 1024).toFixed(1) + ' KB';
            const token = getToken();
            html += `<tr style="font-weight:bold;">
                <td><span class="badge badge-success">Actual</span></td>
                <td>${current.nombre_original}</td>
                <td>${pesoCurrent}</td>
                <td>${current.hash_firma ? '<span class="badge badge-success">Firmado</span>' : '<span class="badge badge-secondary">Sin firma</span>'}</td>
                <td>—</td>
                <td>${current.created_at}</td>
                <td><a href="#" onclick="window.open('${APP_URL}/api/files.php?id=${id}&token=${token}', '_blank');return false;">Descargar</a></td>
            </tr>`;

            versions.forEach(v => {
                const peso = v.peso_bytes > 1048576
                    ? (v.peso_bytes / 1048576).toFixed(2) + ' MB'
                    : (v.peso_bytes / 1024).toFixed(1) + ' KB';
                const subido = v.username || v.subido_por || '—';
                html += `<tr>
                    <td>v${v.version_number}</td>
                    <td>${v.nombre_original}</td>
                    <td>${peso}</td>
                    <td>${v.hash_firma ? '<span class="badge badge-success">Firmado</span>' : '<span class="badge badge-secondary">Sin firma</span>'}</td>
                    <td>${subido}</td>
                    <td>${v.created_at}</td>
                    <td><a href="#" onclick="window.open('${APP_URL}/api/files.php?id=${id}&token=${token}&version=${v.id}', '_blank');return false;">Descargar</a></td>
                </tr>`;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        })
        .catch(() => {
            container.innerHTML = '<p class="empty-state">Error de conexión.</p>';
        });

    return false;
}

// ── Employee datalist sync ───────────────────────────────
(function() {
    const input = document.getElementById('employee_name');
    const hidden = document.getElementById('employee_id_hidden');
    if (input && hidden) {
        input.addEventListener('input', function() {
            const opts = document.querySelectorAll('#employeeList option');
            let found = false;
            for (const opt of opts) {
                if (opt.value === this.value) {
                    hidden.value = opt.dataset.id;
                    found = true;
                    break;
                }
            }
            if (!found) hidden.value = '';
        });
    }
})();

function exportCSV() {
    const params = new URLSearchParams(window.location.search);
    params.set('action', 'export');
    window.open(`${APP_URL}/api/documents.php?${params.toString()}`, '_blank');
}

// ── Close modals with Escape ───────────────────────────────
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-open').forEach(m => m.classList.remove('modal-open'));
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
