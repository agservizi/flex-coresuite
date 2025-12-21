-- Full MySQL bootstrap for Flex (Coresuite)
-- Adjust passwords/host as needed before running.

-- 1) Create DB and user
CREATE DATABASE IF NOT EXISTS flex DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'flex_user'@'localhost' IDENTIFIED BY 'flex_pass';
GRANT ALL PRIVILEGES ON flex.* TO 'flex_user'@'localhost';
FLUSH PRIVILEGES;
USE flex;

-- 2) Schema
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin','installer','segnalatore') NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password VARCHAR(255) NULL,
    password_reset_token VARCHAR(64) NULL,
    password_reset_expires DATETIME NULL,
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
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    notes TEXT NULL,
    offer_id INT UNSIGNED NOT NULL,
    manager_id INT UNSIGNED NOT NULL,
    commission DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('In attesa','OK','KO') NOT NULL DEFAULT 'In attesa',
    installer_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    phone VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    month TINYINT UNSIGNED NOT NULL,
    created_at DATE NOT NULL,
    CONSTRAINT fk_opportunities_offer FOREIGN KEY (offer_id) REFERENCES offers(id),
    CONSTRAINT fk_opportunities_manager FOREIGN KEY (manager_id) REFERENCES gestori(id),
    CONSTRAINT fk_opportunities_installer FOREIGN KEY (installer_id) REFERENCES users(id),
    CONSTRAINT fk_opportunities_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunity_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT UNSIGNED NOT NULL,
    old_status ENUM('In attesa','OK','KO') NOT NULL,
    new_status ENUM('In attesa','OK','KO') NOT NULL,
    changed_by INT UNSIGNED NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id),
    CONSTRAINT fk_audit_user FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunity_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id),
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunity_docs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT UNSIGNED NOT NULL,
    path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    size INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_docs_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Seed demo data (password hashes: admin123 / installer123)
INSERT INTO users (role, name, email, password) VALUES
('admin','Admin Flex','admin@coresuite.local', '$2y$10$TgHkZC6bpwPF7XTbUqEx1OzhxXTWF1wJ7K3qgAt2s9BXNUuWJHkn6'),
('installer','Luca Installer','luca@coresuite.local', '$2y$10$kVj9gDC11FXGeUXLEVp3juFtRkFGZvV1dV0t1Ik5dUvNbK/C.jYl2')
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO gestori (name, active) VALUES
('FastWave',1),
('FiberPlus',1),
('MobileX',0)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO offers (name, description, commission, manager_id) VALUES
('FW 100','Fibra 100Mbps',35.00,1),
('FW 1000','Fibra 1Gbps',55.00,1),
('FiberPlus Casa','FTTH casa',45.00,2),
('MobileX Sim Only','Voce + 100GB',22.00,3)
ON DUPLICATE KEY UPDATE name = VALUES(name);

SET @curr_month = MONTH(CURDATE());
SET @prev_month = CASE WHEN @curr_month > 1 THEN @curr_month - 1 ELSE 12 END;

INSERT INTO opportunities (first_name,last_name,notes,offer_id,manager_id,commission,status,installer_id,created_by,month,created_at) VALUES
('Maria','Rossi','Nuova attivazione fibra',1,1,35.00,'In attesa',2,NULL,@curr_month,CURDATE()),
('Giulia','Bianchi','Upgrade cliente',2,1,55.00,'OK',2,NULL,@prev_month,DATE_SUB(CURDATE(), INTERVAL 15 DAY)),
('Carlo','Verdi','Linea non attivabile',3,2,45.00,'KO',2,NULL,@prev_month,DATE_SUB(CURDATE(), INTERVAL 25 DAY));
