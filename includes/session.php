<?php

require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    $secure = defined('SESSION_COOKIE_SECURE') ? SESSION_COOKIE_SECURE : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/roles.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function isApiRequest(): bool
{
    return isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/api/') !== false;
}

function denyUnauthenticated(string $message): void
{
    if (isApiRequest()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'redirect' => APP_URL . '/modules/auth/login.php',
        ]);
        exit;
    }
    header('Location: ' . APP_URL . '/modules/auth/login.php');
    exit;
}

function requireAuth(): void
{
    if (!isLoggedIn()) {
        denyUnauthenticated('No autenticado.');
    }

    if (defined('SESSION_TIMEOUT') && SESSION_TIMEOUT > 0) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            session_unset();
            session_destroy();
            denyUnauthenticated('Sesión expirada.');
        }
    }
    $_SESSION['last_activity'] = time();

    if (!empty($_SESSION['user']['force_logout'])) {
        session_unset();
        session_destroy();
        denyUnauthenticated('Sesión finalizada.');
    }

    $currentPage = basename($_SERVER['PHP_SELF']);
    $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
    if (
        !empty($_SESSION['user']['password_change_required']) &&
        $currentPage !== 'change_password.php' &&
        $currentPage !== 'logout.php' &&
        strpos($currentScript, '/api/auth.php') === false
    ) {
        if (isApiRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Debes cambiar tu contraseña para continuar.',
                'redirect' => APP_URL . '/modules/auth/change_password.php',
            ]);
            exit;
        }
        header('Location: ' . APP_URL . '/modules/auth/change_password.php');
        exit;
    }
}

function can(string $permission): bool
{
    $user = currentUser();
    if (!$user) return false;
    if (empty($user['role_name'])) return false;
    return hasPermission($user['role_name'], $permission);
}

function requirePermission(string $permission): void
{
    if (!can($permission)) {
        if (isApiRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado: no tienes permiso para realizar esta acción.']);
            exit;
        }
        header('HTTP/1.1 403 Forbidden');
        echo '<h1>403 - Acceso denegado</h1><p>No tienes permisos para acceder a esta sección.</p>';
        exit;
    }
}

function loadUserPermissions(): array
{
    $user = currentUser();
    if (!$user) return [];
    $rolePerms = getRolePermissions();
    return $rolePerms[$user['role_name']] ?? [];
}
