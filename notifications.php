<?php
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();
header('Content-Type: application/json');

function json_response(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user = current_user();
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    json_response(401, ['error' => 'Unauthorized']);
}

if ($method === 'GET') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $items = list_notifications($userId, $limit, $offset);
    $unread = count_unread_notifications($userId);
    json_response(200, ['notifications' => $items, 'unread' => $unread]);
}

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
if (!verify_csrf_header()) {
    json_response(419, ['error' => 'CSRF token mancante o non valido']);
}

$action = $payload['action'] ?? '';

if ($method === 'POST' && $action === 'add') {
    $title = sanitize($payload['title'] ?? 'Info');
    $body = sanitize($payload['body'] ?? '');
    $type = sanitize($payload['type'] ?? 'info');
    $id = create_notification($userId, $title, $body, $type);
    json_response(201, ['id' => $id]);
}

if ($method === 'POST' && $action === 'mark_read') {
    mark_notifications_read($userId);
    json_response(200, ['unread' => count_unread_notifications($userId)]);
}

if ($method === 'POST' && $action === 'clear') {
    clear_notifications($userId);
    json_response(200, ['cleared' => true, 'unread' => 0]);
}

json_response(405, ['error' => 'Metodo non supportato']);
