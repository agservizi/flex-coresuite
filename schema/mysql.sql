-- MySQL schema for Flex (Coresuite)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin','installer','segnalatore') NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password VARCHAR(255) NULL,
    password_reset_token VARCHAR(255) NULL,
    password_reset_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_reset_token (password_reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gestori (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    commission DECIMAL(10,2) NOT NULL DEFAULT 0,
    manager_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_offers_manager FOREIGN KEY (manager_id) REFERENCES gestori(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_code VARCHAR(32) NOT NULL UNIQUE,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    notes TEXT NULL,
    phone VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    offer_id INT UNSIGNED NULL,
    manager_id INT UNSIGNED NOT NULL,
    commission DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('In attesa','OK','KO') NOT NULL DEFAULT 'In attesa',
    installer_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    month TINYINT UNSIGNED NOT NULL,
    created_at DATE NOT NULL,
    CONSTRAINT fk_opportunities_offer FOREIGN KEY (offer_id) REFERENCES offers(id),
    CONSTRAINT fk_opportunities_manager FOREIGN KEY (manager_id) REFERENCES gestori(id),
    CONSTRAINT fk_opportunities_installer FOREIGN KEY (installer_id) REFERENCES users(id),
    CONSTRAINT fk_opportunities_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS segnalazioni (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    offer_id INT UNSIGNED NOT NULL,
    manager_id INT UNSIGNED NOT NULL,
    status ENUM('In attesa','Accettata','Rifiutata') NOT NULL DEFAULT 'In attesa',
    created_by INT UNSIGNED NOT NULL,
    opportunity_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    CONSTRAINT fk_segnalazioni_offer FOREIGN KEY (offer_id) REFERENCES offers(id),
    CONSTRAINT fk_segnalazioni_manager FOREIGN KEY (manager_id) REFERENCES gestori(id),
    CONSTRAINT fk_segnalazioni_user FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_segnalazioni_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id),
    INDEX idx_segnalazioni_status_time (status, created_at),
    INDEX idx_segnalazioni_creator_time (created_by, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS segnalazione_docs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    segnalazione_id INT UNSIGNED NOT NULL,
    path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_segnalazione_docs FOREIGN KEY (segnalazione_id) REFERENCES segnalazioni(id) ON DELETE CASCADE,
    INDEX idx_segnalazione_docs_seg (segnalazione_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(180) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempt_email_ip_time (email, ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunity_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT UNSIGNED NOT NULL,
    old_status ENUM('In attesa','OK','KO') NOT NULL,
    new_status ENUM('In attesa','OK','KO') NOT NULL,
    changed_by INT UNSIGNED NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id),
    CONSTRAINT fk_audit_user FOREIGN KEY (changed_by) REFERENCES users(id),
    INDEX idx_audit_opportunity_time (opportunity_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY idx_push_endpoint (endpoint),
    INDEX idx_push_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    type ENUM('info','success','error') NOT NULL DEFAULT 'info',
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_notifications_user_time (user_id, created_at),
    INDEX idx_notifications_user_unread (user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunity_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id),
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_comments_opportunity_time (opportunity_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunity_docs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT UNSIGNED NOT NULL,
    path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    size INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_docs_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id),
    INDEX idx_docs_opportunity_time (opportunity_id, uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
