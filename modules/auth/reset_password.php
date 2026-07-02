<?php

require_once __DIR__ . '/../../config/app.php';
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../includes/session.php';
if (isLoggedIn()) {
    redirect(APP_URL . '/modules/reports/dashboard.php');
}

$token = trim($_GET['token'] ?? '');
$email = trim($_GET['email'] ?? '');

if ($token === '' || $email === '') {
    $invalidLink = true;
} else {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id FROM password_resets
            WHERE token = :token AND email = :email AND used = 0 AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([':token' => $token, ':email' => $email]);
        $valid = (bool)$stmt->fetch();
        $invalidLink = !$valid;
    } catch (PDOException $e) {
        error_log('Error validando token: ' . $e->getMessage());
        $invalidLink = true;
    }
}

$pageTitle = 'Restablecer contraseña';
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
            <h1>Restablecer contraseña</h1>
            <p class="login-subtitle">Elige una nueva contraseña para tu cuenta</p>
        </div>

        <?php if ($invalidLink): ?>
            <div class="form-error" style="display:block;">
                El enlace es inválido o ha expirado. <a href="<?= APP_URL ?>/modules/auth/forgot_password.php" style="color:inherit;text-decoration:underline;">Solicita uno nuevo</a>.
            </div>
            <a href="<?= APP_URL ?>/modules/auth/login.php" class="forgot-link" style="text-align:center;margin-top:16px;display:block;">Volver al inicio de sesión</a>
        <?php else: ?>

        <div id="resetError" class="form-error" style="display:none;"></div>
        <div id="resetSuccess" class="form-success" style="display:none;"></div>

        <form id="resetForm" class="login-form" method="POST" autocomplete="off" novalidate>
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <input type="hidden" name="email" value="<?= h($email) ?>">

            <div class="form-group">
                <label for="password">Nueva contraseña</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" id="password" name="password" placeholder="Mín. 8 caracteres, 1 mayúscula, 1 número" required autofocus>
                    <button type="button" class="password-toggle" id="passwordToggle" tabindex="-1" aria-label="Mostrar contraseña">
                        <svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                            <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
                <small style="color:var(--color-text-secondary);font-size:0.78rem;">Mínimo 8 caracteres, al menos una mayúscula y un número.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar contraseña</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repite la nueva contraseña" required>
                </div>
            </div>

            <button type="submit" class="btn-login" id="resetBtn">
                <span class="btn-text">Restablecer contraseña</span>
                <span class="spinner"></span>
            </button>
        </form>
        <?php endif; ?>
    </div>

    <script src="<?= APP_URL ?>/assets/js/main.js?v=<?= APP_VERSION ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('resetForm');
        if (!form) return;

        var toggle = document.getElementById('passwordToggle');
        var pwdInput = document.getElementById('password');
        if (toggle && pwdInput) {
            toggle.addEventListener('click', function () {
                var isPassword = pwdInput.type === 'password';
                pwdInput.type = isPassword ? 'text' : 'password';
                toggle.classList.toggle('visible', !isPassword);
                toggle.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
            });
        }

        var errDiv = document.getElementById('resetError');
        var okDiv = document.getElementById('resetSuccess');
        var btn = document.getElementById('resetBtn');

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            var password = document.getElementById('password').value;
            var confirm = document.getElementById('confirm_password').value;

            if (!password || !confirm) {
                showMsg(errDiv, 'Completa todos los campos.');
                return;
            }

            if (password !== confirm) {
                showMsg(errDiv, 'Las contraseñas no coinciden.');
                return;
            }

            btn.disabled = true;
            btn.classList.add('loading');
            hideMsg(errDiv);
            hideMsg(okDiv);

            try {
                var response = await fetch(APP_URL + '/api/auth.php?action=reset_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        token: form.querySelector('input[name="token"]').value,
                        email: form.querySelector('input[name="email"]').value,
                        password: password,
                        confirm_password: confirm
                    })
                });

                var data = await response.json();

                if (data.success) {
                    showMsg(okDiv, data.message);
                    btn.disabled = true;
                    btn.classList.remove('loading');
                    setTimeout(function () {
                        window.location.href = '<?= APP_URL ?>/modules/auth/login.php';
                    }, 2000);
                } else {
                    showMsg(errDiv, data.message || 'Error al restablecer la contraseña.');
                    btn.disabled = false;
                    btn.classList.remove('loading');
                }
            } catch (err) {
                showMsg(errDiv, 'Error de conexión. Intenta de nuevo.');
                btn.disabled = false;
                btn.classList.remove('loading');
            }
        });

        function showMsg(el, msg) {
            if (!el) return;
            el.textContent = msg;
            el.style.display = 'block';
        }

        function hideMsg(el) {
            if (!el) return;
            el.style.display = 'none';
            el.textContent = '';
        }
    });
    </script>
</body>
</html>
