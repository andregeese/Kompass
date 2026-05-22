CREATE TABLE IF NOT EXISTS dashboard_mails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid INT UNIQUE,
    subject_hash VARBINARY(255), -- Verschlüsselter Betreff
    body_hash LONGBLOB,          -- Verschlüsselter Inhalt
    sender VARCHAR(255),
    date_received DATETIME,
    is_exported TINYINT(1) DEFAULT 0,
    INDEX (date_received)
) ENGINE=InnoDB;