<?php

function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function calculateSeniority(string $fechaIngreso): string
{
    $ingreso = new DateTime($fechaIngreso);
    $hoy = new DateTime();
    $diff = $ingreso->diff($hoy);
    return sprintf('%d años, %d meses, %d días', $diff->y, $diff->m, $diff->d);
}

function calculateLFTHolidays(int $antiguedad): int
{
    if ($antiguedad < 1) return 0;
    if ($antiguedad === 1) return 12;
    if ($antiguedad === 2) return 14;
    if ($antiguedad === 3) return 16;
    if ($antiguedad === 4) return 18;
    if ($antiguedad >= 5 && $antiguedad <= 10) return 20;
    if ($antiguedad >= 11 && $antiguedad <= 15) return 22;
    if ($antiguedad >= 16 && $antiguedad <= 20) return 24;
    return 26;
}

function formatCurrency(?float $amount): string
{
    if ($amount === null) return '$0.00';
    return '$' . number_format($amount, 2, '.', ',');
}

function formatDate(?string $date): string
{
    if (!$date) return '';
    $dt = new DateTime($date);
    return $dt->format('d/m/Y');
}

function validateCURP(string $curp): bool
{
    return (bool) preg_match('/^[A-Z]{4}\d{6}[HM][A-Z]{5}[0-9A-Z]\d$/', strtoupper(trim($curp)));
}

function validateRFC(string $rfc): bool
{
    return (bool) preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', strtoupper(trim($rfc)));
}

function validateNSS(string $nss): bool
{
    return (bool) preg_match('/^\d{11}$/', trim($nss));
}

function calculateAge(string $fechaNacimiento): int
{
    $nacimiento = new DateTime($fechaNacimiento);
    $hoy = new DateTime();
    return (int) $nacimiento->diff($hoy)->y;
}

function slugify(string $text): string
{
    $text = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $text);
    return strtolower(trim($text, '_'));
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function generateCSRFToken(): string
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function verifyCSRFToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getClientIP(): string
{
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = explode(',', $_SERVER[$h])[0];
            if (filter_var(trim($ip), FILTER_VALIDATE_IP)) return trim($ip);
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function checkLoginAttempts(string $username): bool
{
    $db = getDB();
    $ip = getClientIP();
    $window = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM login_attempts
        WHERE (username = :username OR ip_address = :ip)
          AND attempted_at >= :window
    ");
    $stmt->execute([':username' => $username, ':ip' => $ip, ':window' => $window]);
    return (int)$stmt->fetchColumn() < 5;
}

function recordLoginAttempt(string $username): void
{
    $db = getDB();
    $ip = getClientIP();
    $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (:ip, :username)");
    $stmt->execute([':ip' => $ip, ':username' => $username]);
}

function clearLoginAttempts(string $username): void
{
    $db = getDB();
    $ip = getClientIP();
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE (username = :username OR ip_address = :ip)");
    $stmt->execute([':username' => $username, ':ip' => $ip]);
}

function logAudit(string $action, string $entityType, ?int $entityId = null, ?string $details = null): void
{
    if (!isset($_SESSION['user_id'])) return;
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address)
            VALUES (:uid, :action, :entity_type, :entity_id, :details, :ip)
        ");
        $stmt->execute([
            ':uid'         => $_SESSION['user_id'],
            ':action'      => $action,
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId,
            ':details'     => $details,
            ':ip'          => getClientIP(),
        ]);
    } catch (PDOException $e) {
        error_log('Error al registrar auditoría: ' . $e->getMessage());
    }
}

function getClientTimezoneOffset(): string
{
    return $_COOKIE['tz_offset'] ?? 'America/Mexico_City';
}
/**
 * Envía un correo electrónico simple.
 * Retorna true si se envió, false si falló.
 */
function sendEmail(string $to, string $subject, string $body): bool
{
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=utf-8\r\n";
    $headers .= "From: " . (defined('MAIL_FROM') ? MAIL_FROM : 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) . "\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";
    try {
        return mail($to, $subject, $body, $headers);
    } catch (\Throwable $e) {
        error_log("sendEmail failed: " . $e->getMessage());
        return false;
    }
}

function computeAttendanceStatus(?string $horaEntrada, ?string $horaSalida, string $lateThreshold = '09:05', int $jornadaHoras = 8): array
{
    $entrada = $horaEntrada ? new DateTime($horaEntrada) : null;
    $salida  = $horaSalida ? new DateTime($horaSalida) : null;
    $jornadaTexto = '—';
    $horasTotales = 0.0;
    $horasExtra = 0.0;
    $estado = 'Regular';
    $class = 'success';

    if ($entrada && $salida) {
        $diff = $entrada->diff($salida);
        $horasTotales = round($diff->h + $diff->i / 60, 2);
        $jornadaTexto = sprintf('%dh %dm', $diff->h, $diff->i);
        $horasExtra = max(0, $horasTotales - $jornadaHoras);

        list($horaUmbral, $minUmbral) = explode(':', $lateThreshold);
        $horaEntradaInt = (int)$entrada->format('G');
        $minEntradaInt = (int)$entrada->format('i');
        if ($horaEntradaInt > (int)$horaUmbral || ($horaEntradaInt === (int)$horaUmbral && $minEntradaInt > (int)$minUmbral)) {
            $estado = 'Retardo';
            $class = 'danger';
        }
    } elseif ($entrada && !$salida) {
        $estado = 'Sin salida';
        $class = 'warning';
    } elseif (!$entrada) {
        $estado = 'Falta';
        $class = 'danger';
    }

    return [
        'estado' => $estado,
        'class' => $class,
        'jornada' => $jornadaTexto,
        'horas_totales' => $horasTotales,
        'horas_extra' => $horasExtra,
    ];
}

