<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

const STATUS_PENDING = 'In attesa';
const STATUS_OK = 'OK';
const STATUS_KO = 'KO';
const LOGIN_RATE_LIMIT_MAX = 5; // max failed attempts per window
const LOGIN_RATE_LIMIT_WINDOW = 600; // 10 minutes
const OP_CODE_PREFIX = 'OP';

function seed_data(): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    // No auto-seed: gestori e offerte vanno inseriti dall'admin reale.
    $bootstrapped = true;
}

function get_users(): array
{
    seed_data();
    return db()->query('SELECT id, role, name, email, password FROM users ORDER BY id')->fetchAll();
}

function get_installers(): array
{
    seed_data();
    return db()->query('SELECT id, name, email, password_reset_token, password_reset_expires, created_at FROM users WHERE role = "installer" ORDER BY id DESC')->fetchAll();
}

function get_segnalatori(): array
{
    seed_data();
    return db()->query('SELECT id, name, email, password_reset_token, password_reset_expires, created_at FROM users WHERE role = "segnalatore" ORDER BY id DESC')->fetchAll();
}

function get_admins(): array
{
    seed_data();
    return db()->query('SELECT id, name, email FROM users WHERE role = "admin" ORDER BY id')->fetchAll();
}

function find_user_by_email(string $email): ?array
{
    seed_data();
    $stmt = db()->prepare('SELECT id, role, name, email, password FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function get_offers(): array
{
    seed_data();
    $sql = 'SELECT o.id, o.name, o.description, o.commission, o.manager_id, g.name AS manager_name
            FROM offers o
            JOIN gestori g ON o.manager_id = g.id
            ORDER BY o.id';
    return db()->query($sql)->fetchAll();
}

function create_installer(string $name, string $email, ?string $password = null, bool $forceReset = false): array
{
    seed_data();
    $pdo = db();

    $stmtCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmtCheck->execute(['email' => $email]);
    if ($stmtCheck->fetch()) {
        throw new InvalidArgumentException('Email già esistente');
    }

    $hash = $password && !$forceReset ? password_hash($password, PASSWORD_DEFAULT) : null;
    $stmt = $pdo->prepare('INSERT INTO users (role, name, email, password) VALUES (:role, :name, :email, :password)');
    $stmt->execute([
        'role' => 'installer',
        'name' => $name,
        'email' => $email,
        'password' => $hash,
    ]);
    $id = (int)$pdo->lastInsertId();

    $token = null;
    if ($forceReset || !$hash) {
        $token = generate_password_reset($id, $pdo);
    }

    return ['id' => $id, 'reset_token' => $token];
}

function create_segnalatore(string $name, string $email, ?string $password = null, bool $forceReset = false): array
{
    seed_data();
    $pdo = db();

    $stmtCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmtCheck->execute(['email' => $email]);
    if ($stmtCheck->fetch()) {
        throw new InvalidArgumentException('Email già esistente');
    }

    $hash = $password && !$forceReset ? password_hash($password, PASSWORD_DEFAULT) : null;
    $stmt = $pdo->prepare('INSERT INTO users (role, name, email, password) VALUES (:role, :name, :email, :password)');
    $stmt->execute([
        'role' => 'segnalatore',
        'name' => $name,
        'email' => $email,
        'password' => $hash,
    ]);
    $id = (int)$pdo->lastInsertId();

    $token = null;
    if ($forceReset || !$hash) {
        $token = generate_password_reset($id, $pdo);
    }

    return ['id' => $id, 'reset_token' => $token];
}

function delete_installer(int $id): void
{
    seed_data();
    $pdo = db();

    // prevent deleting admins or self
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new InvalidArgumentException('Installer non trovato');
    }
    if ($user['role'] !== 'installer') {
        throw new InvalidArgumentException('Non puoi cancellare questo utente');
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM opportunities WHERE installer_id = :id');
    $countStmt->execute(['id' => $id]);
    if ((int)$countStmt->fetchColumn() > 0) {
        throw new RuntimeException('Impossibile cancellare: ci sono opportunity collegate');
    }

    $del = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $del->execute(['id' => $id]);
}

function resend_installer_invite(int $id): string
{
    seed_data();
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();
    if (!$user || $user['role'] !== 'installer') {
        throw new InvalidArgumentException('Installer non trovato');
    }
    $token = generate_password_reset((int)$user['id'], $pdo);
    notify_installer_credentials($user['name'], $user['email'], $token);
    return $token;
}

function resend_segnalatore_invite(int $id): string
{
    seed_data();
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();
    if (!$user || $user['role'] !== 'segnalatore') {
        throw new InvalidArgumentException('Segnalatore non trovato');
    }
    $token = generate_password_reset((int)$user['id'], $pdo);
    notify_segnalatore_credentials($user['name'], $user['email'], $token);
    return $token;
}

function generate_password_reset(int $userId, ?PDO $pdo = null, int $ttlMinutes = 1440): string
{
    $pdo = $pdo ?: db();
    $token = bin2hex(random_bytes(32));
    $expires = (new DateTimeImmutable())->modify("+{$ttlMinutes} minutes")->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare('UPDATE users SET password_reset_token = :t, password_reset_expires = :e WHERE id = :id');
    $stmt->execute(['t' => $token, 'e' => $expires, 'id' => $userId]);
    return $token;
}

function find_user_by_reset_token(string $token): ?array
{
    seed_data();
    $stmt = db()->prepare('SELECT id, role, name, email, password_reset_expires FROM users WHERE password_reset_token = :token LIMIT 1');
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }
    if (empty($user['password_reset_expires']) || new DateTimeImmutable($user['password_reset_expires']) < new DateTimeImmutable()) {
        return null;
    }
    return $user;
}

function set_user_password_with_token(string $token, string $password): void
{
    seed_data();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, password_reset_expires FROM users WHERE password_reset_token = :t LIMIT 1 FOR UPDATE');
        $stmt->execute(['t' => $token]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new InvalidArgumentException('Token non valido');
        }
        if (empty($user['password_reset_expires']) || new DateTimeImmutable($user['password_reset_expires']) < new DateTimeImmutable()) {
            throw new InvalidArgumentException('Token scaduto');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE users SET password = :p, password_reset_token = NULL, password_reset_expires = NULL WHERE id = :id');
        $upd->execute(['p' => $hash, 'id' => $user['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function get_gestori(): array
{
    seed_data();
    return db()->query('SELECT id, name, active FROM gestori ORDER BY id')->fetchAll();
}

function get_opportunities(array $filters = []): array
{
    seed_data();
    $sql = 'SELECT o.id, o.opportunity_code, o.first_name, o.last_name, o.notes, o.status, o.installer_id, o.created_by, o.phone, o.address, o.city, o.month, o.created_at, o.commission,
                   off.id AS product_type, off.name AS offer_name, g.name AS manager_name,
                   u.name AS installer_name,
                   cu.name AS segnalatore_name
            FROM opportunities o
            LEFT JOIN offers off ON o.offer_id = off.id
            LEFT JOIN gestori g ON o.manager_id = g.id
            LEFT JOIN users u ON o.installer_id = u.id
            LEFT JOIN users cu ON o.created_by = cu.id
            WHERE 1=1';
    $params = [];

    if (isset($filters['installer_id']) && $filters['installer_id'] !== '') {
        $sql .= ' AND o.installer_id = :installer_id';
        $params['installer_id'] = (int)$filters['installer_id'];
    }
    if (isset($filters['status']) && $filters['status'] !== '') {
        $sql .= ' AND o.status = :status';
        $params['status'] = $filters['status'];
    }
    if (isset($filters['month']) && $filters['month'] !== '') {
        $sql .= ' AND o.month = :month';
        $params['month'] = (int)$filters['month'];
    }
    if (isset($filters['manager']) && $filters['manager'] !== '') {
        $sql .= ' AND g.name = :manager';
        $params['manager'] = $filters['manager'];
    }
    if (isset($filters['created_by']) && $filters['created_by'] !== '') {
        $sql .= ' AND o.created_by = :created_by';
        $params['created_by'] = (int)$filters['created_by'];
    }
    if (isset($filters['origin']) && $filters['origin'] !== '') {
        if ($filters['origin'] === 'segnalatore') {
            $sql .= ' AND o.notes LIKE :origin_notes';
            $params['origin_notes'] = 'Segnalazione%';
        } elseif ($filters['origin'] === 'admin_installer') {
            $sql .= ' AND (o.notes NOT LIKE :origin_notes OR o.notes IS NULL)';
            $params['origin_notes'] = 'Segnalazione%';
        }
    }

    if (isset($filters['exclude_urgent']) && $filters['exclude_urgent']) {
        $sql .= ' AND o.offer_id > 0';
    }

    $sql .= ' ORDER BY o.created_at DESC, o.id DESC';

    $limit = isset($filters['limit']) ? (int)$filters['limit'] : null;
    $offset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;
    if ($limit !== null && $limit > 0) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($limit !== null && $limit > 0) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }

    $stmt->execute();
    return $stmt->fetchAll();
}

function count_opportunities(array $filters = []): int
{
    seed_data();
    $sql = 'SELECT COUNT(*)
            FROM opportunities o
            JOIN offers off ON o.offer_id = off.id
            JOIN gestori g ON o.manager_id = g.id
            LEFT JOIN users u ON o.installer_id = u.id
            WHERE 1=1';
    $params = [];

    if (isset($filters['installer_id']) && $filters['installer_id'] !== '') {
        $sql .= ' AND o.installer_id = :installer_id';
        $params['installer_id'] = (int)$filters['installer_id'];
    }
    if (isset($filters['status']) && $filters['status'] !== '') {
        $sql .= ' AND o.status = :status';
        $params['status'] = $filters['status'];
    }
    if (isset($filters['month']) && $filters['month'] !== '') {
        $sql .= ' AND o.month = :month';
        $params['month'] = (int)$filters['month'];
    }
    if (isset($filters['manager']) && $filters['manager'] !== '') {
        $sql .= ' AND g.name = :manager';
        $params['manager'] = $filters['manager'];
    }
    if (isset($filters['created_by']) && $filters['created_by'] !== '') {
        $sql .= ' AND o.created_by = :created_by';
        $params['created_by'] = (int)$filters['created_by'];
    }
    if (isset($filters['origin']) && $filters['origin'] !== '') {
        if ($filters['origin'] === 'segnalatore') {
            $sql .= ' AND o.notes LIKE :origin_notes';
            $params['origin_notes'] = 'Segnalazione%';
        } elseif ($filters['origin'] === 'admin_installer') {
            $sql .= ' AND (o.notes NOT LIKE :origin_notes OR o.notes IS NULL)';
            $params['origin_notes'] = 'Segnalazione%';
        }
    }

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function add_opportunity(array $data): array
{
    seed_data();
    $pdo = db();

    $offerId = (int)($data['offer_id'] ?? 0);
    $installerId = isset($data['installer_id']) ? (int)$data['installer_id'] : null;
    $createdBy = isset($data['created_by']) ? (int)$data['created_by'] : null;
    $offer = null;
    if ($offerId > 0) {
        $offerStmt = $pdo->prepare('SELECT o.id, o.name, o.commission, o.manager_id, g.name AS manager_name
                                    FROM offers o
                                    JOIN gestori g ON o.manager_id = g.id
                                    WHERE o.id = :id');
        $offerStmt->execute(['id' => $offerId]);
        $offer = $offerStmt->fetch();
        if (!$offer) {
            throw new InvalidArgumentException('Offerta non valida');
        }
    } else {
        // For urgent or default
        $offer = [
            'id' => 0,
            'name' => 'Fibra 100',
            'commission' => 35.00,
            'manager_id' => (int)($data['manager_id'] ?? 0),
            'manager_name' => 'Default'
        ];
    }

    $now = new DateTimeImmutable();
    $code = generate_opportunity_code($pdo);
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->prepare('INSERT INTO opportunities (opportunity_code, first_name, last_name, notes, offer_id, manager_id, commission, phone, address, city, status, installer_id, created_by, month, created_at)
                   VALUES (:opportunity_code, :first_name, :last_name, :notes, :offer_id, :manager_id, :commission, :phone, :address, :city, :status, :installer_id, :created_by, :month, :created_at)')
        ->execute([
            'opportunity_code' => $code,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'notes' => $data['notes'] ?? '',
            'offer_id' => $offerId,
            'manager_id' => $offer['manager_id'],
            'commission' => $data['commission'] ?? $offer['commission'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'status' => STATUS_PENDING,
            'installer_id' => $installerId,
            'created_by' => $createdBy,
            'month' => (int)$now->format('m'),
            'created_at' => $now->format('Y-m-d'),
        ]);
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $id = (int)$pdo->lastInsertId();
    return [
        'id' => $id,
        'opportunity_code' => $code,
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'notes' => $data['notes'] ?? '',
        'offer_name' => $offer['name'],
        'manager_name' => $offer['manager_name'],
        'commission' => $data['commission'] ?? $offer['commission'],
        'status' => STATUS_PENDING,
        'installer_id' => $installerId,
        'installer_name' => $data['installer_name'] ?? '',
        'month' => (int)$now->format('m'),
        'created_at' => $now->format('Y-m-d'),
    ];

}

function get_opportunity(int $id): ?array
{
    $stmt = db()->prepare('SELECT o.*, off.name AS offer_name, g.name AS manager_name, u.name AS installer_name
                           FROM opportunities o
                           JOIN offers off ON o.offer_id = off.id
                           JOIN gestori g ON o.manager_id = g.id
                           JOIN users u ON o.installer_id = u.id
                           WHERE o.id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function update_opportunity_status(int $id, string $status, int $changedBy): array
{
    seed_data();
    if (!in_array($status, [STATUS_PENDING, STATUS_OK, STATUS_KO], true)) {
        throw new InvalidArgumentException('Stato non valido');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status, installer_id FROM opportunities WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Opportunity non trovata');
        }

        $oldStatus = $row['status'];
        $installerId = (int)$row['installer_id'];
        if ($oldStatus === $status) {
            $pdo->commit();
            return ['changed' => false, 'installer_id' => $installerId];
        }

        $update = $pdo->prepare('UPDATE opportunities SET status = :status WHERE id = :id');
        $update->execute(['status' => $status, 'id' => $id]);

        log_opportunity_status_change($id, $oldStatus, $status, $changedBy, $pdo);
        $pdo->commit();

        // Return change info for downstream notifications
        return ['changed' => true, 'installer_id' => $installerId];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function upsert_gestore(array $gestore): void
{
    seed_data();
    if (!empty($gestore['id'])) {
        $stmt = db()->prepare('UPDATE gestori SET name = :name, active = :active WHERE id = :id');
        $stmt->execute([
            'name' => $gestore['name'],
            'active' => !empty($gestore['active']) ? 1 : 0,
            'id' => (int)$gestore['id'],
        ]);
    } else {
        $stmt = db()->prepare('INSERT INTO gestori (name, active) VALUES (:name, :active)');
        $stmt->execute([
            'name' => $gestore['name'],
            'active' => !empty($gestore['active']) ? 1 : 0,
        ]);
    }
}

function upsert_offer(array $offer): void
{
    seed_data();
    if (!empty($offer['id'])) {
        $stmt = db()->prepare('UPDATE offers SET name = :name, description = :description, commission = :commission, manager_id = :manager_id WHERE id = :id');
        $stmt->execute([
            'name' => $offer['name'],
            'description' => $offer['description'] ?? null,
            'commission' => $offer['commission'],
            'manager_id' => $offer['manager_id'],
            'id' => (int)$offer['id'],
        ]);
    } else {
        $stmt = db()->prepare('INSERT INTO offers (name, description, commission, manager_id) VALUES (:name, :description, :commission, :manager_id)');
        $stmt->execute([
            'name' => $offer['name'],
            'description' => $offer['description'] ?? null,
            'commission' => $offer['commission'],
            'manager_id' => $offer['manager_id'],
        ]);
    }
}

function log_opportunity_status_change(int $opportunityId, string $oldStatus, string $newStatus, int $changedBy, ?PDO $pdo = null): void
{
    $pdo = $pdo ?: db();
    $stmt = $pdo->prepare('INSERT INTO opportunity_audit (opportunity_id, old_status, new_status, changed_by, changed_at)
                           VALUES (:opportunity_id, :old_status, :new_status, :changed_by, NOW())');
    $stmt->execute([
        'opportunity_id' => $opportunityId,
        'old_status' => $oldStatus,
        'new_status' => $newStatus,
        'changed_by' => $changedBy,
    ]);
}

function generate_opportunity_code(PDO $pdo): string
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM opportunities WHERE opportunity_code = :code');
    do {
        $code = OP_CODE_PREFIX . date('Ymd') . random_int(100000, 999999);
        $stmt->execute(['code' => $code]);
    } while ((int)$stmt->fetchColumn() > 0);
    return $code;
}

function record_login_attempt(string $email, string $ip, bool $success): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO login_attempts (email, ip, success, attempted_at) VALUES (:email, :ip, :success, NOW())');
    $stmt->execute([
        'email' => $email,
        'ip' => $ip,
        'success' => $success ? 1 : 0,
    ]);

    // Trim old attempts to keep table small
    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)');
}

function is_login_rate_limited(string $email, string $ip, int $maxAttempts = LOGIN_RATE_LIMIT_MAX, int $windowSeconds = LOGIN_RATE_LIMIT_WINDOW): bool
{
    $pdo = db();
    $windowSeconds = max(1, $windowSeconds);
    $sql = 'SELECT COUNT(*) FROM login_attempts
            WHERE email = :email AND ip = :ip AND success = 0
            AND attempted_at >= (NOW() - INTERVAL ' . (int)$windowSeconds . ' SECOND)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'email' => $email,
        'ip' => $ip,
    ]);
    $failures = (int)$stmt->fetchColumn();
    return $failures >= $maxAttempts;
}

function save_push_subscription(int $userId, string $endpoint, string $p256dh, string $auth, ?string $token = null, ?string $platform = null): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, token, platform) VALUES (:user_id, :endpoint, :p256dh, :auth, :token, :platform)
        ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), token = VALUES(token), platform = VALUES(platform), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([
        'user_id' => $userId,
        'endpoint' => $endpoint ?: null,
        'p256dh' => $p256dh ?: null,
        'auth' => $auth ?: null,
        'token' => $token,
        'platform' => $platform,
    ]);
}

function get_push_subscriptions(int $userId): array
{
    $stmt = db()->prepare('SELECT endpoint, p256dh, auth, token, platform FROM push_subscriptions WHERE user_id = :uid');
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

function get_admin_push_subscriptions(): array
{
    $sql = 'SELECT ps.endpoint, ps.p256dh, ps.auth, ps.token, ps.platform
            FROM push_subscriptions ps
            JOIN users u ON ps.user_id = u.id
            WHERE u.role = "admin"';
    return db()->query($sql)->fetchAll();
}

function ensure_upload_dir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function normalize_filename(string $name): string
{
    $basename = preg_replace('/[^a-zA-Z0-9._-]/', '_', strtolower($name)) ?: 'file';
    return $basename;
}

function validate_document_upload(array $file): void
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Upload non riuscito');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('File troppo grande (max 5MB)');
    }

    $tmp = $file['tmp_name'] ?? '';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    if (!in_array($mime, $allowed, true)) {
        throw new InvalidArgumentException('Formato non ammesso');
    }
}

function scan_document(string $path): void
{
    $cmd = getenv('CLAMSCAN_CMD');
    if (!$cmd) {
        return;
    }

    $binary = escapeshellcmd($cmd);
    $target = escapeshellarg($path);
    $output = [];
    $code = 0;
    @exec($binary . ' ' . $target, $output, $code);
    if ($code !== 0) {
        @unlink($path);
        error_log('Documento rifiutato per potenziale malware: ' . $path);
        // Non throw, per permettere il salvataggio
    }
}

function create_segnalazione(array $data, array $files, int $userId): int
{
    seed_data();
    $pdo = db();

    // rate limit 5 per ora
    $rateStmt = $pdo->prepare('SELECT COUNT(*) FROM segnalazioni WHERE created_by = :uid AND created_at >= (NOW() - INTERVAL 1 HOUR)');
    $rateStmt->execute(['uid' => $userId]);
    if ((int)$rateStmt->fetchColumn() >= 5) {
        throw new RuntimeException('Hai raggiunto il limite di invii, riprova più tardi');
    }

    $offerId = (int)($data['offer_id'] ?? 0);
    $offerStmt = $pdo->prepare('SELECT o.id, o.manager_id FROM offers o WHERE o.id = :id');
    $offerStmt->execute(['id' => $offerId]);
    $offer = $offerStmt->fetch();
    if (!$offer) {
        throw new InvalidArgumentException('Offerta non valida');
    }

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare('INSERT INTO segnalazioni (first_name, last_name, offer_id, manager_id, status, created_by) VALUES (:first, :last, :offer, :manager, "In attesa", :uid)');
        $insert->execute([
            'first' => $data['first_name'],
            'last' => $data['last_name'],
            'offer' => $offerId,
            'manager' => $offer['manager_id'],
            'uid' => $userId,
        ]);
        $segId = (int)$pdo->lastInsertId();

        // handle documents
        try {
            if (!empty($files['docs']) && is_array($files['docs']['name'])) {
                $count = count($files['docs']['name']);
                $docStmt = $pdo->prepare('INSERT INTO segnalazione_docs (segnalazione_id, path, original_name, mime, size) VALUES (:sid, :path, :orig, :mime, :size)');
                $baseDir = rtrim(UPLOAD_DIR, '/');
                $targetDir = $baseDir . '/segnalazioni/' . $segId;
                ensure_upload_dir($targetDir);

                for ($i = 0; $i < $count; $i++) {
                    $file = [
                        'name' => $files['docs']['name'][$i] ?? '',
                        'type' => $files['docs']['type'][$i] ?? '',
                        'tmp_name' => $files['docs']['tmp_name'][$i] ?? '',
                        'error' => $files['docs']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $files['docs']['size'][$i] ?? 0,
                    ];
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    try {
                        validate_document_upload($file);
                    } catch (Throwable $e) {
                        error_log('Validazione documento fallita per segnalazione ' . $segId . ': ' . $e->getMessage());
                        continue; // Salta questo file
                    }
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $safeName = normalize_filename(pathinfo($file['name'], PATHINFO_FILENAME));
                    $destName = $safeName . '-' . uniqid() . ($ext ? ('.' . $ext) : '');
                    $destPath = $targetDir . '/' . $destName;
                    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                        error_log('Salvataggio documento fallito per segnalazione ' . $segId . ': ' . $file['name']);
                        continue; // Salta questo file
                    }
                    scan_document($destPath);
                    $mime = mime_content_type($destPath) ?: ($file['type'] ?? '');
                    $docStmt->execute([
                        'sid' => $segId,
                        'path' => str_replace(rtrim(__DIR__ . '/..', '/'), '', $destPath),
                        'orig' => $file['name'],
                        'mime' => $mime,
                        'size' => (int)$file['size'],
                    ]);
                }
            }
        } catch (Throwable $e) {
            // Log the error but don't fail the segnalazione
            error_log('Errore gestione documenti segnalazione ' . $segId . ': ' . $e->getMessage());
            // Optionally, add a notification to the user
        }

        $pdo->commit();
        return $segId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function get_segnalazione_doc(int $docId): ?array
{
    $sql = 'SELECT d.id, d.path, d.original_name, d.mime, d.size,
                   s.id AS segnalazione_id, s.created_by, s.opportunity_id,
                   o.installer_id AS opportunity_installer_id
            FROM segnalazione_docs d
            JOIN segnalazioni s ON d.segnalazione_id = s.id
            LEFT JOIN opportunities o ON s.opportunity_id = o.id
            WHERE d.id = :id';
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $docId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function count_segnalazioni(array $filters = []): int
{
    seed_data();
    $sql = 'SELECT COUNT(*) FROM segnalazioni s WHERE 1=1';
    $params = [];

    if (!empty($filters['status'])) {
        $sql .= ' AND s.status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['created_by'])) {
        $sql .= ' AND s.created_by = :created_by';
        $params['created_by'] = (int)$filters['created_by'];
    }
    if (!empty($filters['search'])) {
        $sql .= ' AND (s.first_name LIKE :search OR s.last_name LIKE :search)';
        $params['search'] = '%' . $filters['search'] . '%';
    }

    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function list_segnalazioni(array $filters = []): array
{
    seed_data();
    $sql = 'SELECT s.id, s.first_name, s.last_name, s.offer_id, s.manager_id, s.status, s.created_by, s.created_at, s.opportunity_id,
                   o.name AS offer_name, o.commission, g.name AS manager_name, u.name AS creator_name,
                   (SELECT COUNT(*) FROM segnalazione_docs d WHERE d.segnalazione_id = s.id) AS doc_count
            FROM segnalazioni s
            JOIN offers o ON s.offer_id = o.id
            LEFT JOIN gestori g ON s.manager_id = g.id
            LEFT JOIN users u ON s.created_by = u.id
            WHERE 1=1';
    $params = [];

    if (!empty($filters['status'])) {
        $sql .= ' AND s.status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['created_by'])) {
        $sql .= ' AND s.created_by = :created_by';
        $params['created_by'] = (int)$filters['created_by'];
    }
    if (!empty($filters['search'])) {
        $sql .= ' AND (s.first_name LIKE :search OR s.last_name LIKE :search)';
        $params['search'] = '%' . $filters['search'] . '%';
    }

    $sql .= ' ORDER BY s.created_at DESC, s.id DESC';

    if (!empty($filters['limit'])) {
        $sql .= ' LIMIT :limit';
        $params['limit'] = (int)$filters['limit'];
        if (!empty($filters['offset'])) {
            $sql .= ' OFFSET :offset';
            $params['offset'] = (int)$filters['offset'];
        }
    }

    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
        if ($k === 'limit' || $k === 'offset') $type = PDO::PARAM_INT;
        $stmt->bindValue(':' . $k, $v, $type);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_segnalazione(int $id): ?array
{
    $stmt = db()->prepare('SELECT s.*, o.name AS offer_name, g.name AS manager_name, u.name AS creator_name, u.email AS creator_email
                           FROM segnalazioni s
                           JOIN offers o ON s.offer_id = o.id
                           JOIN gestori g ON s.manager_id = g.id
                           JOIN users u ON s.created_by = u.id
                           WHERE s.id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_segnalazione_docs(int $segId): array
{
    $stmt = db()->prepare('SELECT id, path, original_name, mime, size FROM segnalazione_docs WHERE segnalazione_id = :sid');
    $stmt->execute(['sid' => $segId]);
    return $stmt->fetchAll();
}

function update_segnalazione_status(int $id, string $status, int $reviewerId, ?int $installerId = null): ?int
{
    if (!in_array($status, ['Accettata', 'Rifiutata'], true)) {
        throw new InvalidArgumentException('Stato non valido');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $seg = get_segnalazione($id);
        if (!$seg || $seg['status'] !== 'In attesa') {
            $pdo->rollBack();
            return null;
        }

        $opportunityId = null;
        if ($status === 'Accettata') {
            $created = add_opportunity([
                'first_name' => $seg['first_name'],
                'last_name' => $seg['last_name'],
                'notes' => 'Segnalazione #' . $seg['id'],
                'offer_id' => (int)$seg['offer_id'],
                'installer_id' => $installerId,
                'installer_name' => '',
                'commission' => 0, // No commission for segnalazioni
            ]);
            $opportunityId = $created['id'];
        }

        $stmt = $pdo->prepare('UPDATE segnalazioni SET status = :status, reviewed_by = :rev, reviewed_at = NOW(), opportunity_id = :opp WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'rev' => $reviewerId,
            'opp' => $opportunityId,
            'id' => $id,
        ]);

        $pdo->commit();
        return $opportunityId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function create_notification(int $userId, string $title, string $body, string $type = 'info'): int
{
    $type = in_array($type, ['info', 'success', 'error'], true) ? $type : 'info';
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, body, type) VALUES (:user_id, :title, :body, :type)');
    $stmt->execute([
        'user_id' => $userId,
        'title' => $title,
        'body' => $body,
        'type' => $type,
    ]);
    return (int)$pdo->lastInsertId();
}

function cleanup_old_segnalazioni_uploads(int $days = 30): int
{
    $days = max(1, $days);
    $cutoff = (new DateTimeImmutable('-' . $days . ' days'))->format('Y-m-d H:i:s');
    $pdo = db();

    $sql = 'SELECT d.id, d.path
            FROM segnalazione_docs d
            JOIN segnalazioni s ON d.segnalazione_id = s.id
            WHERE s.status = "Rifiutata" AND s.reviewed_at <= :cutoff';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cutoff' => $cutoff]);
    $docs = $stmt->fetchAll();

    $removed = 0;
    $base = realpath(UPLOAD_DIR) ?: null;
    foreach ($docs as $doc) {
        $fullPath = $doc['path'] ?? '';
        $absolute = $fullPath ? realpath(__DIR__ . '/..' . $fullPath) : null;
        if ($absolute && $base && strpos($absolute, $base) === 0 && file_exists($absolute)) {
            @unlink($absolute);
            $removed++;
            // prova a rimuovere la cartella se vuota
            $dir = dirname($absolute);
            if (is_dir($dir) && count(array_diff(scandir($dir), ['.', '..'])) === 0) {
                @rmdir($dir);
            }
        }
        $pdo->prepare('DELETE FROM segnalazione_docs WHERE id = :id')->execute(['id' => $doc['id']]);
    }

    return $removed;
}

function list_notifications(int $userId, int $limit = 50, int $offset = 0): array
{
    $limit = max(1, min($limit, 100));
    $offset = max(0, $offset);
    $stmt = db()->prepare('SELECT id, title, body, type, read_at, created_at
                           FROM notifications
                           WHERE user_id = :uid
                           ORDER BY created_at DESC, id DESC
                           LIMIT :lim OFFSET :off');
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function mark_notifications_read(int $userId): void
{
    $stmt = db()->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = :uid AND read_at IS NULL');
    $stmt->execute(['uid' => $userId]);
}

function clear_notifications(int $userId): void
{
    $stmt = db()->prepare('DELETE FROM notifications WHERE user_id = :uid');
    $stmt->execute(['uid' => $userId]);
}

function count_unread_notifications(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND read_at IS NULL');
    $stmt->execute(['uid' => $userId]);
    return (int)$stmt->fetchColumn();
}
