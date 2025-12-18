<?php
require_once __DIR__ . '/../includes/helpers.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$token = is_string($token) ? trim($token) : '';

$error = null;
$success = null;

if ($token === '') {
    $error = 'Link non valido.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        if ($password !== $confirm) {
            $error = 'Le password non coincidono.';
        } elseif (strlen($password) < 8 || strlen($password) > 128) {
            $error = 'Password non valida (8-128 caratteri).';
        } else {
            try {
                set_user_password_with_token($token, $password);
                $success = 'Password impostata correttamente. Ora puoi accedere.';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
} else {
    // GET: validate token early
    if (!find_user_by_reset_token($token)) {
        $error = 'Token non valido o scaduto.';
    }
}

$pageTitle = 'Imposta password';
$bottomNav = '';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="py-4">
    <div class="card card-soft p-4">
        <div class="mb-3">
            <div class="bite">Account</div>
            <h1 class="h4 fw-bold">Imposta password</h1>
            <p class="text-muted mb-0">Completa la configurazione del tuo account installer.</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2" role="alert"><?php echo sanitize($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success py-2" role="alert"><?php echo sanitize($success); ?></div>
            <a class="btn btn-primary w-100 btn-pill" href="/auth/login.php">Vai al login</a>
        <?php elseif (!$error): ?>
            <form method="post" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="token" value="<?php echo sanitize($token); ?>">
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Nuova password" required>
                    <label for="password">Nuova password</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Conferma password" required>
                    <label for="password_confirm">Conferma password</label>
                </div>
                <button class="btn btn-primary w-100 btn-pill">Imposta password</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
