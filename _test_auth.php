<?php
$_GET['action'] = 'login';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';

$input = json_encode(['username' => 'admin', 'password' => 'test']);
file_put_contents('php://input', $input);

$_SERVER['CONTENT_TYPE'] = 'application/json';

// Simulemos la carga del API
ob_start();
require 'C:\xampp\htdocs\Sistema RH\api\auth.php';
$output = ob_get_clean();
echo $output;
