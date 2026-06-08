/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

<?php
// /nvme/data/apache/functions.php
require_once 'config.php';
function getPaperlessTasks() {
    return callAPI(PAPERLESS_API_URL . 'tasks/?status=started,queued', PAPERLESS_TOKEN);
}
function getHomeAssistantEntity($entity_id) {
    return callAPI(HA_URL . 'states/' . $entity_id, HA_TOKEN, true);
}
function callAPI($url, $token, $isBearer = false) {
    $ch = curl_init($url);
    $auth = $isBearer ? "Authorization: Bearer $token" : "Authorization: Token $token";
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [$auth, "Accept: application/json"]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}
function getSystemRam() {
    $free = shell_exec('free -m');
    $free = (string)trim($free);
    $free_arr = explode("\n", $free);
    $mem = explode(" ", $free_arr[1]);
    $mem = array_filter($mem);
    $mem = array_merge($mem);
    return $mem[2]; // Verwendeter RAM in MB
}
// MySQL Verbindung via PDO
function getPDO() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    try {
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        die("Detail-Fehler: " . $e->getMessage());    }
}
// Zentrale Crypt-Funktion für Mails
function cryptMail($data, $encrypt = true) {
    // Sicherstellen, dass der IV immer exakt 16 Bytes hat
    $iv = substr(ENC_IV, 0, 16); 
    
    if ($encrypt) {
        return openssl_encrypt($data, ENC_METHOD, ENC_KEY, 0, $iv);
    }
    return openssl_decrypt($data, ENC_METHOD, ENC_KEY, 0, $iv);
}
function cleanHTML($html) {
    if (empty($html)) return "";
    
    // Dekodiert Quoted-Printable (wichtig für HTML-Mails & eBons)
    $html = quoted_printable_decode($html);
    // Erlaubt wichtige Tags für das Layout, entfernt aber Scripte
    return strip_tags($html, '<div><p><br><b><strong><ul><li><table><tr><td><a><h1><h2><h3><style><span>');
}
function encryptData($data) {
    return openssl_encrypt($data, 'AES-256-CBC', ENCRYPTION_KEY, 0, ENCRYPTION_IV);
}
function decryptData($data) {
    if (!$data) return "";
    return openssl_decrypt($data, 'AES-256-CBC', ENCRYPTION_KEY, 0, ENCRYPTION_IV);
}