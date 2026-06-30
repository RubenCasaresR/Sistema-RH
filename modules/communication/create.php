<?php

require_once __DIR__ . '/../../includes/session.php';
requireAuth();
requirePermission('announcements.create');

require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $errors[] = 'Token de seguridad inválido.';
    }

    $titulo = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $tipo = $_POST['tipo'] ?? 'aviso';

    if ($titulo === '') $errors[] = 'El título es obligatorio.';
    if ($contenido === '') $errors[] = 'El contenido es obligatorio.';
    if (!in_array($tipo, ['aviso', 'circular', 'politica', 'evento'])) $errors[] = 'Tipo inválido.';

    if (count($errors) === 0) {
        try {
            $stmt = $db->prepare("INSERT INTO announcements (titulo, contenido, tipo, publicado_por) VALUES (:titulo, :contenido, :tipo, :uid)");
            $stmt->execute([
                ':titulo'    => $titulo,
                ':contenido' => $contenido,
                ':tipo'      => $tipo,
                ':uid'       => (int)$_SESSION['user_id'],
            ]);

            $annId = (int)$db->lastInsertId();
            logAudit('create', 'announcement', $annId, json_encode([
                'titulo' => $titulo,
                'tipo'   => $tipo,
            ]));
            setFlash('success', 'Comunicado publicado.');
            redirect(APP_URL . '/modules/communication/announcements.php');
        } catch (PDOException $e) {
            error_log('Error al crear anuncio: ' . $e->getMessage());
            $errors[] = 'Error al guardar.';
        }
    }
}

$csrfToken = generateCSRFToken();

$tipoLabels = [
    'aviso'    => 'Aviso',
    'circular' => 'Circular',
    'politica' => 'Política',
    'evento'   => 'Evento',
];
?>

<div class="page-header">
    <h2>Nuevo comunicado</h2>
    <a href="<?= APP_URL ?>/modules/communication/announcements.php" class="btn btn-link">&larr; Volver</a>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card" style="max-width:700px;">
    <form method="POST" action="" class="form" novalidate id="createForm">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="form-row">
            <div class="form-group flex-2">
                <label for="titulo">Título *</label>
                <input type="text" id="titulo" name="titulo" value="<?= h($_POST['titulo'] ?? '') ?>" required maxlength="200">
            </div>
            <div class="form-group">
                <label for="tipo">Tipo *</label>
                <select id="tipo" name="tipo" required>
                    <?php foreach ($tipoLabels as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($_POST['tipo'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="contenido">Contenido * <span id="charCount" style="font-weight:normal;font-size:0.8rem;color:var(--color-text-secondary);"></span></label>
            <textarea id="contenido" name="contenido" rows="8" required maxlength="10000" oninput="actualizarContador(this)"><?= h($_POST['contenido'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="btnPublicar">Publicar</button>
            <a href="<?= APP_URL ?>/modules/communication/announcements.php" class="btn btn-link">Cancelar</a>
        </div>
    </form>
</div>

<script>
function actualizarContador(el) {
    var max = el.maxLength;
    var rest = max - el.value.length;
    var span = document.getElementById('charCount');
    span.textContent = rest + ' caracteres restantes';
    span.style.color = rest < 100 ? 'var(--color-danger)' : 'var(--color-text-secondary)';
}
var ta = document.getElementById('contenido');
if (ta) actualizarContador(ta);
document.getElementById('createForm').addEventListener('submit', function() {
    var btn = document.getElementById('btnPublicar');
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid transparent;border-top-color:currentColor;border-radius:50%;animation:spinkf 0.6s linear infinite;vertical-align:middle;margin-right:6px;"></span>Publicando...';
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
