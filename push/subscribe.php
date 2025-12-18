<?php
require_once __DIR__ . '/../includes/permissions.php';
require_login();
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit;
}

if (!verify_csrf_header()) {
    http_response_code(400);
    echo json_encode(['error' => 'CSRF non valido']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload) || empty($payload['endpoint']) || empty($payload['keys']['p256dh']) || empty($payload['keys']['auth'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Payload non valido']);
    exit;
}

$endpoint = trim($payload['endpoint']);
$p256dh = trim($payload['keys']['p256dh']);
$auth = trim($payload['keys']['auth']);

try {
    save_push_subscription((int)current_user()['id'], $endpoint, $p256dh, $auth);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Impossibile salvare la subscription']);
}
