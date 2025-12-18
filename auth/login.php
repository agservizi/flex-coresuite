<?php
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/helpers.php';

if (current_user()) {
    $target = is_admin() ? '/admin/dashboard.php' : '/installer/dashboard.php';
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
                $target = $user['role'] === 'admin' ? '/admin/dashboard.php' : '/installer/dashboard.php';
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
<div class="py-4">
    <div class="card card-soft p-4">
        <div class="mb-3">
            <div class="bite">Modulo Flex</div>
            <h1 class="h4 fw-bold">Accedi</h1>
            <p class="text-muted mb-0"><?php echo COMPANY_NAME; ?></p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2" role="alert"><?php echo sanitize($error); ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?php echo csrf_field(); ?>
            <div class="form-floating mb-3">
                <input type="email" class="form-control" id="email" name="email" placeholder="email" value="<?php echo $rememberedEmail; ?>" required>
                <label for="email">Email aziendale</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                <label for="password">Password</label>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember" <?php echo $rememberedEmail ? 'checked' : ''; ?>>
                <label class="form-check-label small" for="remember">Ricorda email</label>
            </div>
            <button class="btn btn-primary w-100 btn-pill">Entra</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
