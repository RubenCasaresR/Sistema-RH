<?php
/**
 * Punto de entrada del sistema.
 * Redirige al login si no hay sesión, o al dashboard si ya está autenticado.
 */

require_once __DIR__ . '/includes/session.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/modules/reports/dashboard.php');
} else {
    redirect(APP_URL . '/modules/auth/login.php');
}
