# Plan: Corregir conexión a BD para InfinityFree

## Problema
Al subir el sistema a infinityfree, el `DB_HOST` queda como `localhost`, lo que hace que PHP intente conectar por socket Unix y falle con:
`SQLSTATE[HY000] [2002] No such file or directory`

## Cambios necesarios

### 1. `config/database.php` (línea 15-18)
Forzar TCP cuando el host sea `localhost`, para evitar el error de socket:

```php
$host = DB_HOST === 'localhost' ? '127.0.0.1' : DB_HOST;
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $host, DB_PORT, DB_NAME, DB_CHARSET
);
```

### 2. `.env` (subir al servidor)
Crear o modificar `.env` en el servidor con las credenciales reales.
**IMPORTANTE:** no subir el `.env` al repositorio ni compartir las credenciales.
El archivo `.env` real se crea directamente en el servidor.

El `.env` local puede seguir con `DB_HOST=localhost` para desarrollo.
