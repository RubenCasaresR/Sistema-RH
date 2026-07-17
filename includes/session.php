<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
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

function requireAuth(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/modules/auth/login.php');
        exit;
    }

    if (defined('SESSION_TIMEOUT') && SESSION_TIMEOUT > 0) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            session_unset();
            session_destroy();
            header('Location: ' . APP_URL . '/modules/auth/login.php?expired=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();

    if (!empty($_SESSION['user']['force_logout'])) {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/modules/auth/login.php');
        exit;
    }

    $currentPage = basename($_SERVER['PHP_SELF']);
    if (
        !empty($_SESSION['user']['password_change_required']) &&
        $currentPage !== 'change_password.php' &&
        $currentPage !== 'logout.php'
    ) {
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
