<?php
require_once __DIR__ . '/../includes/permissions.php';
session_destroy();
header('Location: /auth/login.php');
exit;
