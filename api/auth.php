<?php

require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/session.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida.']);
    exit;
}

switch ($action) {
    case 'login':
        handleLogin($input);
        break;
    case 'change_password':
        handleChangePassword($input);
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
}

function handleLogin(array $input): void
{
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son requeridos.']);
        return;
    }

    if (!checkLoginAttempts($username)) {
        logAudit('login_blocked', 'user', null, json_encode(['username' => $username, 'reason' => 'rate_limit']));
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Demasiados intentos. Intenta de nuevo en 15 minutos.']);
        return;
    }

    try {
        $db = getDB();

        $stmt = $db->prepare('
            SELECT u.id, u.username, u.email, u.password_hash, u.activo,
                   u.password_change_required, u.force_logout, u.role_id,
                   r.nombre AS role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.username = :username
            LIMIT 1
        ');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            recordLoginAttempt($username);
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Credenciales inválidas.']);
            return;
        }

        if (!$user['activo']) {
            recordLoginAttempt($username);
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cuenta desactivada. Contacte al administrador.']);
            return;
        }

        if (!password_verify($password, $user['password_hash'])) {
            recordLoginAttempt($username);
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Credenciales inválidas.']);
            return;
        }

        clearLoginAttempts($username);

        $stmtUpd = $db->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
        $stmtUpd->execute([':id' => $user['id']]);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user'] = [
            'id'                        => (int) $user['id'],
            'username'                  => $user['username'],
            'email'                     => $user['email'],
            'role_id'                   => (int) $user['role_id'],
            'role_name'                 => $user['role_name'],
            'password_change_required'  => (bool)$user['password_change_required'],
            'force_logout'              => (bool)$user['force_logout'],
        ];
        $_SESSION['last_activity'] = time();

        logAudit('login', 'user', (int)$user['id']);

        echo json_encode([
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'user'    => [
                'username'                  => $user['username'],
                'role_name'                 => $user['role_name'],
                'password_change_required'  => (bool)$user['password_change_required'],
            ],
        ]);
    } catch (PDOException $e) {
        error_log('Error en login: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
    }
}

function handleChangePassword(array $input): void
{
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado.']);
        return;
    }

    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos.']);
        return;
    }

    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Las contraseñas nuevas no coinciden.']);
        return;
    }

    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.']);
        return;
    }

    if (!preg_match('/[A-Z]/', $newPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La contraseña debe contener al menos una mayúscula.']);
        return;
    }

    if (!preg_match('/[0-9]/', $newPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La contraseña debe contener al menos un número.']);
        return;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Contraseña actual incorrecta.']);
            return;
        }

        if (password_verify($newPassword, $user['password_hash'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La nueva contraseña no puede ser igual a la actual.']);
            return;
        }

        $newHash = hashPassword($newPassword);
        $stmtUpd = $db->prepare("UPDATE users SET password_hash = :hash, password_change_required = 0 WHERE id = :id");
        $stmtUpd->execute([':hash' => $newHash, ':id' => $_SESSION['user_id']]);

        $_SESSION['user']['password_change_required'] = false;

        logAudit('password_change', 'user', (int)$_SESSION['user_id']);

        echo json_encode(['success' => true, 'message' => 'Contraseña cambiada exitosamente.']);
    } catch (PDOException $e) {
        error_log('Error cambio contraseña: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
    }
}
