<?php
require_once __DIR__ . '/includes/permissions.php';
require_login();
require_once __DIR__ . '/includes/helpers.php';

$type = $_GET['type'] ?? 'segnalazione';
$id = (int)($_GET['id'] ?? 0);
$index = (int)($_GET['index'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo 'ID mancante';
    exit;
}

$user = current_user();

if ($type === 'segnalazione') {
    $doc = get_segnalazione_doc($id);
    if (!$doc) {
        http_response_code(404);
        echo 'Documento non trovato';
        exit;
    }

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

    $relative = $doc['path'] ?? '';
    $originalName = $doc['original_name'] ?: 'documento';
    $mime = $doc['mime'] ?: 'application/octet-stream';
} elseif ($type === 'opportunity') {
    // Solo admin possono scaricare file opportunity
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo 'Accesso negato';
        exit;
    }

    $opp = get_opportunity($id);
    if (!$opp) {
        http_response_code(404);
        echo 'Opportunity non trovata';
        exit;
    }

    $notes = $opp['notes'] ?? '';
    $fileData = null;
    if (strpos($notes, '|') !== false) {
        list($text, $json) = explode('|', $notes, 2);
        $fileData = json_decode($json, true);
    }

    if (!$fileData || !isset($fileData[$index])) {
        http_response_code(404);
        echo 'File non trovato';
        exit;
    }

    $relative = $fileData[$index];
    $originalName = basename($relative);
    $mime = mime_content_type(__DIR__ . $relative) ?: 'application/octet-stream';
} else {
    http_response_code(400);
    echo 'Tipo non valido';
    exit;
}

$base = realpath(UPLOAD_DIR) ?: null;
$absolute = $relative ? realpath(__DIR__ . '/' . $relative) : null;
if (!$absolute || !$base || strpos($absolute, $base) !== 0 || !is_file($absolute)) {
    http_response_code(404);
    echo 'File non disponibile';
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($absolute));
header('Content-Disposition: inline; filename="' . $originalName . '"');
readfile($absolute);
exit;
