<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('employees.read');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$search = trim($_GET['search'] ?? '');
$filtroDepto = $_GET['departamento'] ?? '';
$filtroContrato = $_GET['tipo_contrato'] ?? '';
$filtroEstatus = $_GET['estatus'] ?? 'activos';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$departments = $db->query("SELECT DISTINCT departamento FROM employees WHERE departamento IS NOT NULL AND departamento != '' ORDER BY departamento")->fetchAll(PDO::FETCH_COLUMN);

$tiposContrato = ['Base', 'Confianza', 'Temporal', 'Honorarios', 'Outsourcing', 'Becario'];

$where = 'WHERE 1=1';
$params = [];

if ($filtroEstatus === 'activos') {
    $where .= ' AND e.activo = 1';
} elseif ($filtroEstatus === 'inactivos') {
    $where .= ' AND e.activo = 0';
}

if ($search !== '') {
    $where .= ' AND (e.nombre LIKE :q OR e.apellido_paterno LIKE :q OR e.curp LIKE :q OR e.rfc LIKE :q OR e.puesto LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

if ($filtroDepto !== '') {
    $where .= ' AND e.departamento = :depto';
    $params[':depto'] = $filtroDepto;
}

if ($filtroContrato !== '') {
    $where .= ' AND e.tipo_contrato = :contrato';
    $params[':contrato'] = $filtroContrato;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM employees e $where");
$countStmt->execute($params);
$totalEmpleados = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalEmpleados / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT e.id, e.nombre, e.apellido_paterno, e.apellido_materno, e.curp, e.rfc,
           e.puesto, e.departamento, e.fecha_ingreso, e.activo, e.tipo_contrato,
           e.foto_url, e.email, e.telefono
    FROM employees e
    $where
    ORDER BY e.apellido_paterno, e.nombre
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$employees = $stmt->fetchAll();

$puedeExportar = can('employees.export');
?>

<div class="page-header">
    <h2>Empleados <?= $totalEmpleados > 0 ? "<span class=\"badge badge-info\">$totalEmpleados</span>" : '' ?></h2>
    <div class="header-actions">
        <?php if (can('employees.create')): ?>
            <a href="<?= APP_URL ?>/modules/employees/create.php" class="btn btn-primary">+ Nuevo</a>
        <?php endif; ?>
        <?php if ($puedeExportar && $totalEmpleados > 0): ?>
            <a href="<?= APP_URL ?>/api/employees.php?action=export&<?= http_build_query(['search' => $search, 'departamento' => $filtroDepto, 'tipo_contrato' => $filtroContrato, 'estatus' => $filtroEstatus]) ?>" class="btn btn-secondary">Exportar CSV</a>
        <?php endif; ?>
        <?php if (can('employees.create')): ?>
            <a href="<?= APP_URL ?>/modules/employees/import.php" class="btn btn-secondary">Importar CSV</a>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="" class="search-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
        <div class="form-group" style="flex:2;min-width:200px;margin:0;">
            <input type="text" name="search" placeholder="Buscar por nombre, CURP, RFC o puesto..." value="<?= h($search) ?>">
        </div>
        <div class="form-group" style="flex:1;min-width:140px;margin:0;">
            <select name="departamento">
                <option value="">Todos los deptos.</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= h($d) ?>" <?= $filtroDepto === $d ? 'selected' : '' ?>><?= h($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:1;min-width:130px;margin:0;">
            <select name="tipo_contrato">
                <option value="">Todos los contratos</option>
                <?php foreach ($tiposContrato as $tc): ?>
                    <option value="<?= $tc ?>" <?= $filtroContrato === $tc ? 'selected' : '' ?>><?= $tc ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:0 0 120px;margin:0;">
            <select name="estatus">
                <option value="activos" <?= $filtroEstatus === 'activos' ? 'selected' : '' ?>>Activos</option>
                <option value="inactivos" <?= $filtroEstatus === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
                <option value="todos" <?= $filtroEstatus === 'todos' ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filtrar</button>
        <?php if ($search !== '' || $filtroDepto !== '' || $filtroContrato !== '' || $filtroEstatus !== 'activos'): ?>
            <a href="<?= APP_URL ?>/modules/employees/index.php" class="btn btn-link">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:36px;"></th>
                    <th>Nombre</th>
                    <th>CURP</th>
                    <th>RFC</th>
                    <th>Puesto</th>
                    <th>Departamento</th>
                    <th>Contrato</th>
                    <th>Ingreso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($employees) === 0): ?>
                    <tr><td colspan="9" class="text-center empty-state">No se encontraron empleados.</td></tr>
                <?php else: ?>
                    <?php $toggleToken = $_SESSION['csrf_token'] ?? generateCSRFToken(); ?>
                    <?php foreach ($employees as $emp): ?>
                        <tr class="<?= !$emp['activo'] ? 'row-inactive' : '' ?>">
                            <td>
                                <?php if ($emp['foto_url']): ?>
                                    <img src="<?= APP_URL ?>/<?= $emp['foto_url'] ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                <?php else: ?>
                                    <span style="display:inline-block;width:32px;height:32px;border-radius:50%;background:var(--color-surface-alt);display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:#999;"><?= h(strtoupper(substr($emp['nombre'], 0, 1))) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= APP_URL ?>/modules/employees/view.php?id=<?= (int)$emp['id'] ?>" class="employee-name">
                                    <?= h($emp['nombre'] . ' ' . $emp['apellido_paterno'] . ($emp['apellido_materno'] ? ' ' . $emp['apellido_materno'] : '')) ?>
                                </a>
                                <?php if (!$emp['activo']): ?>
                                    <span class="badge badge-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= h($emp['curp']) ?></code></td>
                            <td><code><?= h($emp['rfc']) ?></code></td>
                            <td><?= h($emp['puesto'] ?? '—') ?></td>
                            <td><?= h($emp['departamento'] ?? '—') ?></td>
                            <td><?= h($emp['tipo_contrato'] ?? '—') ?></td>
                            <td><?= formatDate($emp['fecha_ingreso']) ?></td>
                            <td class="actions-cell">
                                <a href="<?= APP_URL ?>/modules/employees/view.php?id=<?= (int)$emp['id'] ?>" class="btn btn-sm btn-ghost">Ver</a>
                                <?php if (can('employees.update')): ?>
                                    <a href="<?= APP_URL ?>/modules/employees/edit.php?id=<?= (int)$emp['id'] ?>" class="btn btn-sm btn-ghost">Editar</a>
                                <?php endif; ?>
                                <?php if (can('employees.delete')): ?>
                                    <?php if ($emp['activo']): ?>
                                        <a href="<?= APP_URL ?>/modules/employees/delete.php?id=<?= (int)$emp['id'] ?>&token=<?= urlencode($toggleToken) ?>" class="btn btn-sm btn-ghost" onclick="return confirm('¿Desactivar a <?= h($emp['nombre'] . ' ' . $emp['apellido_paterno']) ?>?')">Desactivar</a>
                                    <?php else: ?>
                                        <a href="<?= APP_URL ?>/modules/employees/reactivate.php?id=<?= (int)$emp['id'] ?>&token=<?= urlencode($toggleToken) ?>" class="btn btn-sm btn-ghost" onclick="return confirm('¿Reactivar a <?= h($emp['nombre'] . ' ' . $emp['apellido_paterno']) ?>?')">Reactivar</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="display:flex;justify-content:center;gap:4px;padding:16px 0;flex-wrap:wrap;">
        <?php
        $queryParams = array_filter(['search' => $search, 'departamento' => $filtroDepto, 'tipo_contrato' => $filtroContrato, 'estatus' => $filtroEstatus]);
        $queryBase = http_build_query($queryParams);
        ?>
        <?php if ($page > 1): ?>
            <a href="?<?= $queryBase ?>&page=<?= $page - 1 ?>" class="btn btn-sm btn-ghost">&laquo; Anterior</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++): ?>
            <a href="?<?= $queryBase ?>&page=<?= $i ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-ghost' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?<?= $queryBase ?>&page=<?= $page + 1 ?>" class="btn btn-sm btn-ghost">Siguiente &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
