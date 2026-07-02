<?php

require_once __DIR__ . '/../../config/app.php';
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../includes/session.php';
if (isLoggedIn()) {
    redirect(APP_URL . '/modules/reports/dashboard.php');
}

$pageTitle = 'Recuperar contraseña';
$extraCss = ['login'];
$extraJs = ['login'];
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
            <div class="login-logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <h1>Recuperar contraseña</h1>
            <p class="login-subtitle">Ingresa tu correo electrónico y te enviaremos instrucciones</p>
        </div>

        <div id="forgotError" class="form-error" style="display:none;"></div>
        <div id="forgotSuccess" class="form-success" style="display:none;"></div>

        <form id="forgotForm" class="login-form" method="POST" autocomplete="off" novalidate>
            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <input type="email" id="email" name="email" placeholder="tu@correo.com" required autofocus>
                </div>
            </div>

            <button type="submit" class="btn-login" id="forgotBtn">
                <span class="btn-text">Enviar instrucciones</span>
                <span class="spinner"></span>
            </button>

            <a href="<?= APP_URL ?>/modules/auth/login.php" class="forgot-link" style="text-align:center;">Volver al inicio de sesión</a>
        </form>
    </div>

    <script src="<?= APP_URL ?>/assets/js/main.js?v=<?= APP_VERSION ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('forgotForm');
        if (!form) return;

        const errDiv = document.getElementById('forgotError');
        const okDiv = document.getElementById('forgotSuccess');
        const btn = document.getElementById('forgotBtn');
        const emailInput = document.getElementById('email');

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const email = emailInput.value.trim();

            if (!email) {
                showMsg(errDiv, 'Ingresa tu correo electrónico.');
                return;
            }

            btn.disabled = true;
            btn.classList.add('loading');
            hideMsg(errDiv);
            hideMsg(okDiv);

            try {
                const response = await fetch(APP_URL + '/api/auth.php?action=forgot_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email })
                });

                const data = await response.json();

                showMsg(okDiv, data.message || 'Revisa tu bandeja de entrada para continuar.');

                emailInput.value = '';
            } catch (err) {
                showMsg(errDiv, 'Error de conexión. Intenta de nuevo.');
            } finally {
                btn.disabled = false;
                btn.classList.remove('loading');
            }
        });

        function showMsg(el, msg) {
            el.textContent = msg;
            el.style.display = 'block';
        }

        function hideMsg(el) {
            el.style.display = 'none';
            el.textContent = '';
        }
    });
    </script>
</body>
</html>
