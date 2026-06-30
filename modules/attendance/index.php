<?php
/**
 * Reportes de asistencia con tabs: Lista, Calendario, Resumen.
 * Filtros por fecha y empleado, paginación, exportación CSV, correcciones.
 */

require_once __DIR__ . "/../../includes/session.php";
requireAuth();
requirePermission("attendance.read");

$extraCss = ["attendance"];
require_once __DIR__ . "/../../includes/header.php";

$db = getDB();
$tab = $_GET["tab"] ?? "list";

$fechaInicio = $_GET["fecha_inicio"] ?? date("Y-m-01");
$fechaFin    = $_GET["fecha_fin"] ?? date("Y-m-d");
$employeeId  = (int)($_GET["employee_id"] ?? 0);
$page        = max(1, (int)($_GET["page"] ?? 1));
$perPage     = 50;

$params = [":inicio" => $fechaInicio, ":fin" => $fechaFin];
$whereEmployee = "";
if ($employeeId > 0) {
    $whereEmployee = "AND a.employee_id = :emp_id";
    $params[":emp_id"] = $employeeId;
}

// ── List view with pagination ──────────────────────────────
$stmtCount = $db->prepare("SELECT COUNT(*) FROM attendance_logs a WHERE a.fecha BETWEEN :inicio AND :fin AND a.tipo = 'regular' $whereEmployee");
$stmtCount->execute($params);
$totalRegistros = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRegistros / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT a.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.puesto, e.departamento
    FROM attendance_logs a
    INNER JOIN employees e ON e.id = a.employee_id
    WHERE a.fecha BETWEEN :inicio AND :fin AND a.tipo = 'regular' $whereEmployee
    ORDER BY a.fecha DESC, e.apellido_paterno
    LIMIT :lim OFFSET :off
");
$stmt->bindValue(":lim", $perPage, PDO::PARAM_INT);
$stmt->bindValue(":off", $offset, PDO::PARAM_INT);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$logs = $stmt->fetchAll();

// Resumen KPIs
$stmtResumen = $db->prepare("
    SELECT
        COUNT(*) AS total_registros,
        SUM(CASE WHEN a.hora_entrada IS NULL THEN 1 ELSE 0 END) AS sin_entrada,
        SUM(CASE WHEN a.hora_salida IS NULL THEN 1 ELSE 0 END) AS sin_salida
    FROM attendance_logs a
    INNER JOIN employees e ON e.id = a.employee_id
    WHERE a.fecha BETWEEN :inicio AND :fin AND a.tipo = 'regular' AND e.activo = 1 $whereEmployee
");
$stmtResumen->execute($params);
$resumen = $stmtResumen->fetch();

// Lista de empleados para filtros
$emps = $db->query("SELECT id, nombre, apellido_paterno, apellido_materno FROM employees WHERE activo = 1 ORDER BY apellido_paterno")->fetchAll();

$lateThreshold = defined("LATE_THRESHOLD") ? LATE_THRESHOLD : "09:05";
$jornadaHoras = defined("JORNADA_HORAS") ? JORNADA_HORAS : 8;

$puedeExportar = can("attendance.export");
$puedeCorregir = can("attendance.correct");

$queryString = http_build_query(array_filter([
    "fecha_inicio" => $fechaInicio,
    "fecha_fin" => $fechaFin,
    "employee_id" => $employeeId ?: null,
]));
?>
<div class="page-header">
    <h2>Reporte de asistencia</h2>
    <div class="header-actions">
        <?php if (can("attendance.clock")): ?>
            <a href="<?= APP_URL ?>/modules/attendance/clock.php" class="btn btn-primary">Reloj checador</a>
        <?php endif; ?>
        <?php if ($puedeExportar): ?>
            <a href="<?= APP_URL ?>/api/attendance.php?action=export&amp;<?= $queryString ?>" class="btn btn-secondary">Exportar CSV</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <form method="GET" action="" class="search-form">
        <input type="hidden" name="tab" value="<?= h($tab) ?>">
        <div class="form-row">
            <div class="form-group">
                <label for="fecha_inicio">Fecha inicio</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= h($fechaInicio) ?>">
            </div>
            <div class="form-group">
                <label for="fecha_fin">Fecha fin</label>
                <input type="date" id="fecha_fin" name="fecha_fin" value="<?= h($fechaFin) ?>">
            </div>
            <div class="form-group">
                <label for="employee_name">Empleado</label>
                <input type="text" list="employeeList" id="employee_name" autocomplete="off" placeholder="Todos (escribir para buscar)" value="<?php $selName = ""; foreach ($emps as $e) { if ($employeeId === (int)$e["id"]) { $selName = h($e["apellido_paterno"] . " " . ($e["apellido_materno"] ?? "") . ", " . $e["nombre"]); break; } } echo $selName; ?>">
                <input type="hidden" name="employee_id" id="employee_id_hidden" value="<?= $employeeId > 0 ? $employeeId : "" ?>">
                <datalist id="employeeList">
                    <?php foreach ($emps as $e): ?>
                        <option value="<?= h($e["apellido_paterno"] . " " . ($e["apellido_materno"] ?? "") . ", " . $e["nombre"]) ?>" data-id="<?= (int)$e["id"] ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;">
                <button type="submit" class="btn btn-secondary">Filtrar</button>
            </div>
        </div>
    </form>
</div>

<div class="attendance-tabs">
    <a href="?<?= $queryString ?>&amp;tab=list" class="<?= $tab === "list" ? "active" : "" ?>">Lista</a>
    <a href="?<?= $queryString ?>&amp;tab=calendar" class="<?= $tab === "calendar" ? "active" : "" ?>">Calendario</a>
    <a href="?<?= $queryString ?>&amp;tab=summary" class="<?= $tab === "summary" ? "active" : "" ?>">Resumen</a>
</div>

<?php if ($tab === "list"): ?>

<div class="dashboard-grid" style="margin-bottom:20px;">
    <div class="kpi-card">
        <span class="kpi-label">Registros</span>
        <span class="kpi-value"><?= (int)($resumen["total_registros"] ?? 0) ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Sin entrada</span>
        <span class="kpi-value" style="color:var(--color-danger);"><?= (int)($resumen["sin_entrada"] ?? 0) ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Sin salida</span>
        <span class="kpi-value" style="color:var(--color-warning);"><?= (int)($resumen["sin_salida"] ?? 0) ?></span>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Departamento</th>
                    <th>Fecha</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Jornada</th>
                    <th>Extra</th>
                    <th>Estatus</th>
                    <?php if ($puedeCorregir): ?><th>Acciones</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (count($logs) === 0): ?>
                    <tr><td colspan="<?= $puedeCorregir ? 9 : 8 ?>" class="empty-state">Sin registros en el período seleccionado.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $nombreCompleto = $log["nombre"] . " " . $log["apellido_paterno"] . ($log["apellido_materno"] ? " " . $log["apellido_materno"] : "");
                        $status = computeAttendanceStatus($log["hora_entrada"], $log["hora_salida"], $lateThreshold, $jornadaHoras);
                        $estadoTexto = $log["estatus"] === "justificado" ? "Justificado" : $status["estado"];
                        $estadoClass = $log["estatus"] === "justificado" ? "info" : $status["class"];
                        ?>
                        <tr>
                            <td class="employee-name"><?= h($nombreCompleto) ?></td>
                            <td><?= h($log["departamento"] ?? "") ?></td>
                            <td><?= formatDate($log["fecha"]) ?></td>
                            <td><?= $log["hora_entrada"] ? date("H:i:s", strtotime($log["hora_entrada"])) : "—" ?></td>
                            <td><?= $log["hora_salida"] ? date("H:i:s", strtotime($log["hora_salida"])) : "—" ?></td>
                            <td><?= $status["jornada"] ?></td>
                            <td><?= $status["horas_extra"] > 0 ? sprintf("%.1fh", $status["horas_extra"]) : "—" ?></td>
                            <td><span class="badge badge-<?= $estadoClass ?>"><?= $estadoTexto ?></span></td>
                            <?php if ($puedeCorregir): ?>
                            <td class="actions-cell">
                                <button class="btn btn-sm btn-ghost"
                                    data-id="<?= (int)$log["id"] ?>"
                                    data-fecha="<?= $log["fecha"] ?>"
                                    data-entrada="<?= h($log["hora_entrada"] ? date("H:i:s", strtotime($log["hora_entrada"])) : "") ?>"
                                    data-salida="<?= h($log["hora_salida"] ? date("H:i:s", strtotime($log["hora_salida"])) : "") ?>"
                                    data-justificacion="<?= h($log["justificacion"] ?? "") ?>"
                                    data-estatus="<?= $log["estatus"] ?>"
                                    onclick="abrirCorreccion(this)">Editar</button>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?= $queryString ?>&amp;tab=list&amp;page=1">&laquo;</a>
            <a href="?<?= $queryString ?>&amp;tab=list&amp;page=<?= $page - 1 ?>">&lsaquo;</a>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++): ?>
            <?php if ($i === $page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?<?= $queryString ?>&amp;tab=list&amp;page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?<?= $queryString ?>&amp;tab=list&amp;page=<?= $page + 1 ?>">&rsaquo;</a>
            <a href="?<?= $queryString ?>&amp;tab=list&amp;page=<?= $totalPages ?>">&raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === "calendar"): ?>

<div class="card">
    <div class="calendar-nav">
        <button class="btn btn-ghost" onclick="cambiarMes(-1)">&lsaquo; Mes anterior</button>
        <h3 id="calendarTitle"><?= ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][(int)date('m') - 1] . ' ' . date('Y') ?></h3>
        <button class="btn btn-ghost" onclick="cambiarMes(1)">Mes siguiente &rsaquo;</button>
    </div>
    <div id="calendarGrid" class="calendar-grid">
        <div class="calendar-header">Lun</div>
        <div class="calendar-header">Mar</div>
        <div class="calendar-header">Mié</div>
        <div class="calendar-header">Jue</div>
        <div class="calendar-header">Vie</div>
        <div class="calendar-header">Sáb</div>
        <div class="calendar-header">Dom</div>
    </div>
    <div style="margin-top:12px;display:flex;gap:16px;font-size:0.8rem;color:var(--color-text-secondary);">
        <span style="display:inline-flex;align-items:center;gap:4px;"><span style="display:inline-block;width:12px;height:12px;background:var(--color-success);border-radius:2px;"></span> Asistió</span>
        <span style="display:inline-flex;align-items:center;gap:4px;"><span style="display:inline-block;width:12px;height:12px;background:var(--color-danger);border-radius:2px;"></span> Falta / Retardo</span>
        <span style="display:inline-flex;align-items:center;gap:4px;"><span style="display:inline-block;width:12px;height:12px;background:var(--color-text-light);border-radius:2px;"></span> Sin registro</span>
    </div>
    <p id="calendarEmpty" class="empty-state" style="display:none;">Selecciona un empleado para ver su calendario.</p>
</div>

<?php elseif ($tab === "summary"): ?>

<div class="card">
    <div id="summaryContainer">
        <p class="empty-state">Cargando resumen...</p>
    </div>
</div>

<?php endif; ?>

<?php if ($puedeCorregir): ?>
<!-- Modal de corrección -->
<div id="modalCorreccion" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <span class="modal-close" onclick="cerrarCorreccion()">&times;</span>
        <h3>Corregir registro de asistencia</h3>
        <form class="form correction-form" onsubmit="return guardarCorreccion(event)">
            <input type="hidden" id="corrId" value="">
            <input type="hidden" id="corrFecha" value="">
            <input type="hidden" id="corrCsrf" value="<?= generateCSRFToken() ?>">
            <div class="form-group">
                <label for="corrCampo">Campo a corregir</label>
                <select id="corrCampo" required onchange="actualizarCampoCorreccion()">
                    <option value="hora_entrada">Hora de entrada</option>
                    <option value="hora_salida">Hora de salida</option>
                    <option value="justificacion">Justificación</option>
                    <option value="estatus">Estatus</option>
                </select>
            </div>
            <div class="form-group" id="corrValorGroup">
                <label for="corrValor">Nuevo valor</label>
                <input type="text" id="corrValor" required>
            </div>
            <div class="form-group">
                <label for="corrMotivo">Motivo de la corrección</label>
                <textarea id="corrMotivo" rows="3" required placeholder="Explica por qué se realiza esta corrección"></textarea>
            </div>
            <div id="corrHistory" style="margin-top:12px;border-top:1px solid var(--color-border);padding-top:12px;display:none;">
                <h4 style="font-size:0.9rem;margin-bottom:8px;">Historial de correcciones</h4>
                <div id="corrHistoryList"></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="corrBtn">Guardar corrección</button>
                <button type="button" class="btn btn-secondary" onclick="cerrarCorreccion()">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
let calendarioMes = <?= (int)date("m") ?>;
let calendarioAnio = <?= (int)date("Y") ?>;
let calendarioEmpId = <?= $employeeId > 0 ? $employeeId : "null" ?>;

function abrirCorreccion(btn) {
    document.getElementById("corrId").value = btn.dataset.id;
    document.getElementById("corrFecha").value = btn.dataset.fecha;
    document.getElementById("corrCampo").value = "hora_entrada";
    document.getElementById("corrValor").value = btn.dataset.entrada;
    document.getElementById("corrMotivo").value = "";
    document.getElementById("modalCorreccion").classList.add("modal-open");
    document.getElementById("corrBtn").disabled = false;
    document.getElementById("corrBtn").textContent = "Guardar corrección";
    cargarHistorialCorrecciones(btn.dataset.id);
}

function cerrarCorreccion() {
    document.getElementById("modalCorreccion").classList.remove("modal-open");
}

function actualizarCampoCorreccion() {
    const campo = document.getElementById("corrCampo").value;
    const input = document.getElementById("corrValor");
    if (campo === "hora_entrada" || campo === "hora_salida") {
        input.type = "time";
        input.placeholder = "HH:mm:ss";
    } else if (campo === "justificacion") {
        input.type = "text";
        input.placeholder = "Ej. Cita médica justificada";
    } else if (campo === "estatus") {
        input.type = "text";
        input.placeholder = "regular / justificado / incidencia";
    }
}

async function guardarCorreccion(event) {
    event.preventDefault();
    const id = document.getElementById("corrId").value;
    const campo = document.getElementById("corrCampo").value;
    const valor = document.getElementById("corrValor").value;
    const motivo = document.getElementById("corrMotivo").value;

    const btn = document.getElementById("corrBtn");
    btn.disabled = true;
    btn.textContent = "Guardando...";

    let valorFinal = valor;
    if (campo === "hora_entrada" || campo === "hora_salida") {
        const fecha = document.getElementById("corrFecha").value;
        if (fecha) {
            valorFinal = fecha + " " + valor + ":00";
        }
    }

    try {
        const csrf = document.getElementById("corrCsrf").value;
        const resp = await fetch("<?= APP_URL ?>/api/attendance.php?action=correct", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: parseInt(id), campo: campo, valor: valorFinal, motivo: motivo, csrf_token: csrf })
        });
        const data = await resp.json();
        if (data.success) {
            cerrarCorreccion();
            location.reload();
        } else {
            btn.disabled = false;
            btn.textContent = "Guardar corrección";
            alert("Error: " + data.message);
        }
    } catch (e) {
        btn.disabled = false;
        btn.textContent = "Guardar corrección";
        alert("Error de conexión.");
    }
}

// ── Calendar ───────────────────────────────────────────────
async function cargarCalendario() {
    if (!calendarioEmpId) {
        document.getElementById("calendarEmpty").style.display = "block";
        document.getElementById("calendarGrid").innerHTML = "";
        return;
    }
    document.getElementById("calendarEmpty").style.display = "none";
    const meses = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];
    document.getElementById("calendarTitle").textContent = meses[calendarioMes - 1] + " " + calendarioAnio;

    try {
        const resp = await fetch("<?= APP_URL ?>/api/attendance.php?action=calendar&mes=" + calendarioMes + "&anio=" + calendarioAnio + "&employee_id=" + calendarioEmpId);
        const data = await resp.json();
        if (!data.success) return;

        const d = data.data;
        const primerDia = d.primer_dia_semana;
        const totalDias = d.total_dias;
        const dias = d.dias || {};

        const hoy = new Date();
        const hoyStr = hoy.getFullYear() + "-" + String(hoy.getMonth() + 1).padStart(2, "0") + "-" + String(hoy.getDate()).padStart(2, "0");

        let html = `<div class="calendar-header">Lun</div><div class="calendar-header">Mar</div><div class="calendar-header">Mié</div><div class="calendar-header">Jue</div><div class="calendar-header">Vie</div><div class="calendar-header">Sáb</div><div class="calendar-header">Dom</div>`;

        const diaSemana = (primerDia + 6) % 7;

        for (let i = 0; i < diaSemana; i++) {
            html += `<div class="calendar-day other-month"></div>`;
        }

        for (let dia = 1; dia <= totalDias; dia++) {
            const fechaStr = calendarioAnio + "-" + String(calendarioMes).padStart(2, "0") + "-" + String(dia).padStart(2, "0");
            const info = dias[fechaStr];
            let clase = "no-record";
            let infoHtml = "";

            if (info) {
                clase = info.class;
                infoHtml = `<span class="day-info">${info.hora_entrada || "?"} - ${info.hora_salida || "?"}</span>`;
                if (info.estado !== "Regular") {
                    infoHtml += `<span class="day-status badge badge-${clase === "absent" ? "danger" : "info"}">${info.estado}</span>`;
                }
            }

            const esHoy = fechaStr === hoyStr ? " today" : "";
            html += `<div class="calendar-day ${clase}${esHoy}"><span class="day-number">${dia}</span>${infoHtml}</div>`;
        }

        document.getElementById("calendarGrid").innerHTML = html;
    } catch (e) {
        console.error("Error cargando calendario:", e);
    }
}

function cambiarMes(delta) {
    calendarioMes += delta;
    if (calendarioMes > 12) { calendarioMes = 1; calendarioAnio++; }
    if (calendarioMes < 1) { calendarioMes = 12; calendarioAnio--; }
    cargarCalendario();
}

// ── Correction history ─────────────────────────────────────
async function cargarHistorialCorrecciones(logId) {
    const container = document.getElementById("corrHistory");
    const list = document.getElementById("corrHistoryList");
    try {
        const resp = await fetch("<?= APP_URL ?>/api/attendance.php?action=correction_history&log_id=" + logId);
        const data = await resp.json();
        if (data.success && data.data.length > 0) {
            let html = "";
            for (const c of data.data) {
                html += `<div style="font-size:0.8rem;padding:4px 0;border-bottom:1px solid var(--color-border-alt);">
                    <strong>${c.campo_modificado}</strong>: ${c.valor_anterior || "—"} → ${c.valor_nuevo || "—"}
                    <br><span style="color:var(--color-text-secondary);">${c.motivo} — ${c.fecha}</span>
                </div>`;
            }
            list.innerHTML = html;
            container.style.display = "block";
        } else {
            container.style.display = "none";
        }
    } catch (e) {
        container.style.display = "none";
    }
}

// ── Employee datalist sync ─────────────────────────────────
document.addEventListener("DOMContentLoaded", function() {
    const input = document.getElementById("employee_name");
    const hidden = document.getElementById("employee_id_hidden");
    if (input && hidden) {
        input.addEventListener("input", function() {
            const opts = document.querySelectorAll("#employeeList option");
            let found = false;
            for (const opt of opts) {
                if (opt.value === this.value) {
                    hidden.value = opt.dataset.id;
                    found = true;
                    break;
                }
            }
            if (!found) hidden.value = "";
        });
    }
});

// ── Summary ────────────────────────────────────────────────
async function cargarResumen() {
    try {
        const params = new URLSearchParams({ action: "summary", fecha_inicio: "<?= $fechaInicio ?>", fecha_fin: "<?= $fechaFin ?>" });
        <?php if ($employeeId > 0): ?>
        params.set("employee_id", "<?= $employeeId ?>");
        <?php endif; ?>
        const resp = await fetch("<?= APP_URL ?>/api/attendance.php?" + params.toString());
        const data = await resp.json();
        if (!data.success || !data.data.length) {
            document.getElementById("summaryContainer").innerHTML = "<p class='empty-state'>Sin datos en el período seleccionado.</p>";
            return;
        }

        let html = `<div class="table-responsive"><table class="table"><thead><tr>
            <th>Empleado</th><th>Días</th><th>Asistencias</th><th>Faltas</th><th>Retardos</th><th>Sin salida</th><th>Horas reales</th><th>Horas esperadas</th><th>Horas extra</th>
        </tr></thead><tbody>`;

        for (const r of data.data) {
            html += `<tr>
                <td class="employee-name">${r.nombre_completo}</td>
                <td>${r.dias_habiles}</td>
                <td>${r.asistencias}</td>
                <td><span style="color:var(--color-danger)">${r.faltas}</span></td>
                <td><span style="color:var(--color-warning)">${r.retardos}</span></td>
                <td>${r.sin_salida}</td>
                <td>${r.horas_reales}h</td>
                <td>${r.horas_esperadas}h</td>
                <td>${r.horas_extra > 0 ? '<span style="color:var(--color-primary)">' + r.horas_extra + 'h</span>' : "—"}</td>
            </tr>`;
        }

        html += "</tbody></table></div>";
        document.getElementById("summaryContainer").innerHTML = html;
    } catch (e) {
        document.getElementById("summaryContainer").innerHTML = "<p class='empty-state'>Error al cargar resumen.</p>";
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const tab = "<?= $tab ?>";
    if (tab === "calendar") {
        cargarCalendario();
        if (calendarioEmpId === null) {
            document.getElementById("calendarEmpty").style.display = "block";
        }
    }
    if (tab === "summary") {
        cargarResumen();
    }
});
</script>

<?php
require_once __DIR__ . "/../../includes/footer.php";
?>
