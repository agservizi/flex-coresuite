-- PostgreSQL schema for Flex (Coresuite)
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
