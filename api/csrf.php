<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado.']);
    exit;
}

echo json_encode(['success' => true, 'token' => generateCSRFToken()]);
