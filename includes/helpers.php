<?php
require_once __DIR__ . '/data.php';

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && hash_equals(csrf_token(), $sent);
}

function month_options(): array
{
    $months = [];
    for ($m = 1; $m <= 12; $m++) {
        $months[] = [
            'value' => $m,
            'label' => sprintf('%02d', $m),
        ];
    }
    return $months;
}

function filter_opportunities(array $options): array
{
    return get_opportunities($options);
}

function summarize(array $ops): array
{
    $summary = [
        'total' => count($ops),
        'ok' => 0,
        'ko' => 0,
        'pending' => 0,
        'commission_total' => 0,
    ];
    foreach ($ops as $op) {
        if ($op['status'] === STATUS_OK) {
            $summary['ok']++;
        } elseif ($op['status'] === STATUS_KO) {
            $summary['ko']++;
        } else {
            $summary['pending']++;
        }
        $summary['commission_total'] += (float)$op['commission'];
    }
    return $summary;
}
