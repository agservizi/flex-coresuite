<?php
// Lightweight .env loader for simple key=value pairs.
function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(ltrim($line), '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        [$key, $value] = $parts;
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}
