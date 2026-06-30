<?php

require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/session.php';
requireAuth();
requirePermission('recruitment.read');

$action = $_GET['action'] ?? '';

try {
    $db = getDB();
    switch ($action) {
        case 'get_vacancy':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM vacancies WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $v = $stmt->fetch();
            echo json_encode(['success' => (bool)$v, 'data' => $v]);
            break;
        case 'list_vacancies':
            $stmt = $db->query("SELECT v.*, COALESCE(cnt.total, 0) AS total_candidatos FROM vacancies v LEFT JOIN (SELECT vacancy_id, COUNT(*) AS total FROM candidates GROUP BY vacancy_id) cnt ON cnt.vacancy_id = v.id ORDER BY v.created_at DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
    }
} catch (PDOException $e) {
    error_log('API Recruitment error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
