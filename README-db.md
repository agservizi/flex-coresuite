# Flex (Coresuite) - Setup DB

## MySQL
```sql
SOURCE schema/mysql.sql;

-- seed demo data
INSERT INTO users (role, name, email, password) VALUES
('admin','Admin Flex','admin@coresuite.local', '$2y$10$TgHkZC6bpwPF7XTbUqEx1OzhxXTWF1wJ7K3qgAt2s9BXNUuWJHkn6'),
('installer','Luca Installer','luca@coresuite.local', '$2y$10$kVj9gDC11FXGeUXLEVp3juFtRkFGZvV1dV0t1Ik5dUvNbK/C.jYl2');

INSERT INTO gestori (name, active) VALUES ('FastWave',1), ('FiberPlus',1), ('MobileX',0);
INSERT INTO offers (name, description, commission, manager_id) VALUES
('FW 100','Fibra 100Mbps',35.00,1),
('FW 1000','Fibra 1Gbps',55.00,1),
('FiberPlus Casa','FTTH casa',45.00,2),
('MobileX Sim Only','Voce + 100GB',22.00,3);

INSERT INTO opportunities (first_name,last_name,notes,offer_id,manager_id,commission,status,installer_id,month,created_at) VALUES
('Maria','Rossi','Nuova attivazione fibra',1,1,35.00,'In attesa',2,MONTH(CURDATE()),CURDATE()),
('Giulia','Bianchi','Upgrade cliente',2,1,55.00,'OK',2,MONTH(CURDATE())-1,DATE_SUB(CURDATE(), INTERVAL 15 DAY)),
('Carlo','Verdi','Linea non attivabile',3,2,45.00,'KO',2,MONTH(CURDATE())-1,DATE_SUB(CURDATE(), INTERVAL 25 DAY));
```

## PostgreSQL
```sql
\i schema/postgres.sql;

-- seed demo data
INSERT INTO users (role, name, email, password) VALUES
('admin','Admin Flex','admin@coresuite.local', '$2y$10$TgHkZC6bpwPF7XTbUqEx1OzhxXTWF1wJ7K3qgAt2s9BXNUuWJHkn6'),
('installer','Luca Installer','luca@coresuite.local', '$2y$10$kVj9gDC11FXGeUXLEVp3juFtRkFGZvV1dV0t1Ik5dUvNbK/C.jYl2');

INSERT INTO gestori (name, active) VALUES ('FastWave',true), ('FiberPlus',true), ('MobileX',false);
INSERT INTO offers (name, description, commission, manager_id) VALUES
('FW 100','Fibra 100Mbps',35.00,1),
('FW 1000','Fibra 1Gbps',55.00,1),
('FiberPlus Casa','FTTH casa',45.00,2),
('MobileX Sim Only','Voce + 100GB',22.00,3);

INSERT INTO opportunities (first_name,last_name,notes,offer_id,manager_id,commission,status,installer_id,month,created_at) VALUES
('Maria','Rossi','Nuova attivazione fibra',1,1,35.00,'In attesa',2,EXTRACT(MONTH FROM CURRENT_DATE),CURRENT_DATE),
('Giulia','Bianchi','Upgrade cliente',2,1,55.00,'OK',2,EXTRACT(MONTH FROM CURRENT_DATE)-1,CURRENT_DATE - INTERVAL '15 days'),
('Carlo','Verdi','Linea non attivabile',3,2,45.00,'KO',2,EXTRACT(MONTH FROM CURRENT_DATE)-1,CURRENT_DATE - INTERVAL '25 days');
```

Password hashes corrispondono a: admin123 / installer123.
