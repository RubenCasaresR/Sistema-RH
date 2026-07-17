# Plan: Corregir detección de APP_URL

## Problema
`APP_URL` se calcula con `dirname($_SERVER['SCRIPT_NAME'])`, que varía según el script que se ejecute:
- `index.php` → `/Sistema RH` ✅
- `login.php` → `/Sistema RH/modules/auth` ❌ (CSS/JS no cargan, página distorsionada)

## Cambios necesarios

### 1. `config/app.php` (líneas 29-39)
Usar `$_SERVER['DOCUMENT_ROOT']` + `__DIR__` (ruta real del directorio `config/`) para obtener la raíz del proyecto, independientemente del script que se ejecute:

```php
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
```

### 2. `modules/auth/change_password.php` (línea 90)
Redirigir a dashboard real en vez de solo `APP_URL`:

```javascript
setTimeout(() => { window.location.href = '<?= APP_URL ?>/modules/reports/dashboard.php'; }, 1500);
```

## Verificación
- Probar acceso desde `http://localhost/Sistema%20RH/` — debe redirigir a login o dashboard
- Probar acceso directo a `http://localhost/Sistema%20RH/modules/auth/login.php` — CSS/JS deben cargar correctamente
- Probar acceso desde dominio externo — debe mantener el dominio (no redirigir a localhost)
