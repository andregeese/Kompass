<?php
/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */
?>

<?php
// Falls deine Config-Datei anders heißt, passe den Pfad an:
require_once __DIR__ . '/config.php'; 
header('Content-Type: text/plain; charset=utf-8');
echo "--- ICLOUD ORDNER DIAGNOSE --- \n\n";
// Verbindung aufbauen
$mbox = imap_open(MAIL_HOST, MAIL_USER, MAIL_PASS, OP_HALFOPEN);
if (!$mbox) {
    echo "FEHLER: Verbindung zum Server fehlgeschlagen! \n";
    echo imap_last_error();
    exit;
}
echo "Erfolgreich verbunden! Suche Ordner... \n\n";
// Alle Ordner auflisten
$list = imap_list($mbox, "{imap.mail.me.com:993/imap/ssl}", "*");
if (is_array($list)) {
    foreach ($list as $val) {
        // Wir säubern die Ausgabe für eine bessere Lesbarkeit
        echo "Gefundener Pfad: " . $val . "\n";
    }
} else {
    echo "FEHLER: Ordner konnten nicht ausgelesen werden. \n";
    echo imap_last_error();
}
imap_close($mbox);
echo "\n--- DIAGNOSE ENDE ---";