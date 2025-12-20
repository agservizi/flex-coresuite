<?php
require_once __DIR__ . '/data.php';

setlocale(LC_TIME, 'it_IT.UTF-8');

function sanitize(?string $value): string
{
    return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
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

function get_opportunity_install_info(int $id): ?array
{
    $stmt = db()->prepare('SELECT o.id, o.opportunity_code, o.installer_id, u.name AS installer_name, u.email AS installer_email FROM opportunities o JOIN users u ON o.installer_id = u.id WHERE o.id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
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

function asset_version(?string $path = null): string
{
    $envVersion = getenv('ASSET_VERSION');

    if ($path) {
        $fullPath = realpath(__DIR__ . '/../' . ltrim($path, '/'));
        if ($fullPath && file_exists($fullPath)) {
            $mtime = (int)filemtime($fullPath);
            if ($mtime > 0) {
                return (string)$mtime;
            }
        }
    }

    if ($envVersion) {
        return (string)$envVersion;
    }

    static $fallback;
    if (!$fallback) {
        $fallback = (string)((int)filemtime(__FILE__) ?: time());
    }

    return $fallback;
}

function asset_url(string $path): string
{
    $version = asset_version($path);
    $separator = str_contains($path, '?') ? '&' : '?';
    return $path . $separator . 'v=' . rawurlencode($version);
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
        error_log('send_push_notification: no subscriptions');
        return;
    }

    $webSubs = array_filter($subscriptions, fn($sub) => !empty($sub['endpoint']));
    $nativeSubs = array_filter($subscriptions, fn($sub) => !empty($sub['token']));

    error_log('send_push_notification: ' . count($webSubs) . ' web subs, ' . count($nativeSubs) . ' native subs');

    // Send web push
    if (!empty($webSubs)) {
        $publicKey = get_vapid_public_key();
        $privateKey = get_vapid_private_key();
        if ($publicKey && $privateKey) {
            $subject = getenv('VAPID_SUBJECT') ?: ('mailto:' . (getenv('RESEND_FROM') ?: 'no-reply@example.com'));

            foreach ($webSubs as $sub) {
                $endpoint = $sub['endpoint'] ?? '';
                if (!$endpoint) continue;
                $aud = parse_url($endpoint);
                if (empty($aud['scheme']) || empty($aud['host'])) continue;
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
                        CURLOPT_POSTFIELDS => json_encode(['title' => $title, 'body' => $body]),
                    ]);
                    $result = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    error_log('Web push sent to ' . substr($endpoint, 0, 50) . '... , response: ' . $httpCode . ' ' . substr($result, 0, 100));
                } catch (Throwable $e) {
                    error_log('Web push failed: ' . $e->getMessage());
                }
            }
        }
    }

    // Send native push via FCM
    if (!empty($nativeSubs)) {
        $projectId = getenv('FCM_PROJECT_ID');
        $accessToken = get_fcm_access_token();
        if ($projectId && $accessToken) {
            error_log('FCM v1 access token obtained, sending to ' . count($nativeSubs) . ' tokens');
            foreach ($nativeSubs as $sub) {
                $token = $sub['token'] ?? '';
                if (!$token) continue;

                try {
                    $payload = [
                        'message' => [
                            'token' => $token,
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                        ],
                    ];

                    $ch = curl_init('https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send');
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => [
                            'Authorization: Bearer ' . $accessToken,
                            'Content-Type: application/json',
                        ],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_POSTFIELDS => json_encode($payload),
                    ]);
                    $result = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    error_log('FCM v1 push sent to ' . substr($token, 0, 20) . '... , response: ' . $httpCode . ' ' . substr($result, 0, 100));
                } catch (Throwable $e) {
                    error_log('FCM v1 push failed: ' . $e->getMessage());
                }
            }
        } elseif ($fcmKey = getenv('FCM_SERVER_KEY')) {
            // Fallback to legacy API
            error_log('FCM legacy key found, sending to ' . count($nativeSubs) . ' tokens');
            foreach ($nativeSubs as $sub) {
                $token = $sub['token'] ?? '';
                if (!$token) continue;

                try {
                    $payload = [
                        'to' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                    ];

                    $ch = curl_init('https://fcm.googleapis.com/fcm/send');
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => [
                            'Authorization: key=' . $fcmKey,
                            'Content-Type: application/json',
                        ],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_POSTFIELDS => json_encode($payload),
                    ]);
                    $result = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    error_log('FCM legacy push sent to ' . substr($token, 0, 20) . '... , response: ' . $httpCode . ' ' . substr($result, 0, 100));
                } catch (Throwable $e) {
                    error_log('FCM legacy push failed: ' . $e->getMessage());
                }
            }
        } else {
            error_log('No FCM credentials found');
        }
    }
}

function get_fcm_access_token(): ?string
{
    $serviceAccountJson = getenv('FCM_SERVICE_ACCOUNT_KEY');
    if (!$serviceAccountJson) {
        return null;
    }

    $serviceAccount = json_decode($serviceAccountJson, true);
    if (!$serviceAccount || !isset($serviceAccount['private_key'], $serviceAccount['client_email'])) {
        return null;
    }

    $privateKey = $serviceAccount['private_key'];
    $clientEmail = $serviceAccount['client_email'];
    $projectId = getenv('FCM_PROJECT_ID');
    if (!$projectId) {
        return null;
    }

    $now = time();
    $jwtHeader = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $jwtPayload = json_encode([
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now,
    ]);

    $headerEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($jwtHeader));
    $payloadEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($jwtPayload));
    $data = $headerEncoded . '.' . $payloadEncoded;

    $signature = '';
    openssl_sign($data, $signature, $privateKey, 'SHA256');
    $signatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    $jwt = $data . '.' . $signatureEncoded;

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_TIMEOUT => 10,
    ]);

    $result = curl_exec($ch);
    if ($result === false) {
        curl_close($ch);
        return null;
    }

    $data = json_decode($result, true);
    curl_close($ch);

    return $data['access_token'] ?? null;
}

function send_resend_email($recipient, string $subject, string $html, ?string $text = null, ?string $sender = null): bool
{
    $apiKey = getenv('RESEND_API_KEY');
    if (!$apiKey) {
        return false;
    }

    $fromAddress = $sender ?: (getenv('RESEND_FROM') ?: 'no-reply@example.com');
    $recipients = is_array($recipient) ? $recipient : array_filter(array_map('trim', explode(',', (string)$recipient)));
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
    if ($logoUrl && !preg_match('#^https?://#i', $logoUrl)) {
        $base = getenv('APP_URL') ?: (getenv('BASE_URL') ?: '');
        $base = rtrim($base, '/');
        $path = '/' . ltrim($logoUrl, '/');
        $logoUrl = $base ? $base . $path : '';
    }

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
        . '<p style="color:#6c757d;font-size:13px;">Inviato il ' . strftime('%d/%m/%Y %H:%M') . '.</p>';

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
        'Inviato il: ' . strftime('%d/%m/%Y %H:%M');

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

function notify_segnalatore_credentials(string $name, string $email, string $token): void
{
    if (!$email) {
        return;
    }
    $baseUrl = getenv('APP_URL') ?: (getenv('BASE_URL') ?: '/');
    if (!str_contains($baseUrl, 'http')) {
        $baseUrl = rtrim($baseUrl, '/');
    }
    $resetUrl = rtrim($baseUrl, '/') . '/auth/set_password.php?token=' . urlencode($token);

    $subject = 'Benvenuto in ' . (APP_NAME ?? 'Flex') . ' - accesso segnalatore';
    $body = '<p>Ciao ' . htmlspecialchars($name) . ',</p>' .
        '<p>Il tuo account segnalatore è stato creato. Imposta la tua password cliccando sul pulsante qui sotto.</p>' .
        '<table style="border-collapse:collapse;width:100%;font-size:14px;">'
        . '<tr><td style="padding:6px 0;color:#6c757d;">Email</td><td style="padding:6px 0;font-weight:600;">' . htmlspecialchars($email) . '</td></tr>'
        . '</table>'
        . '<p style="margin-top:12px;color:#6c757d;font-size:13px;">Il link scade tra 24 ore.</p>';

    $html = render_email_wrapper('Imposta la tua password', $body, 'Imposta password', $resetUrl, APP_NAME . ' · ' . (getenv('COMPANY_NAME') ?: ''));

    $text = "Ciao $name,\nIl tuo account segnalatore è stato creato.\n" .
        'Email: ' . $email . "\n" .
        'Imposta la password: ' . $resetUrl . "\n" .
        'Il link scade tra 24 ore.';

    send_resend_email($email, $subject, $html, $text);
}

function notify_installer_status_change(int $installerId, string $installerName, string $installerEmail, string $code, string $status): void
{
    if (!$installerEmail) {
        return;
    }

    $statusLabels = [
        STATUS_PENDING => 'In attesa',
        STATUS_OK => 'OK',
        STATUS_KO => 'KO',
    ];
    $statusLabel = $statusLabels[$status] ?? $status;

    $subject = 'Aggiornamento stato opportunity #' . $code;
    $body = '<p>Ciao ' . htmlspecialchars($installerName) . ',</p>' .
        '<p>Lo stato della tua opportunity è stato aggiornato.</p>' .
        '<table style="border-collapse:collapse;width:100%;font-size:14px;">'
        . '<tr><td style="padding:6px 0;color:#6c757d;">Codice</td><td style="padding:6px 0;font-weight:600;">' . htmlspecialchars($code) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#6c757d;">Nuovo stato</td><td style="padding:6px 0;font-weight:600;">' . htmlspecialchars($statusLabel) . '</td></tr>'
        . '</table>'
        . '<p style="margin-top:12px;color:#6c757d;font-size:13px;">Inviato il ' . strftime('%d/%m/%Y %H:%M') . '.</p>';

    $html = render_email_wrapper('Aggiornamento stato opportunity', $body, null, null, APP_NAME . ' · ' . (getenv('COMPANY_NAME') ?: ''));

    $text = "Ciao $installerName,\nLo stato della tua opportunity è stato aggiornato.\n" .
        'Codice: ' . $code . "\n" .
        'Nuovo stato: ' . $statusLabel . "\n" .
        'Inviato il: ' . strftime('%d/%m/%Y %H:%M');

    send_resend_email($installerEmail, $subject, $html, $text);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
