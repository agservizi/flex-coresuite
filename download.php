<?php
require_once __DIR__ . '/includes/permissions.php';
require_login();
require_once __DIR__ . '/includes/helpers.php';

$docId = (int)($_GET['id'] ?? 0);
if (!$docId) {
    http_response_code(400);
    echo 'ID mancante';
    exit;
}

$doc = get_segnalazione_doc($docId);
if (!$doc) {
    http_response_code(404);
    echo 'Documento non trovato';
    exit;
}

$user = current_user();
$canAccess = false;
if ($user['role'] === 'admin') {
    $canAccess = true;
} elseif ($user['role'] === 'segnalatore' && (int)$doc['created_by'] === (int)$user['id']) {
    $canAccess = true;
}

if (!$canAccess) {
    http_response_code(403);
    echo 'Accesso negato';
    exit;
}

$base = realpath(UPLOAD_DIR) ?: null;
$relative = $doc['path'] ?? '';
$absolute = $relative ? realpath(__DIR__ . $relative) : null;
if (!$absolute || !$base || strpos($absolute, $base) !== 0 || !is_file($absolute)) {
    http_response_code(404);
    echo 'File non disponibile';
    exit;
}

$mime = $doc['mime'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($absolute));
header('Content-Disposition: inline; filename="' . basename($doc['original_name'] ?: 'documento') . '"');
readfile($absolute);
exit;
