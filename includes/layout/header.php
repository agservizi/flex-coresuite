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
    <title><?php echo sanitize($pageTitle); ?> · <?php echo APP_NAME; ?></title>
    <meta name="theme-color" content="#0d1b2a">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-base text-body">
<div class="app-shell">
    <header class="app-topbar d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
        <div>
            <div class="text-uppercase small text-muted fw-semibold"><?php echo APP_SUITE; ?></div>
            <div class="fw-bold">Flex</div>
        </div>
        <?php if (current_user()): ?>
        <div class="text-end">
            <div class="small fw-semibold text-primary text-truncate" style="max-width:120px;"><?php echo sanitize(current_user()['name']); ?></div>
            <div class="badge bg-body-tertiary text-muted text-uppercase border"><?php echo sanitize(current_user()['role']); ?></div>
        </div>
        <?php endif; ?>
    </header>
    <main class="app-main">
        <div class="container-fluid px-3 py-3">
