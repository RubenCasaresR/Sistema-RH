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
    case 'forgot_password':
        handleForgotPassword($input);
        break;
    case 'reset_password':
        handleResetPassword($input);
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
}

function handleLogin(array $input): void
{
    $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
        return;
    }

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $rememberMe = !empty($input['remember_me']);

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

        session_regenerate_id(true);

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

        if ($rememberMe) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
            setcookie(session_name(), session_id(), time() + REMEMBER_ME_LIFETIME, '/', '', $secure, true);
        }

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

function handleForgotPassword(array $input): void
{
    $email = trim($input['email'] ?? '');

    if ($email === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El correo electrónico es requerido.']);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Correo electrónico inválido.']);
        return;
    }

    try {
        $db = getDB();

        $stmt = $db->prepare('SELECT id, username FROM users WHERE email = :email AND activo = 1 LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => true, 'message' => 'Si el correo está registrado, recibirás instrucciones para restablecer tu contraseña.']);
            return;
        }

        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE email = :email AND used = 0");
        $stmt->execute([':email' => $email]);

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmtIns = $db->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)');
        $stmtIns->execute([
            ':email'      => $email,
            ':token'      => $token,
            ':expires_at' => $expiresAt,
        ]);

        $resetUrl = APP_URL . '/modules/auth/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email);

        $subject = 'Restablece tu contraseña - ' . APP_NAME;
        $body = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="font-family: Arial, sans-serif; padding: 24px; background: #f5f7fa;">
            <div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px;">
                <h2 style="color: #059669; margin-top: 0;">' . APP_NAME . '</h2>
                <p>Hola <strong>' . h($user['username']) . '</strong>,</p>
                <p>Recibimos una solicitud para restablecer tu contraseña. Haz clic en el botón de abajo para crear una nueva:</p>
                <p style="text-align: center; margin: 28px 0;">
                    <a href="' . $resetUrl . '" style="display: inline-block; padding: 12px 28px; background: #059669; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600;">Restablecer contraseña</a>
                </p>
                <p style="color: #6b7280; font-size: 0.85rem;">Este enlace expira en 1 hora. Si no solicitaste este cambio, ignora este correo.</p>
                <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
                <p style="color: #9ca3af; font-size: 0.78rem;">&copy; ' . date('Y') . ' ' . APP_NAME . '</p>
            </div>
        </body>
        </html>';

        sendEmail($email, $subject, $body);

        echo json_encode(['success' => true, 'message' => 'Si el correo está registrado, recibirás instrucciones para restablecer tu contraseña.']);
    } catch (PDOException $e) {
        error_log('Error en forgot_password: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
    }
}

function handleResetPassword(array $input): void
{
    $token = trim($input['token'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    if ($token === '' || $email === '' || $password === '' || $confirmPassword === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos.']);
        return;
    }

    if ($password !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden.']);
        return;
    }

    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.']);
        return;
    }

    if (!preg_match('/[A-Z]/', $password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La contraseña debe contener al menos una mayúscula.']);
        return;
    }

    if (!preg_match('/[0-9]/', $password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La contraseña debe contener al menos un número.']);
        return;
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT id, email FROM password_resets
            WHERE token = :token AND email = :email AND used = 0 AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([':token' => $token, ':email' => $email]);
        $reset = $stmt->fetch();

        if (!$reset) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El enlace es inválido o ha expirado. Solicita un nuevo restablecimiento.']);
            return;
        }

        $stmtUser = $db->prepare("SELECT id, password_hash FROM users WHERE email = :email AND activo = 1 LIMIT 1");
        $stmtUser->execute([':email' => $email]);
        $user = $stmtUser->fetch();

        if (!$user) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
            return;
        }

        if (password_verify($password, $user['password_hash'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La nueva contraseña no puede ser igual a la anterior.']);
            return;
        }

        $newHash = hashPassword($password);
        $stmtUpd = $db->prepare("UPDATE users SET password_hash = :hash, password_change_required = 0 WHERE id = :id");
        $stmtUpd->execute([':hash' => $newHash, ':id' => $user['id']]);

        $stmtUpdToken = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = :id");
        $stmtUpdToken->execute([':id' => $reset['id']]);

        logAudit('password_reset', 'user', (int)$user['id']);

        echo json_encode(['success' => true, 'message' => 'Contraseña restablecida exitosamente. Ahora puedes iniciar sesión.']);
    } catch (PDOException $e) {
        error_log('Error en reset_password: ' . $e->getMessage());
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

    $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
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
