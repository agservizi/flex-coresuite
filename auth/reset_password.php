<?php
require_once __DIR__ . '/../includes/permissions.php';
// No role required for reset password

$error = '';
$success = '';
$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    $error = 'Token mancante.';
} else {
    $user = find_user_by_reset_token($token);
    if (!$user) {
        $error = 'Token non valido o scaduto.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    if (empty($password) || strlen($password) < 8) {
        $error = 'La password deve essere di almeno 8 caratteri.';
    } elseif ($password !== $confirm) {
        $error = 'Le password non coincidono.';
    } else {
        try {
            set_user_password_with_token($token, $password);
            $success = 'Password aggiornata con successo. <a href="/auth/login.php">Accedi</a>';
        } catch (Exception $e) {
            $error = 'Errore nell\'aggiornamento della password.';
        }
    }
}

$pageTitle = 'Reset Password';
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
                    <h1 class="h4 fw-bold text-light mb-1">Nuova Password</h1>
                    <p class="text-light small">Imposta una nuova password sicura</p>
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
                <?php elseif ($error): ?>
                    <div class="alert alert-danger py-2 rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo sanitize($error); ?>
                    </div>
                <?php else: ?>
                <form method="post" novalidate>
                    <?php echo csrf_field(); ?>
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control border-0 bg-light rounded-3" id="password" name="password" placeholder="Password" required minlength="8">
                        <label for="password" class="text-dark">
                            <i class="bi bi-lock-fill me-2"></i>Nuova Password
                        </label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control border-0 bg-light rounded-3" id="confirm" name="confirm" placeholder="Conferma Password" required minlength="8">
                        <label for="confirm" class="text-dark">
                            <i class="bi bi-lock-fill me-2"></i>Conferma Password
                        </label>
                    </div>
                    <button class="btn btn-primary w-100 btn-pill py-2 fw-semibold shadow-sm" type="submit">
                        <i class="bi bi-check-circle-fill me-2"></i>Aggiorna Password
                    </button>
                </form>
                <?php endif; ?>
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