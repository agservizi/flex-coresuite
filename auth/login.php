<?php
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/helpers.php';

if (current_user()) {
    $user = current_user();
    $target = $user['role'] === 'admin'
        ? '/admin/dashboard.php'
        : ($user['role'] === 'segnalatore' ? '/segnalatore/dashboard.php' : '/installer/dashboard.php');
    header("Location: {$target}");
    exit;
}

$error = null;
$rememberedEmail = isset($_COOKIE['flex_email']) ? sanitize($_COOKIE['flex_email']) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $emailRaw = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $email = sanitize($emailRaw);
        $emailKey = strtolower(trim($emailRaw));
        if (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email non valida';
        } elseif (strlen($password) < 8 || strlen($password) > 128) {
            $error = 'Password non valida';
        } elseif (is_login_rate_limited($emailKey, $ip)) {
            $error = 'Troppi tentativi di accesso. Riprova tra pochi minuti.';
        } else {
            $user = find_user_by_email($email);
            if ($user && empty($user['password'])) {
                $error = 'Devi impostare la password dal link ricevuto via email.';
            } elseif ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = $user;
                record_login_attempt($emailKey, $ip, true);
                if ($remember) {
                    setcookie('flex_email', $email, [
                        'expires' => time() + 60 * 60 * 24 * 30,
                        'path' => '/',
                        'secure' => !empty($_SERVER['HTTPS']),
                        'httponly' => false,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    setcookie('flex_email', '', [
                        'expires' => time() - 3600,
                        'path' => '/',
                    ]);
                }
                $target = $user['role'] === 'admin'
                    ? '/admin/dashboard.php'
                    : ($user['role'] === 'segnalatore' ? '/segnalatore/dashboard.php' : '/installer/dashboard.php');
                header("Location: {$target}");
                exit;
            }
            record_login_attempt($emailKey, $ip, false);
            $error = 'Credenziali non valide';
        }
    }
}

$pageTitle = 'Login';
$bottomNav = '';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="login-bg">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="text-center mb-4">
                    <div class="logo-circle mb-3">
                        <i class="bi bi-shield-lock-fill text-primary" style="font-size: 3rem;"></i>
                    </div>
                        <h1 class="h4 fw-bold text-light mb-1">Accedi a Flex</h1>
                        <p class="text-light small"><?php echo COMPANY_NAME; ?> - Coresuite</p>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo sanitize($error); ?>
                    </div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <?php echo csrf_field(); ?>
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control border-0 bg-light rounded-3" id="email" name="email" placeholder="email" value="<?php echo $rememberedEmail; ?>" required>
                        <label for="email" class="text-dark">
                            <i class="bi bi-envelope-fill me-2"></i>Email aziendale
                        </label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control border-0 bg-light rounded-3" id="password" name="password" placeholder="Password" required>
                        <label for="password" class="text-dark">
                            <i class="bi bi-lock-fill me-2"></i>Password
                        </label>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember" <?php echo $rememberedEmail ? 'checked' : ''; ?>>
                            <label class="form-check-label small text-dark" for="remember">Ricorda email</label>
                        </div>
                        <a href="/auth/forgot_password.php" class="text-light text-decoration-none fw-semibold">Dimenticato?</a>
                    </div>
                    <button class="btn btn-primary w-100 btn-pill py-2 fw-semibold shadow-sm" type="submit">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Entra
                    </button>
                </form>
                <div class="text-center mt-4">
                    <p class="text-light small mb-0">© 2025 <?php echo COMPANY_NAME; ?> - Accesso sicuro</p>
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
