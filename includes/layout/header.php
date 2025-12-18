<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../data.php';

seed_data();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!doctype html>
<html lang="it" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <?php if (current_user()): ?>
        <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <?php endif; ?>
    <?php $vapidPublic = get_vapid_public_key(); if ($vapidPublic): ?>
        <meta name="vapid-public-key" content="<?php echo htmlspecialchars($vapidPublic, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <title><?php echo sanitize($pageTitle); ?> · <?php echo APP_NAME; ?></title>
    <meta name="theme-color" content="#0d1b2a">
    <link rel="icon" href="/public/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/public/favicon-32.png" sizes="32x32">
    <link rel="icon" href="/public/favicon-64.png" sizes="64x64">
    <link rel="apple-touch-icon" href="/public/favicon-180.png" sizes="180x180">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-base text-body">
    <div class="toast-stack" aria-live="polite" aria-atomic="true"></div>
<div class="app-shell">
    <header class="app-topbar d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
        <div>
            <div class="text-uppercase small text-muted fw-semibold"><?php echo APP_SUITE; ?></div>
            <div class="fw-bold">Flex</div>
        </div>
        <?php if (current_user()): ?>
        <div class="d-flex align-items-center gap-2">
            <div class="text-end">
                <div class="small fw-semibold text-primary text-truncate" style="max-width:120px;"><?php echo sanitize(current_user()['name']); ?></div>
                <div class="badge bg-body-tertiary text-muted text-uppercase border"><?php echo sanitize(current_user()['role']); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </header>
    <main class="app-main">
        <div class="container-fluid px-3 py-3">
    <div class="sheet-backdrop" data-sheet-select-backdrop></div>
    <div class="sheet" data-sheet-select>
        <div class="sheet-handle"></div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fw-bold" data-sheet-select-title>Seleziona</div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sheet-select-close>Chiudi</button>
        </div>
        <div class="list-group" data-sheet-select-list></div>
    </div>
