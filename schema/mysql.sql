-- MySQL schema for Flex (Coresuite)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin','installer') NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    offer_id INT UNSIGNED NOT NULL,
    manager_id INT UNSIGNED NOT NULL,
    commission DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('In attesa','OK','KO') NOT NULL DEFAULT 'In attesa',
    installer_id INT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    created_at DATE NOT NULL,
    CONSTRAINT fk_opportunities_offer FOREIGN KEY (offer_id) REFERENCES offers(id),
    CONSTRAINT fk_opportunities_manager FOREIGN KEY (manager_id) REFERENCES gestori(id),
    CONSTRAINT fk_opportunities_installer FOREIGN KEY (installer_id) REFERENCES users(id)
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
