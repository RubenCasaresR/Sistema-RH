<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/session.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$pageTitle = 'Cambiar contraseña';
$extraCss = ['login'];
$extraJs = ['login'];

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — <?= $pageTitle ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/login.css?v=<?= APP_VERSION ?>">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <h1>Cambiar contraseña</h1>
            <p class="login-subtitle">Por seguridad, debes cambiar tu contraseña antes de continuar</p>
        </div>

        <div id="changePasswordError" class="form-error" style="display:none;"></div>
        <div id="changePasswordSuccess" class="form-success" style="display:none;"></div>

        <form id="changePasswordForm" class="login-form" method="POST" autocomplete="off" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="form-group">
                <label for="current_password">Contraseña actual</label>
                <input type="password" id="current_password" name="current_password" placeholder="Tu contraseña actual" required autofocus>
            </div>

            <div class="form-group">
                <label for="new_password">Nueva contraseña</label>
                <input type="password" id="new_password" name="new_password" placeholder="Mín. 8 caracteres, 1 mayúscula, 1 número" required>
                <small style="color:var(--color-text-secondary);">Mínimo 8 caracteres, al menos una mayúscula y un número.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar contraseña</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repite la nueva contraseña" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="changeBtn">
                Cambiar contraseña
            </button>
        </form>
    </div>

    <script src="<?= APP_URL ?>/assets/js/main.js?v=<?= APP_VERSION ?>"></script>
    <script>
    document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('changeBtn');
        const errDiv = document.getElementById('changePasswordError');
        const okDiv = document.getElementById('changePasswordSuccess');
        errDiv.style.display = 'none';
        okDiv.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Cambiando...';

        const data = {
            csrf_token: document.querySelector('input[name="csrf_token"]').value,
            current_password: document.getElementById('current_password').value,
            new_password: document.getElementById('new_password').value,
            confirm_password: document.getElementById('confirm_password').value
        };

        try {
            const res = await apiFetchAction('auth', 'change_password', data);
            if (res.success) {
                okDiv.textContent = res.message;
                okDiv.style.display = 'block';
                setTimeout(() => { window.location.href = '<?= APP_URL ?>'; }, 1500);
            } else {
                errDiv.textContent = res.message;
                errDiv.style.display = 'block';
            }
        } catch (err) {
            errDiv.textContent = 'Error de conexión. Intenta de nuevo.';
            errDiv.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Cambiar contraseña';
        }
    });
    </script>
</body>
</html>