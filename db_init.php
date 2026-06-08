<?php
/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */
require_once 'config.php';
// Verbindung ohne Datenbank-Selektion, um Existenz der DB zu prüfen
$host = DB_HOST;
$root_user = 'age';
$root_pass = DB_ROOT_PASS; // Nutzt jetzt die Konstante statt $_ENV
$db_name = DB_NAME;
try {
    $pdo = new PDO("mysql:host=$host", $root_user, $root_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // 1. Datenbank anlegen falls nicht vorhanden
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` 
                CHARACTER SET utf8mb4 
                COLLATE utf8mb4_0900_ai_ci");
    
    $pdo->exec("USE `$db_name` text;");
    // 2. Tabelle für verschlüsselte Mails anlegen
    $table_sql = "CREATE TABLE IF NOT EXISTS dashboard_age (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uid INT UNIQUE NOT NULL,
        subject_hash VARBINARY(512), 
        body_hash LONGBLOB,          
        sender VARCHAR(255),
        date_received DATETIME,
        is_exported TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_date (date_received),
        INDEX idx_exported (is_exported)
    ) ENGINE=InnoDB;";
    $pdo->exec($table_sql);
    
    echo "Check abgeschlossen: Datenbank und Tabelle sind bereit.";
} catch (PDOException $e) {
    die("Fehler bei der DB-Initialisierung: " . $e->getMessage());
}