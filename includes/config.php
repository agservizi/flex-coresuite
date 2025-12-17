<?php
require_once __DIR__ . '/env.php';
load_env(__DIR__ . '/../.env');

// Basic app metadata (env overrideable)
define('APP_NAME', getenv('APP_NAME') ?: 'Flex');
define('COMPANY_NAME', getenv('COMPANY_NAME') ?: 'AG SERVIZI VIA PLINIO 72');
define('APP_SUITE', getenv('APP_SUITE') ?: 'Coresuite');

// Basic routes
define('BASE_URL', getenv('BASE_URL') ?: '/');

// Database (choose your DSN: MySQL or PostgreSQL)
// MySQL example:  mysql:host=localhost;port=3306;dbname=flex;charset=utf8mb4
// Postgres example: pgsql:host=localhost;port=5432;dbname=flex
define('DB_DSN', getenv('DB_DSN') ?: 'mysql:host=localhost;port=3306;dbname=flex;charset=utf8mb4');
define('DB_USER', getenv('DB_USER') ?: 'flex_user');
define('DB_PASS', getenv('DB_PASS') ?: 'flex_pass');

define('DEFAULT_TIMEZONE', getenv('DEFAULT_TIMEZONE') ?: 'Europe/Rome');

date_default_timezone_set(DEFAULT_TIMEZONE);
