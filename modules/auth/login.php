<?php

require_once __DIR__ . '/../../config/app.php';
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../includes/session.php';
if (isLoggedIn()) {
    redirect(APP_URL . '/modules/reports/dashboard.php');
}

$pageTitle = 'Iniciar sesión';
$extraCss = ['login'];
$extraJs = ['login'];

$expiredMsg = isset($_GET['expired']) ? 'Sesión expirada por inactividad. Inicia sesión nuevamente.' : null;

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
            <div class="login-logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <h1><?= APP_NAME ?></h1>
            <p class="login-subtitle">Accede con tus credenciales</p>
        </div>

        <?php if ($expiredMsg): ?>
            <div class="alert alert-warning"><?= h($expiredMsg) ?></div>
        <?php endif; ?>

        <?php if ($flash = getFlash()): ?>
            <div class="alert alert-<?= $flash['type'] ?>"><?= h($flash['message']) ?></div>
        <?php endif; ?>

        <form id="loginForm" class="login-form" method="POST" autocomplete="off" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="form-group">
                <label for="username">Usuario</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <input type="text" id="username" name="username" placeholder="Tu usuario" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" id="password" name="password" placeholder="Tu contraseña" required>
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
            </div>

            <div class="form-options">
                <label class="checkbox-custom">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <span class="checkmark">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </span>
                    Recordarme
                </label>
                <a href="<?= APP_URL ?>/modules/auth/forgot_password.php" class="forgot-link">¿Olvidaste tu contraseña?</a>
            </div>

            <div id="loginError" class="form-error" style="display:none;"></div>

            <button type="submit" class="btn-login" id="loginBtn">
                <span class="btn-text">Entrar</span>
                <span class="spinner"></span>
            </button>
        </form>

        <div class="login-footer">
            &copy; <?= date('Y') ?> — <?= APP_NAME ?>
        </div>
    </div>

    <script src="<?= APP_URL ?>/assets/js/main.js?v=<?= APP_VERSION ?>"></script>
    <script src="<?= APP_URL ?>/assets/js/login.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
