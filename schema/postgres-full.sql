-- Full PostgreSQL bootstrap for Flex (Coresuite)
-- Adjust passwords/host as needed before running.

-- Run as a superuser (e.g., postgres) then switch to the app role.

-- 1) Create role and database
DO $$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'flex_user') THEN
      CREATE ROLE flex_user LOGIN PASSWORD 'flex_pass';
   END IF;
END$$;

CREATE DATABASE flex OWNER flex_user;

-- Connect to DB (in psql: \c flex)
\c flex

-- 2) Schema
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin','installer')),
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gestori (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS offers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255),
    commission NUMERIC(10,2) NOT NULL DEFAULT 0,
    manager_id INT NOT NULL REFERENCES gestori(id),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS opportunities (
    id SERIAL PRIMARY KEY,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    notes TEXT,
    offer_id INT NOT NULL REFERENCES offers(id),
    manager_id INT NOT NULL REFERENCES gestori(id),
    commission NUMERIC(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL CHECK (status IN ('In attesa','OK','KO')) DEFAULT 'In attesa',
    installer_id INT NOT NULL REFERENCES users(id),
    month SMALLINT NOT NULL,
    created_at DATE NOT NULL
);

-- 3) Seed demo data (password hashes: admin123 / installer123)
INSERT INTO users (role, name, email, password) VALUES
('admin','Admin Flex','admin@coresuite.local', '$2y$10$TgHkZC6bpwPF7XTbUqEx1OzhxXTWF1wJ7K3qgAt2s9BXNUuWJHkn6'),
('installer','Luca Installer','luca@coresuite.local', '$2y$10$kVj9gDC11FXGeUXLEVp3juFtRkFGZvV1dV0t1Ik5dUvNbK/C.jYl2')
ON CONFLICT (email) DO NOTHING;

INSERT INTO gestori (name, active) VALUES
('FastWave',true),
('FiberPlus',true),
('MobileX',false)
ON CONFLICT (name) DO NOTHING;

INSERT INTO offers (name, description, commission, manager_id) VALUES
('FW 100','Fibra 100Mbps',35.00,1),
('FW 1000','Fibra 1Gbps',55.00,1),
('FiberPlus Casa','FTTH casa',45.00,2),
('MobileX Sim Only','Voce + 100GB',22.00,3)
ON CONFLICT (name) DO NOTHING;

DO $$
DECLARE
    curr_month SMALLINT := EXTRACT(MONTH FROM CURRENT_DATE);
    prev_month SMALLINT := CASE WHEN curr_month > 1 THEN curr_month - 1 ELSE 12 END;
BEGIN
    INSERT INTO opportunities (first_name,last_name,notes,offer_id,manager_id,commission,status,installer_id,month,created_at) VALUES
    ('Maria','Rossi','Nuova attivazione fibra',1,1,35.00,'In attesa',2,curr_month,CURRENT_DATE),
    ('Giulia','Bianchi','Upgrade cliente',2,1,55.00,'OK',2,prev_month,CURRENT_DATE - INTERVAL '15 days'),
    ('Carlo','Verdi','Linea non attivabile',3,2,45.00,'KO',2,prev_month,CURRENT_DATE - INTERVAL '25 days')
    ON CONFLICT DO NOTHING;
END$$;
