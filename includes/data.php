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
    $sql = 'SELECT o.id, o.opportunity_code, o.first_name, o.last_name, o.notes, o.status, o.installer_id, o.month, o.created_at, o.commission,
                   off.id AS product_type, off.name AS offer_name, g.name AS manager_name,
                   u.name AS installer_name
            FROM opportunities o
            JOIN offers off ON o.offer_id = off.id
            JOIN gestori g ON o.manager_id = g.id
            JOIN users u ON o.installer_id = u.id
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

    $sql .= ' ORDER BY o.created_at DESC, o.id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function add_opportunity(array $data): array
{
    seed_data();
    $pdo = db();

    $offerId = (int)($data['offer_id'] ?? 0);
    $installerId = (int)($data['installer_id'] ?? 0);
    $offerStmt = $pdo->prepare('SELECT o.id, o.name, o.commission, o.manager_id, g.name AS manager_name
                                FROM offers o
                                JOIN gestori g ON o.manager_id = g.id
                                WHERE o.id = :id');
    $offerStmt->execute(['id' => $offerId]);
    $offer = $offerStmt->fetch();
    if (!$offer) {
        throw new InvalidArgumentException('Offerta non valida');
    }

    $now = new DateTimeImmutable();
    $code = generate_opportunity_code($pdo);
    $pdo->prepare('INSERT INTO opportunities (opportunity_code, first_name, last_name, notes, offer_id, manager_id, commission, status, installer_id, month, created_at)
                   VALUES (:opportunity_code, :first_name, :last_name, :notes, :offer_id, :manager_id, :commission, :status, :installer_id, :month, :created_at)')
        ->execute([
            'opportunity_code' => $code,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'notes' => $data['notes'] ?? '',
            'offer_id' => $offerId,
            'manager_id' => $offer['manager_id'],
            'commission' => $offer['commission'],
            'status' => STATUS_PENDING,
            'installer_id' => $installerId,
            'month' => (int)$now->format('m'),
            'created_at' => $now->format('Y-m-d'),
        ]);

    $id = (int)$pdo->lastInsertId();
    return [
        'id' => $id,
        'opportunity_code' => $code,
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'notes' => $data['notes'] ?? '',
        'offer_name' => $offer['name'],
        'manager_name' => $offer['manager_name'],
        'commission' => $offer['commission'],
        'status' => STATUS_PENDING,
        'installer_id' => $installerId,
        'installer_name' => $data['installer_name'] ?? '',
        'month' => (int)$now->format('m'),
        'created_at' => $now->format('Y-m-d'),
    ];
}

function update_opportunity_status(int $id, string $status, int $changedBy): void
{
    seed_data();
    if (!in_array($status, [STATUS_PENDING, STATUS_OK, STATUS_KO], true)) {
        throw new InvalidArgumentException('Stato non valido');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status FROM opportunities WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Opportunity non trovata');
        }

        $oldStatus = $row['status'];
        if ($oldStatus === $status) {
            $pdo->commit();
            return;
        }

        $update = $pdo->prepare('UPDATE opportunities SET status = :status WHERE id = :id');
        $update->execute(['status' => $status, 'id' => $id]);

        log_opportunity_status_change($id, $oldStatus, $status, $changedBy, $pdo);
        $pdo->commit();
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
