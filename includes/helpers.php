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

function verify_csrf_header(): bool
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
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

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function get_vapid_public_key(): ?string
{
    $key = getenv('VAPID_PUBLIC_KEY') ?: null;
    return $key ?: null;
}

function get_vapid_private_key(): ?string
{
    $key = getenv('VAPID_PRIVATE_KEY_PEM') ?: getenv('VAPID_PRIVATE_KEY') ?: null;
    if ($key && str_contains($key, '\\n')) {
        $key = str_replace('\\n', "\n", $key);
    }
    return $key ?: null;
}

function build_vapid_jwt(string $audience, string $subject, string $privateKeyPem): string
{
    $header = base64url_encode(json_encode(['alg' => 'ES256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
    $payload = base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200, // 12h
        'sub' => $subject,
    ], JSON_UNESCAPED_SLASHES));

    $data = $header . '.' . $payload;
    $signature = '';
    if (!openssl_sign($data, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Impossibile firmare il token VAPID');
    }

    return $data . '.' . base64url_encode($signature);
}

function send_push_notification(array $subscriptions, string $title, string $body): void
{
    if (empty($subscriptions)) {
        return;
    }
    $publicKey = get_vapid_public_key();
    $privateKey = get_vapid_private_key();
    if (!$publicKey || !$privateKey) {
        return;
    }
    $subject = getenv('VAPID_SUBJECT') ?: ('mailto:' . (getenv('RESEND_FROM') ?: 'no-reply@example.com'));

    foreach ($subscriptions as $sub) {
        $endpoint = $sub['endpoint'] ?? '';
        if (!$endpoint) {
            continue;
        }
        $aud = parse_url($endpoint);
        if (empty($aud['scheme']) || empty($aud['host'])) {
            continue;
        }
        $audience = $aud['scheme'] . '://' . $aud['host'] . (!empty($aud['port']) ? ':' . $aud['port'] : '');

        try {
            $jwt = build_vapid_jwt($audience, $subject, $privateKey);
            $headers = [
                'TTL: 60',
                'Authorization: WebPush ' . $jwt,
                'Crypto-Key: p256ecdsa=' . $publicKey,
            ];

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => '',
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (Throwable $e) {
            // ignora errori push per non bloccare il flusso principale
        }
    }
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

function render_email_wrapper(string $title, string $bodyHtml, ?string $ctaLabel = null, ?string $ctaUrl = null, ?string $footer = null): string
{
    $brand = APP_NAME ?? 'Flex';
    $logoUrl = getenv('APP_LOGO_URL') ?: '';

    $ctaBlock = '';
    if ($ctaLabel && $ctaUrl) {
        $ctaBlock = '<p style="text-align:center; margin:24px 0;">'
            . '<a href="' . htmlspecialchars($ctaUrl) . '" style="background:#0d6efd;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;">'
            . htmlspecialchars($ctaLabel) . '</a></p>';
    }

    $footerHtml = $footer ? '<p style="color:#6c757d;font-size:13px;margin:0;">' . htmlspecialchars($footer) . '</p>' : '';

    $logoHtml = $logoUrl
        ? '<div style="text-align:center;margin-bottom:12px;"><img src="' . htmlspecialchars($logoUrl) . '" alt="' . htmlspecialchars($brand) . '" style="height:36px;"></div>'
        : '<div style="text-align:center;margin-bottom:12px;font-weight:800;color:#0d6efd;font-size:18px;letter-spacing:0.6px;text-transform:uppercase;">' . htmlspecialchars($brand ?: 'flex') . '</div>';

    return '<div style="background:#f6f7fb;padding:24px;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;border:1px solid #e9ecef;box-shadow:0 6px 24px rgba(0,0,0,0.05);padding:24px;">'
        . $logoHtml
        . '<h1 style="font-size:18px;margin:0 0 12px 0;color:#111;">' . htmlspecialchars($title) . '</h1>'
        . '<div style="color:#212529;font-size:15px;line-height:1.6;">' . $bodyHtml . '</div>'
        . $ctaBlock
        . '</div>'
        . '<div style="max-width:560px;margin:12px auto 0 auto;text-align:center;">' . $footerHtml . '</div>'
        . '</div>';
}

function notify_new_opportunity_email(array $op): void
{
    $to = getenv('OPPORTUNITY_ALERT_TO');
    if (!$to) {
        return;
    }

    $subject = 'Nuova opportunity #' . ($op['opportunity_code'] ?? '');
    $body = '<p>Nuova opportunity segnalata.</p>' .
        '<table style="border-collapse:collapse;width:100%;font-size:14px;">'
        . '<tr><td style="padding:6px 0;color:#6c757d;">Codice</td><td style="padding:6px 0;font-weight:600;">' . htmlspecialchars((string)($op['opportunity_code'] ?? '')) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#6c757d;">Cliente</td><td style="padding:6px 0;font-weight:600;">' . htmlspecialchars(trim(($op['first_name'] ?? '') . ' ' . ($op['last_name'] ?? ''))) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#6c757d;">Offerta</td><td style="padding:6px 0;">' . htmlspecialchars((string)($op['offer_name'] ?? '')) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#6c757d;">Gestore</td><td style="padding:6px 0;">' . htmlspecialchars((string)($op['manager_name'] ?? '')) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#6c757d;">Commissione</td><td style="padding:6px 0;">€ ' . htmlspecialchars(number_format((float)($op['commission'] ?? 0), 2, ',', '.')) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#6c757d;">Installer</td><td style="padding:6px 0;">' . htmlspecialchars((string)($op['installer_name'] ?? '')) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#6c757d;">Email installer</td><td style="padding:6px 0;">' . htmlspecialchars((string)($op['installer_email'] ?? '')) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#6c757d;">Note</td><td style="padding:6px 0;">' . nl2br(htmlspecialchars((string)($op['notes'] ?? ''))) . '</td></tr>'
        . '</table>'
        . '<p style="color:#6c757d;font-size:13px;">Inviato il ' . date('d/m/Y H:i') . '.</p>';

    $html = render_email_wrapper('Nuova opportunity', $body, null, null, APP_NAME . ' · ' . (getenv('COMPANY_NAME') ?: ''));

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

function notify_installer_credentials(string $name, string $email, string $token): void
{
    if (!$email) {
        return;
    }
    $baseUrl = getenv('APP_URL') ?: (getenv('BASE_URL') ?: '/');
    if (!str_contains($baseUrl, 'http')) {
        $baseUrl = rtrim($baseUrl, '/');
    }
    $resetUrl = rtrim($baseUrl, '/') . '/auth/set_password.php?token=' . urlencode($token);

    $subject = 'Benvenuto in ' . (APP_NAME ?? 'Flex') . ' - credenziali di accesso';
    $body = '<p>Ciao ' . htmlspecialchars($name) . ',</p>' .
        '<p>Il tuo account installer è stato creato. Imposta la tua password cliccando sul pulsante qui sotto.</p>' .
        '<table style="border-collapse:collapse;width:100%;font-size:14px;">'
        . '<tr><td style="padding:6px 0;color:#6c757d;">Email</td><td style="padding:6px 0;font-weight:600;">' . htmlspecialchars($email) . '</td></tr>'
        . '</table>'
        . '<p style="margin-top:12px;color:#6c757d;font-size:13px;">Il link scade tra 24 ore.</p>';

    $html = render_email_wrapper('Imposta la tua password', $body, 'Imposta password', $resetUrl, APP_NAME . ' · ' . (getenv('COMPANY_NAME') ?: ''));

    $text = "Ciao $name,\nIl tuo account installer è stato creato.\n" .
        'Email: ' . $email . "\n" .
        'Imposta la password: ' . $resetUrl . "\n" .
        'Il link scade tra 24 ore.';

    send_resend_email($email, $subject, $html, $text);
}
