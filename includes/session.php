<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize storage buckets in session
if (!isset($_SESSION['store_initialized'])) {
    $_SESSION['store_initialized'] = false;
}
