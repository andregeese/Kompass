<?php
// /nvme/data/apache/config.php

define('PAPERLESS_API_URL', 'http://192.168.0.72:8030');
define('PAPERLESS_TOKEN', '17a32ab64bd39bfff975e81707e1548d737d424d');
define('PAPERLESS_CONSUME_PATH', '/export/paperless/'); // Host-Pfad für Mail-Exports

// Home Assistant
define('HA_URL', 'http://192.168.0.72:8123/api/');
define('HA_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiI5MDkwNjA3YTcyZjc0ZDNlYTFkZjRmNmZlNGExMDQ3MiIsImlhdCI6MTc3ODc1NTA1OSwiZXhwIjoyMDk0MTE1MDU5fQ.U8JkDeNgj6LOzia-uBz_I_NLD3mL759gl7iOHkeIdzE');

// iCloud & Mail (IMAP)
define('MAIL_HOST', '{imap.mail.me.com:993/imap/ssl}');
define('MAIL_USER', 'andregeese@me.com');
define('MAIL_PASS', 'gzca-ojma-tdkq-copm');
define('ICLOUD_ICAL_URL', 'https://pXX-caldav.icloud.com/published/...');

// Datenbank
define('DB_HOST', 'mysql');
define('DB_NAME', 'Dashboard');
define('DB_USER', 'age_user');
define('DB_PASS', 'DeinSicheresPasswort123!');

define('HASH_SALT', 'dein_geheimes_salz_2026');

// Sicherheit (AES-256)
define('ENC_METHOD', 'aes-256-cbc');
define('ENC_KEY', 'ein-sehr-langer-geheimer-schluessel-32-zeichen'); 
define('ENC_IV', '16-zeichen-iv-fix'); // Muss genau 16 Zeichen lang sein

define('TZ', 'Europe/Berlin');
date_default_timezone_set(TZ);

// ==========================================
// HIER WAR DAS LOCH: Datenbank-Verbindung aufbauen
// ==========================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (\PDOException $e) {
    // Falls die DB nicht erreichbar ist, bricht das Skript sauber mit JSON ab
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'details' => [],
        'error' => 'Datenbankverbindung fehlgeschlagen: ' . $e->getMessage()
    ]);
    exit;
}