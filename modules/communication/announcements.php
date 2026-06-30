<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('announcements.read');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$tipoActual = $_GET['tipo'] ?? '';
$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 20;
$offset = ($pagina - 1) * $porPagina;

$params = [];
$where = 'WHERE a.activo = 1';

if (in_array($tipoActual, ['aviso', 'circular', 'politica', 'evento'])) {
    $where .= ' AND a.tipo = :tipo';
    $params[':tipo'] = $tipoActual;
}

// Total para paginación
$stmtCount = $db->prepare("SELECT COUNT(*) FROM announcements a $where");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$totalPaginas = max(1, (int)ceil($total / $porPagina));

if ($pagina > $totalPaginas) $pagina = $totalPaginas;

// Listado paginado
$stmtList = $db->prepare("
    SELECT a.*, u.username AS autor
    FROM announcements a
    INNER JOIN users u ON u.id = a.publicado_por
    $where
    ORDER BY a.created_at DESC
    LIMIT $porPagina OFFSET $offset
");
$stmtList->execute($params);
$announcements = $stmtList->fetchAll();

$puedePublicar = can('announcements.create');
$puedeEliminar = can('announcements.delete');

$badgeMap = [
    'aviso'    => 'info',
    'circular' => 'warning',
    'politica' => 'secondary',
    'evento'   => 'success',
];

$tipoLabels = [
    'aviso'    => 'Aviso',
    'circular' => 'Circular',
    'politica' => 'Política',
    'evento'   => 'Evento',
];

$csrfToken = $puedeEliminar ? generateCSRFToken() : '';
?>

<div class="page-header">
    <h2>Comunicados</h2>
    <?php if ($puedePublicar): ?>
        <a href="<?= APP_URL ?>/modules/communication/create.php" class="btn btn-primary">+ Nuevo comunicado</a>
    <?php endif; ?>
</div>

<!-- Filtros -->
<div class="card">
    <form method="GET" action="" class="search-form" id="filterForm">
        <div class="form-group">
            <label for="tipo">Filtrar por tipo</label>
            <select id="tipo" name="tipo" onchange="document.getElementById('filterForm').submit(); this.disabled=true;">
                <option value="">Todos</option>
                <?php foreach ($tipoLabels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $tipoActual === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="announcements-list">
    <?php if (count($announcements) === 0): ?>
        <div class="card">
            <p class="empty-state">No hay comunicados publicados.</p>
            <?php if ($puedePublicar): ?>
                <p style="text-align:center;margin-top:12px;">
                    <a href="<?= APP_URL ?>/modules/communication/create.php" class="btn btn-primary">Publicar el primero</a>
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($announcements as $a): ?>
            <div class="card announcement-card">
                <div class="announcement-header">
                    <span class="badge badge-<?= $badgeMap[$a['tipo']] ?? 'info' ?>">
                        <?= $tipoLabels[$a['tipo']] ?? $a['tipo'] ?>
                    </span>
                    <span class="announcement-date"><?= formatDate($a['created_at']) ?></span>
                    <?php if ($puedeEliminar): ?>
                        <button type="button" class="btn btn-sm btn-ghost"
                                onclick="confirmarEliminar(<?= (int)$a['id'] ?>, '<?= h(addslashes($a['titulo'])) ?>')">Eliminar</button>
                    <?php endif; ?>
                </div>
                <h3 class="announcement-title"><?= h($a['titulo']) ?></h3>
                <p class="announcement-meta">Publicado por <?= h($a['autor']) ?></p>
                <div class="announcement-body"><?= nl2br(h($a['contenido'])) ?></div>
            </div>
        <?php endforeach; ?>

        <!-- Paginación -->
        <?php if ($totalPaginas > 1): ?>
            <div class="pagination" style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
                <?php if ($pagina > 1): ?>
                    <a href="?p=<?= $pagina - 1 ?><?= $tipoActual ? '&tipo=' . urlencode($tipoActual) : '' ?>" class="btn btn-sm">&laquo; Anterior</a>
                <?php endif; ?>
                <span style="padding:6px 12px;">Página <?= $pagina ?> de <?= $totalPaginas ?> (<?= $total ?> comunicados)</span>
                <?php if ($pagina < $totalPaginas): ?>
                    <a href="?p=<?= $pagina + 1 ?><?= $tipoActual ? '&tipo=' . urlencode($tipoActual) : '' ?>" class="btn btn-sm">Siguiente &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal de confirmación delete -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;"
     onclick="if(event.target===this)cerrarEliminar()">
    <div style="background:var(--color-bg);padding:24px;border-radius:var(--radius);max-width:400px;width:90%;">
        <h3 style="margin:0 0 12px;">Eliminar comunicado</h3>
        <p id="deleteMsg" style="margin-bottom:16px;"></p>
        <form method="POST" action="<?= APP_URL ?>/modules/communication/delete.php" style="display:flex;gap:8px;justify-content:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="id" id="deleteId" value="">
            <button type="button" class="btn btn-link" onclick="cerrarEliminar()">Cancelar</button>
            <button type="submit" class="btn btn-danger">Eliminar</button>
        </form>
    </div>
</div>

<script>
function confirmarEliminar(id, titulo) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteMsg').textContent = '¿Eliminar el comunicado "' + titulo + '"? Esta acción no se puede deshacer.';
    document.getElementById('deleteModal').style.display = 'flex';
}
function cerrarEliminar() {
    document.getElementById('deleteModal').style.display = 'none';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarEliminar();
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
