<?php

require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . (defined('APP_URL') ? APP_URL : '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/session.php';

$action = $_GET['action'] ?? '';

try {
    $db = getDB();

    switch ($action) {
        case 'list':
            handleList($db);
            break;
        case 'create':
            handleCreate($db);
            break;
        case 'delete':
            handleDelete($db);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
    }
} catch (PDOException $e) {
    error_log('API Announcements error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}

function handleList(PDO $db): void
{
    requireAuth();
    requirePermission('announcements.read');

    $tipo = $_GET['tipo'] ?? '';
    $params = [];
    $where = 'WHERE a.activo = 1';

    if (in_array($tipo, ['aviso', 'circular', 'politica', 'evento'])) {
        $where .= ' AND a.tipo = :tipo';
        $params[':tipo'] = $tipo;
    }

    $stmt = $db->prepare("
        SELECT a.*, u.username AS autor
        FROM announcements a
        INNER JOIN users u ON u.id = a.publicado_por
        $where
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($params);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function handleCreate(PDO $db): void
{
    requireAuth();
    requirePermission('announcements.create');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
        return;
    }

    $titulo = trim($input['titulo'] ?? '');
    $contenido = trim($input['contenido'] ?? '');
    $tipo = $input['tipo'] ?? '';

    if ($titulo === '' || $contenido === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Título y contenido requeridos.']);
        return;
    }

    if (!in_array($tipo, ['aviso', 'circular', 'politica', 'evento'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tipo inválido. Valores: aviso, circular, politica, evento.']);
        return;
    }

    $stmt = $db->prepare("INSERT INTO announcements (titulo, contenido, tipo, publicado_por) VALUES (:t, :c, :tp, :uid)");
    $stmt->execute([':t' => $titulo, ':c' => $contenido, ':tp' => $tipo, ':uid' => (int)$_SESSION['user_id']]);

    echo json_encode(['success' => true, 'message' => 'Comunicado publicado.', 'id' => (int)$db->lastInsertId()]);
}

function handleDelete(PDO $db): void
{
    requireAuth();
    requirePermission('announcements.delete');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
        return;
    }

    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        return;
    }

    $stmt = $db->prepare("UPDATE announcements SET activo = 0 WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true, 'message' => 'Comunicado eliminado.']);
}
