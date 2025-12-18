<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';

$message = null;
$error = null;
$installers = get_installers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sessione scaduta, ricarica la pagina.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'create') {
                $name = sanitize($_POST['name'] ?? '');
                $emailRaw = trim($_POST['email'] ?? '');
                if (!$name || strlen($name) > 120) {
                    throw new InvalidArgumentException('Nome non valido');
                }
                if (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Email non valida');
                }
                $created = create_installer($name, $emailRaw, null, true);
                if (!empty($created['reset_token'])) {
                    notify_installer_credentials($name, $emailRaw, $created['reset_token']);
                    $message = 'Invito inviato all\'installer';
                } else {
                    $message = 'Installer creato';
                }
            } elseif ($action === 'resend') {
                $id = (int)($_POST['id'] ?? 0);
                resend_installer_invite($id);
                $message = 'Invito re-inviato';
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                delete_installer($id);
                $message = 'Installer cancellato';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        $installers = get_installers();
    }
}

$pageTitle = 'Installer';
$bottomNav = '
    <a class="nav-pill" href="/admin/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/admin/opportunities.php"><span class="dot"></span><span>Opportunity</span></a>
    <a class="nav-pill" href="/admin/report.php"><span class="dot"></span><span>Report</span></a>
    <a class="nav-pill" href="/admin/settings.php"><span class="dot"></span><span>Impostazioni</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Admin</div>
        <h1 class="h5 fw-bold mb-0">Installer</h1>
    </div>
</div>

<?php if ($message): ?>
    <div data-flash data-type="success" data-title="OK" data-flash="<?php echo sanitize($message); ?>"></div>
<?php endif; ?>
<?php if ($error): ?>
    <div data-flash data-type="error" data-title="Errore" data-flash="<?php echo sanitize($error); ?>"></div>
<?php endif; ?>

<div class="card-soft p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <div class="bite">Crea invito</div>
            <h2 class="h6 fw-bold mb-0">Nuovo installer</h2>
        </div>
    </div>
    <form method="post" class="row g-2 align-items-center">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <div class="col-12 col-md-5">
            <input type="text" class="form-control" name="name" placeholder="Nome completo" required>
        </div>
        <div class="col-12 col-md-5">
            <input type="email" class="form-control" name="email" placeholder="Email aziendale" required>
        </div>
        <div class="col-12 col-md-2">
            <button class="btn btn-primary w-100 btn-sm">Invia invito</button>
        </div>
    </form>
</div>

<div class="card-soft p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <div class="bite">Lista</div>
            <h2 class="h6 fw-bold mb-0">Installer registrati</h2>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="text-muted small">
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Creato</th>
                    <th class="text-end">Azioni</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($installers as $u): ?>
                <tr>
                    <td class="fw-semibold"><?php echo sanitize($u['name']); ?></td>
                    <td class="text-muted small"><?php echo sanitize($u['email']); ?></td>
                    <td class="text-muted small"><?php echo sanitize($u['created_at']); ?></td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end align-items-center gap-2 flex-nowrap">
                            <form method="post" class="mb-0">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="resend">
                                <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                <button class="btn btn-outline-primary btn-sm d-inline-flex align-items-center justify-content-center" title="Re-invia" aria-label="Re-invia">
                                    <i class="bi bi-arrow-repeat"></i>
                                    <span class="visually-hidden">Re-invia</span>
                                </button>
                            </form>
                            <form method="post" class="mb-0" onsubmit="return confirm('Cancellare questo installer?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                <button class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" title="Elimina" aria-label="Elimina">
                                    <i class="bi bi-trash"></i>
                                    <span class="visually-hidden">Elimina</span>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$installers): ?>
                <tr><td colspan="4" class="text-muted small">Nessun installer presente.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
