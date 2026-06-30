<?php
/**
 * Reloj checador — interfaz para registrar entrada/salida.
 * El empleado se identifica primero, luego marca.
 */

require_once __DIR__ . "/../../includes/session.php";
requireAuth();
requirePermission("attendance.clock");

require_once __DIR__ . "/../../includes/header.php";

$db = getDB();
$message = "";
$messageType = "";

$userId = (int)($_SESSION["user_id"]);
$stmtEmp = $db->prepare("SELECT id, nombre, apellido_paterno, apellido_materno FROM employees WHERE user_id = :uid AND activo = 1 LIMIT 1");
$stmtEmp->execute([":uid" => $userId]);
$myEmployee = $stmtEmp->fetch();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $csrfToken = $_POST["csrf_token"] ?? "";
    if (!verifyCSRFToken($csrfToken)) {
        $message = "Token de seguridad inválido.";
        $messageType = "danger";
    } else {
        $empId = (int)($_POST["employee_id"] ?? 0);
        $action = $_POST["action"];

        $stmtV = $db->prepare("SELECT id, nombre, apellido_paterno FROM employees WHERE id = :id AND activo = 1 LIMIT 1");
        $stmtV->execute([":id" => $empId]);
        $emp = $stmtV->fetch();

        if (!$emp) {
            $message = "Empleado no encontrado.";
            $messageType = "danger";
        } else {
            $hoy = date("Y-m-d");
            $ahora = date("Y-m-d H:i:s");
            $ip = getClientIP();

            try {
                if ($action === "entrada") {
                    $stmtC = $db->prepare("SELECT id FROM attendance_logs WHERE employee_id = :eid AND fecha = :fecha AND tipo = 'regular' AND hora_entrada IS NOT NULL LIMIT 1");
                    $stmtC->execute([":eid" => $empId, ":fecha" => $hoy]);

                    if ($stmtC->fetch()) {
                        $message = "Ya registraste entrada hoy.";
                        $messageType = "warning";
                    } else {
                        $stmtU = $db->prepare("
                            INSERT INTO attendance_logs (employee_id, fecha, hora_entrada, tipo, ip_address)
                            VALUES (:eid, :fecha, :hora, 'regular', :ip)
                            ON DUPLICATE KEY UPDATE hora_entrada = VALUES(hora_entrada), ip_address = VALUES(ip_address)
                        ");
                        $stmtU->execute([":eid" => $empId, ":fecha" => $hoy, ":hora" => $ahora, ":ip" => $ip]);
                        logAudit("clock_in", "attendance", $empId, "Entrada registrada desde reloj checador");
                        $message = "Entrada registrada: " . date("H:i:s");
                        $messageType = "success";
                    }
                } elseif ($action === "salida") {
                    $stmtC = $db->prepare("
                        SELECT id FROM attendance_logs
                        WHERE employee_id = :eid AND fecha = :fecha AND tipo = 'regular'
                          AND hora_entrada IS NOT NULL AND hora_salida IS NULL
                        LIMIT 1
                    ");
                    $stmtC->execute([":eid" => $empId, ":fecha" => $hoy]);
                    $log = $stmtC->fetch();

                    if (!$log) {
                        $message = "No hay entrada registrada hoy o ya registraste salida.";
                        $messageType = "warning";
                    } else {
                        $stmtU = $db->prepare("UPDATE attendance_logs SET hora_salida = :hora, ip_address = COALESCE(ip_address, :ip) WHERE id = :id");
                        $stmtU->execute([":hora" => $ahora, ":ip" => $ip, ":id" => $log["id"]]);
                        logAudit("clock_out", "attendance", $empId, "Salida registrada desde reloj checador");
                        $message = "Salida registrada: " . date("H:i:s");
                        $messageType = "success";
                    }
                }
            } catch (PDOException $e) {
                error_log("Error en clock: " . $e->getMessage());
                $message = "Error al registrar. Intente de nuevo.";
                $messageType = "danger";
            }
        }
    }
}

$estadoHoy = null;
$empSeleccionado = (int)($_POST["employee_id"] ?? ($myEmployee["id"] ?? 0));
if ($empSeleccionado > 0) {
    $stmtH = $db->prepare("
        SELECT hora_entrada, hora_salida, ip_address FROM attendance_logs
        WHERE employee_id = :eid AND fecha = :fecha AND tipo = 'regular'
        LIMIT 1
    ");
    $stmtH->execute([":eid" => $empSeleccionado, ":fecha" => date("Y-m-d")]);
    $estadoHoy = $stmtH->fetch();
}

$lateThreshold = defined("LATE_THRESHOLD") ? LATE_THRESHOLD : "09:05";
$csrfToken = generateCSRFToken();
$emps = $db->query("SELECT id, nombre, apellido_paterno, apellido_materno FROM employees WHERE activo = 1 ORDER BY apellido_paterno")->fetchAll();
?>

<div class="page-header">
    <h2>Reloj checador</h2>
    <a href="<?= APP_URL ?>/modules/attendance/index.php" class="btn btn-link">&larr; Reportes</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="card" style="max-width:500px;margin:0 auto;">
    <form method="POST" action="" class="form">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <?php if (can("employees.read")): ?>
            <div class="form-group">
                <label for="employee_name">Empleado</label>
                <input type="text" list="employeeList" id="employee_name" autocomplete="off" placeholder="Escribir para buscar" required value="<?php $selName = ""; foreach ($emps as $e) { if ($empSeleccionado === (int)$e["id"]) { $selName = h($e["apellido_paterno"] . " " . ($e["apellido_materno"] ?? "") . ", " . $e["nombre"]); break; } } echo $selName; ?>">
                <input type="hidden" name="employee_id" id="employee_id_hidden" value="<?= $empSeleccionado > 0 ? $empSeleccionado : "" ?>">
                <datalist id="employeeList">
                    <?php foreach ($emps as $e): ?>
                        <option value="<?= h($e["apellido_paterno"] . " " . ($e["apellido_materno"] ?? "") . ", " . $e["nombre"]) ?>" data-id="<?= (int)$e["id"] ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
        <?php elseif ($myEmployee): ?>
            <input type="hidden" name="employee_id" value="<?= (int)$myEmployee["id"] ?>">
            <p style="text-align:center;font-size:1.1rem;font-weight:500;">
                <?= h($myEmployee["nombre"] . " " . $myEmployee["apellido_paterno"]) ?>
            </p>
        <?php else: ?>
            <p class="alert alert-danger">No tienes un perfil de empleado vinculado. Contacta a RH.</p>
        <?php endif; ?>

        <div style="text-align:center;padding:20px 0;">
            <div style="font-size:3rem;font-weight:300;font-family:monospace;" id="relojDigital">
                <?= date("H:i:s") ?>
            </div>
            <div style="font-size:0.9rem;color:var(--color-text-secondary);">
                <?= date("l, d \d\e F \d\e\l Y") ?>
            </div>
        </div>

        <?php if ($estadoHoy): ?>
            <div style="text-align:center;margin-bottom:16px;">
                <span class="badge badge-success">Entrada: <?= date("H:i:s", strtotime($estadoHoy["hora_entrada"])) ?></span>
                <?php if ($estadoHoy["hora_salida"]): ?>
                    <span class="badge badge-info">Salida: <?= date("H:i:s", strtotime($estadoHoy["hora_salida"])) ?></span>
                <?php endif; ?>
                <?php if ($estadoHoy["ip_address"]): ?>
                    <span class="badge badge-secondary">IP: <?= h($estadoHoy["ip_address"]) ?></span>
                <?php endif; ?>
            </div>
            <p style="font-size:0.8rem;text-align:center;color:var(--color-text-secondary);">
                Tolerancia de retardo: después de las <?= h($lateThreshold) ?> hrs
            </p>
        <?php endif; ?>

        <div style="display:flex;gap:16px;justify-content:center;">
            <button type="submit" name="action" value="entrada" class="btn btn-primary btn-block"
                    <?= ($estadoHoy && $estadoHoy["hora_entrada"]) ? "disabled" : "" ?>>
                Registrar entrada
            </button>
            <button type="submit" name="action" value="salida" class="btn btn-secondary btn-block"
                    <?= (!$estadoHoy || !$estadoHoy["hora_entrada"] || $estadoHoy["hora_salida"]) ? "disabled" : "" ?>>
                Registrar salida
            </button>
        </div>
    </form>
</div>

<script>
function actualizarReloj() {
    const ahora = new Date();
    const h = String(ahora.getHours()).padStart(2, "0");
    const m = String(ahora.getMinutes()).padStart(2, "0");
    const s = String(ahora.getSeconds()).padStart(2, "0");
    const el = document.getElementById("relojDigital");
    if (el) el.textContent = h + ":" + m + ":" + s;
}
setInterval(actualizarReloj, 1000);
actualizarReloj();

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
</script>

<?php
require_once __DIR__ . "/../../includes/footer.php";
?>