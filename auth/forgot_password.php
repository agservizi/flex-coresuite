<?php
require_once __DIR__ . '/../includes/permissions.php';
// No role required for forgot password

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email)) {
        $error = 'Inserisci un indirizzo email valido.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Formato email non valido.';
    } else {
        // Check if user exists
        $stmt = db()->prepare('SELECT id, name FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if ($user) {
            // Generate reset token
            $token = generate_password_reset($user['id']);
            // Send email
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/auth/reset_password.php?token=" . urlencode($token);
            $subject = 'Reset Password - Flex Coresuite';
            $body = '<p>Ciao ' . htmlspecialchars($user['name']) . ',</p>' .
                '<p>Hai richiesto il reset della password per il tuo account Flex Coresuite.</p>' .
                '<p>Clicca sul link seguente per impostare una nuova password:</p>' .
                '<p><a href="' . htmlspecialchars($resetLink) . '">' . htmlspecialchars($resetLink) . '</a></p>' .
                '<p>Il link è valido per 24 ore.</p>' .
                '<p>Se non hai richiesto questo reset, ignora questa email.</p>';
            $html = render_email_wrapper('Reset Password', $body, null, null, APP_NAME . ' · ' . (getenv('COMPANY_NAME') ?: ''));
            $text = "Ciao {$user['name']},\n\nHai richiesto il reset della password per il tuo account Flex Coresuite.\n\nClicca sul link seguente per impostare una nuova password:\n\n{$resetLink}\n\nIl link è valido per 24 ore.\n\nSe non hai richiesto questo reset, ignora questa email.";
            send_resend_email($email, $subject, $html, $text);
            $success = 'Se l\'email è registrata, riceverai un link per il reset della password.';
        } else {
            $success = 'Se l\'email è registrata, riceverai un link per il reset della password.';
        }
    }
}

$pageTitle = 'Dimenticato Password';
$bottomNav = '';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="login-bg">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="text-center mb-4">
                    <div class="logo-circle mb-3">
                        <i class="bi bi-key-fill text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h1 class="h4 fw-bold text-light mb-1">Reset Password</h1>
                    <p class="text-light small">Inserisci la tua email aziendale</p>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo sanitize($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success py-2 rounded-3" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?php echo sanitize($success); ?>
                    </div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <?php echo csrf_field(); ?>
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control border-0 bg-light rounded-3" id="email" name="email" placeholder="email" required>
                        <label for="email" class="text-light">
                            <i class="bi bi-envelope-fill me-2"></i>Email aziendale
                        </label>
                    </div>
                    <button class="btn btn-primary w-100 btn-pill py-2 fw-semibold shadow-sm" type="submit">
                        <i class="bi bi-send-fill me-2"></i>Invia Link Reset
                    </button>
                </form>
                <div class="text-center mt-4">
                    <a href="/auth/login.php" class="text-light text-decoration-none fw-semibold">
                        <i class="bi bi-arrow-left me-1"></i>Torna al Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    overflow: hidden;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-bg {
    height: 100vh;
    display: flex;
    align-items: center;
}

.logo-circle {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.form-floating > .form-control:focus {
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(248,249,250,0.9) 100%);
    border: 2px solid rgba(255,255,255,0.8);
    color: #333 !important;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, rgba(255,255,255,1) 0%, rgba(248,249,250,1) 100%);
    border-color: rgba(255,255,255,1);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

a.text-light:hover {
    opacity: 0.8;
    text-decoration: underline !important;
}

@media (max-width: 768px) {
}
</style>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>