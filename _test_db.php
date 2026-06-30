<?php
require 'C:\xampp\htdocs\Sistema RH\config\app.php';
require 'C:\xampp\htdocs\Sistema RH\config\database.php';
try {
    $db = getDB();
    echo "OK: Conexion exitosa\n";
    $stmt = $db->query("SELECT 'Funciona' AS test");
    echo "OK: " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}