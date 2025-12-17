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

function send_resend_email($to, string $subject, string $html, ?string $text = null, ?string $from = null): bool
{
    $apiKey = getenv('RESEND_API_KEY');
    if (!$apiKey) {
        return false;
    }

    $fromAddress = $from ?: (getenv('RESEND_FROM') ?: 'no-reply@example.com');
    $recipients = is_array($to) ? $to : array_filter(array_map('trim', explode(',', (string)$to)));
    if (empty($recipients)) {
        return false;
    }

    $payload = [
        'from' => $fromAddress,
        'to' => $recipients,
        'subject' => $subject,
        'html' => $html,
    ];
    if ($text) {
        $payload['text'] = $text;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10,
    ]);

    $result = curl_exec($ch);
    if ($result === false) {
        curl_close($ch);
        return false;
    }
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $status >= 200 && $status < 300;
}

function notify_new_opportunity_email(array $op): void
{
    $to = getenv('OPPORTUNITY_ALERT_TO');
    if (!$to) {
        return;
    }

    $subject = 'Nuova opportunity #' . ($op['opportunity_code'] ?? '');
    $html = '<p>Nuova opportunity segnalata.</p>' .
        '<ul>' .
        '<li><strong>Codice:</strong> ' . htmlspecialchars((string)($op['opportunity_code'] ?? '')) . '</li>' .
        '<li><strong>Cliente:</strong> ' . htmlspecialchars(trim(($op['first_name'] ?? '') . ' ' . ($op['last_name'] ?? ''))) . '</li>' .
        '<li><strong>Offerta:</strong> ' . htmlspecialchars((string)($op['offer_name'] ?? '')) . '</li>' .
        '<li><strong>Gestore:</strong> ' . htmlspecialchars((string)($op['manager_name'] ?? '')) . '</li>' .
        '<li><strong>Commissione:</strong> € ' . htmlspecialchars(number_format((float)($op['commission'] ?? 0), 2, ',', '.')) . '</li>' .
        '<li><strong>Installer:</strong> ' . htmlspecialchars((string)($op['installer_name'] ?? '')) . '</li>' .
        '<li><strong>Email installer:</strong> ' . htmlspecialchars((string)($op['installer_email'] ?? '')) . '</li>' .
        '<li><strong>Note:</strong> ' . htmlspecialchars((string)($op['notes'] ?? '')) . '</li>' .
        '</ul>' .
        '<p>Inviato il ' . date('d/m/Y H:i') . '.</p>';

    $text = "Nuova opportunity \n" .
        'Codice: ' . ($op['opportunity_code'] ?? '') . "\n" .
        'Cliente: ' . trim(($op['first_name'] ?? '') . ' ' . ($op['last_name'] ?? '')) . "\n" .
        'Offerta: ' . ($op['offer_name'] ?? '') . "\n" .
        'Gestore: ' . ($op['manager_name'] ?? '') . "\n" .
        'Commissione: ' . number_format((float)($op['commission'] ?? 0), 2, ',', '.') . "\n" .
        'Installer: ' . ($op['installer_name'] ?? '') . "\n" .
        'Email installer: ' . ($op['installer_email'] ?? '') . "\n" .
        'Note: ' . ($op['notes'] ?? '') . "\n" .
        'Inviato il: ' . date('d/m/Y H:i');

    send_resend_email($to, $subject, $html, $text);
}

function notify_installer_credentials(string $name, string $email, string $password): void
{
    if (!$email) {
        return;
    }
    $loginUrl = getenv('APP_URL') ?: (getenv('BASE_URL') ?: '/auth/login.php');
    if (!str_contains($loginUrl, 'http')) {
        $loginUrl = rtrim($loginUrl, '/') . '/auth/login.php';
    }

    $subject = 'Benvenuto in Flex - credenziali di accesso';
    $html = '<p>Ciao ' . htmlspecialchars($name) . ',</p>' .
        '<p>Il tuo account installer è stato creato.</p>' .
        '<ul>' .
        '<li><strong>Email:</strong> ' . htmlspecialchars($email) . '</li>' .
        '<li><strong>Password:</strong> ' . htmlspecialchars($password) . '</li>' .
        '</ul>' .
        '<p>Accedi da: <a href="' . htmlspecialchars($loginUrl) . '">' . htmlspecialchars($loginUrl) . '</a></p>' .
        '<p>Per sicurezza, cambia la password al primo accesso.</p>';

    $text = "Ciao $name,\nIl tuo account installer è stato creato.\n" .
        'Email: ' . $email . "\n" .
        'Password: ' . $password . "\n" .
        'Login: ' . $loginUrl . "\n" .
        'Cambia la password al primo accesso.';

    send_resend_email($email, $subject, $html, $text);
}
