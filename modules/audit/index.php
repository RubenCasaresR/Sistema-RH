<?php
require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('audit.read');
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$search = trim($_GET['search'] ?? '');
$action  = $_GET['action'] ?? '';
$entity  = $_GET['entity'] ?? '';
$from    = $_GET['from'] ?? '';
$to      = $_GET['to'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$params = [];
$where  = 'WHERE 1=1';

if ($search !== '') {
    $where .= ' AND (a.details LIKE :search OR u.username LIKE :search2)';
    $params[':search'] = "%$search%";
    $params[':search2'] = "%$search%";
}
if ($action !== '') {
    $where .= ' AND a.action = :action';
    $params[':action'] = $action;
}
if ($entity !== '') {
    $where .= ' AND a.entity_type = :entity';
    $params[':entity'] = $entity;
}
if ($from !== '') {
    $where .= ' AND a.created_at >= :from';
    $params[':from'] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where .= ' AND a.created_at <= :to';
    $params[':to'] = $to . ' 23:59:59';
}

$stmtCount = $db->prepare("SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON u.id = a.user_id $where");
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$stmt = $db->prepare("
    SELECT a.*, u.username
    FROM audit_log a
    LEFT JOIN users u ON u.id = a.user_id
    $where
    ORDER BY a.created_at DESC
    LIMIT :lim OFFSET :off
");
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$rows = $stmt->fetchAll();

$actionsList = $db->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$entitiesList = $db->query("SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-header">
    <h2>Auditoría</h2>
</div>

<div class="card">
    <form method="GET" action="" class="search-form">
        <div class="search-form-row">
            <div class="form-group">
                <label for="search">Buscar</label>
                <input type="search" id="search" name="search" placeholder="Usuario o detalles..." value="<?= h($search) ?>">
            </div>
            <div class="form-group">
                <label for="action">Acción</label>
                <select id="action" name="action">
                    <option value="">Todas</option>
                    <?php foreach ($actionsList as $a): ?>
                        <option value="<?= h($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= h($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="entity">Entidad</label>
                <select id="entity" name="entity">
                    <option value="">Todas</option>
                    <?php foreach ($entitiesList as $e): ?>
                        <option value="<?= h($e) ?>" <?= $entity === $e ? 'selected' : '' ?>><?= h($e) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="from">Desde</label>
                <input type="date" id="from" name="from" value="<?= h($from) ?>">
            </div>
            <div class="form-group">
                <label for="to">Hasta</label>
                <input type="date" id="to" name="to" value="<?= h($to) ?>">
            </div>
            <div class="form-group" style="align-self:flex-end;">
                <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                <a href="?" class="btn btn-sm btn-link">Limpiar</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Entidad</th>
                    <th>ID</th>
                    <th>Detalles</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rows) === 0): ?>
                    <tr><td colspan="6" class="empty-state">Sin registros de auditoría.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td style="white-space:nowrap;font-size:0.85rem;"><?= $r['created_at'] ?></td>
                            <td><?= h($r['username'] ?? '—') ?></td>
                            <td><code><?= h($r['action']) ?></code></td>
                            <td><span class="badge badge-info"><?= h($r['entity_type']) ?></span></td>
                            <td><?= (int)$r['entity_id'] ?: '—' ?></td>
                            <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;font-size:0.85rem;color:var(--color-text-secondary);"><?= h($r['details'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:1px solid var(--color-border);">
        <span style="font-size:0.85rem;color:var(--color-text-secondary);">
            Mostrando <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalRows) ?> de <?= $totalRows ?>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
