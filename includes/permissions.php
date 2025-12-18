<?php
require_once __DIR__ . '/data.php';

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /auth/login.php');
        exit;
    }
}

function require_role(string $role): void
{
    require_login();
    $user = current_user();
    $required = explode('|', $role);
    if (!$user || !in_array($user['role'], $required, true)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Accesso negato';
        exit;
    }
}

function is_admin(): bool
{
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

function is_installer(): bool
{
    $user = current_user();
    return $user && $user['role'] === 'installer';
}
