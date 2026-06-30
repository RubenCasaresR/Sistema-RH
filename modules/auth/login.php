<?php
/**
 * Pantalla de inicio de sesión.
 * Minimalista: fondo limpio, formulario centrado, sin elementos innecesarios.
 */

require_once __DIR__ . '/../../config/app.php';
header('Content-Type: text/html; charset=utf-8');

// Si ya hay sesión, redirigir al dashboard
require_once __DIR__ . '/../../includes/session.php';
if (isLoggedIn()) {
    redirect(APP_URL . '/modules/reports/dashboard.php');
}

$pageTitle = 'Iniciar sesión';
$extraCss = ['login'];
$extraJs = ['login'];

$expiredMsg = isset($_GET['expired']) ? 'Sesión expirada por inactividad. Inicia sesión nuevamente.' : null;

// Generar token CSRF para el formulario
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
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
            </svg>
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
                <input type="text" id="username" name="username" placeholder="Tu usuario" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="Tu contraseña" required>
            </div>

            <div id="loginError" class="form-error" style="display:none;"></div>

            <button type="submit" class="btn btn-primary btn-block" id="loginBtn">
                Entrar
            </button>
        </form>
    </div>

    <script src="<?= APP_URL ?>/assets/js/main.js?v=<?= APP_VERSION ?>"></script>
    <script src="<?= APP_URL ?>/assets/js/login.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
