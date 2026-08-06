<?php
require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('control.incidencias.read');

$db = getDB();
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$search  = trim($_GET['search'] ?? '');
$tipo    = $_GET['tipo'] ?? '';
$resultado = $_GET['resultado'] ?? '';

$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (i.personas_involucradas LIKE :search OR i.descripcion LIKE :search OR i.area LIKE :search)';
    $params[':search'] = "%$search%";
}
if ($tipo !== '') {
    $where .= ' AND i.tipo_incidencia = :tipo';
    $params[':tipo'] = $tipo;
}
if ($resultado !== '') {
    $where .= ' AND i.resultado = :resultado';
    $params[':resultado'] = $resultado;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM control_incidencias i WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT i.*, u.username AS registrado_por_nombre
    FROM control_incidencias i
    LEFT JOIN users u ON u.id = i.registrado_por
    WHERE $where
    ORDER BY i.folio DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$incidencias = $stmt->fetchAll();

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

$extraCss = ['control'];
$pageTitle = 'Bitácora de Incidencias';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-exclamation-triangle" style="color:var(--color-warning);margin-right:8px;"></i> Bitácora de Incidencias</h2>
        <p class="greeting">Uso interno y confidencial — Área de Recursos Humanos</p>
    </div>
    <div class="header-actions">
        <?php if (can('control.incidencias.create')): ?>
            <a href="<?= APP_URL ?>/modules/control/incidencia_form.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva incidencia</a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros -->
<div class="card">
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Buscar personas, área, descripción..." value="<?= h($search) ?>" style="min-width:260px;">
        <select name="tipo">
            <option value="">Todos los tipos</option>
            <?php foreach ($tipoLabels as $k => $v): ?>
                <option value="<?= $k ?>" <?= $k === $tipo ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
        <select name="resultado">
            <option value="">Todos los resultados</option>
            <?php foreach ($resultadoLabels as $k => $v): ?>
                <option value="<?= $k ?>" <?= $k === $resultado ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-filter"></i> Filtrar</button>
        <?php if ($search !== '' || $tipo !== '' || $resultado !== ''): ?>
            <a href="<?= APP_URL ?>/modules/control/incidencias.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-xmark"></i> Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<!-- Listado -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Fecha</th>
                    <th>Persona(s)</th>
                    <th>Área</th>
                    <th>Tipo</th>
                    <th>Resultado</th>
                    <th>Registrado por</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($incidencias)): ?>
                    <tr><td colspan="8" class="text-center empty-state">No hay incidencias registradas.</td></tr>
                <?php else: ?>
                    <?php foreach ($incidencias as $inc): ?>
                        <tr>
                            <td style="font-weight:600;">#<?= $inc['folio'] ?></td>
                            <td><?= formatDate($inc['fecha']) ?></td>
                            <td class="incidencia-texto" title="<?= h($inc['personas_involucradas']) ?>"><?= h($inc['personas_involucradas']) ?></td>
                            <td><?= h($inc['area']) ?></td>
                            <td><span class="badge-tipo badge-tipo-<?= h($inc['tipo_incidencia']) ?>"><?= h($tipoLabels[$inc['tipo_incidencia']] ?? $inc['tipo_incidencia']) ?></span></td>
                            <td><span class="badge-resultado badge-resultado-<?= h($inc['resultado']) ?>"><?= h($resultadoLabels[$inc['resultado']] ?? $inc['resultado']) ?></span></td>
                            <td><?= h($inc['registrado_por_nombre']) ?></td>
                            <td class="actions-cell">
                                <a href="<?= APP_URL ?>/modules/control/incidencia_view.php?id=<?= $inc['id'] ?>" class="btn btn-sm btn-ghost">Ver</a>
                                <?php if (can('control.incidencias.update')): ?>
                                    <a href="<?= APP_URL ?>/modules/control/incidencia_form.php?id=<?= $inc['id'] ?>" class="btn btn-sm btn-ghost">Editar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $queryParams = $_GET;
            unset($queryParams['page']);
            $queryBase = http_build_query($queryParams);
            ?>
            <?php if ($page > 1): ?>
                <a href="?<?= $queryBase ?>&page=1">&laquo;</a>
                <a href="?<?= $queryBase ?>&page=<?= $page - 1 ?>">&lsaquo;</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= $queryBase ?>&page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= $queryBase ?>&page=<?= $page + 1 ?>">&rsaquo;</a>
                <a href="?<?= $queryBase ?>&page=<?= $totalPages ?>">&raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$extraJs = ['control'];
require_once __DIR__ . '/../../includes/footer.php';
?>
