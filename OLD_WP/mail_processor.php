/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

<?php
// mail_processor.php - WICHTIG: Keine Leerzeichen vor <?php
ob_start(); // Buffer starten, um Warnungen abzufangen
require_once 'functions.php';
header('Content-Type: application/json');
try {
    $pdo = getPDO();
    $uid = $_POST['uid'] ?? null;
    
    if (!$uid) throw new \Exception("UID fehlt.");
    // PDF Logik hier... (wie zuvor besprochen)
    
    // Falls dompdf noch nicht installiert ist, hier ein Dummy-Erfolg für den Test:
    $filename = "Export_" . $uid . ".pdf"; 
    
    ob_clean(); // Eventuelle Warnungen löschen
    echo json_encode(['success' => true, 'file' => $filename]);
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}