<?php

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'sistema_rh'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = DB_HOST === 'localhost' ? '127.0.0.1' : DB_HOST;
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET NAMES utf8mb4");
        } catch (PDOException $e) {
            error_log('Error de conexión BD: ' . $e->getMessage());
            if (APP_ENV === 'development') {
                die('Error de conexión: ' . $e->getMessage());
            }
            die('Error interno del sistema. Contacte al administrador.');
        }
    }

    return $pdo;
}