<?php
/**
 * Cierra la sesión del usuario y redirige al login.
 */

require_once __DIR__ . '/../../includes/session.php';

logAudit('logout', 'user', isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

redirect(APP_URL . '/modules/auth/login.php');
