<?php

date_default_timezone_set('America/Mexico_City');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

if (!empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $appDir = rtrim(dirname(str_replace('\\', '/', __DIR__)), '/');
    if (str_starts_with($appDir, $docRoot)) {
        $relativePath = substr($appDir, strlen($docRoot));
        $segments = explode('/', ltrim($relativePath, '/'));
        $encodedSegments = array_map('rawurlencode', $segments);
        $basePath = '/' . implode('/', $encodedSegments);
        define('APP_URL', rtrim($scheme . '://' . $host . $basePath, '/'));
    } else {
        define('APP_URL', env('APP_URL', 'http://localhost/Sistema%20RH'));
    }
} else {
    define('APP_URL', env('APP_URL', 'http://localhost/Sistema%20RH'));
}
define('APP_NAME', env('APP_NAME', 'Sistema Integral RH'));
define('APP_ENV', env('APP_ENV', 'development'));

define('UPLOAD_MAX_SIZE', (int)env('UPLOAD_MAX_SIZE', 5 * 1024 * 1024));
define('UPLOAD_ALLOWED_TYPES', ['application/pdf', 'image/jpeg', 'image/png']);

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('UPLOAD_PATH', BASE_PATH . 'uploads' . DIRECTORY_SEPARATOR);

define('SESSION_TIMEOUT', (int)env('SESSION_TIMEOUT', 1800));

define('LATE_THRESHOLD', env('LATE_THRESHOLD', '09:05'));
define('JORNADA_HORAS', (int)env('JORNADA_HORAS', 8));

define('REMEMBER_ME_LIFETIME', (int)env('REMEMBER_ME_LIFETIME', 30 * 24 * 3600));

define('APP_VERSION', '1.2.0');
define('MAIL_FROM', env('MAIL_FROM', 'noreply@localhost'));

