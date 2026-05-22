<?php
require_once 'functions.php';

header('Content-Type: application/json');

try {
    $pdo = getPDO();
    $uid = $_GET['uid'] ?? null;

    if (!$uid) {
        throw new Exception("Keine UID übergeben.");
    }

    // Mail aus der Datenbank abrufen
    $stmt = $pdo->prepare("SELECT subject_hash, body_hash FROM mails WHERE uid = ?");
    $stmt->execute([$uid]);
    $mail = $stmt->fetch();

    if (!$mail) {
        throw new Exception("Mail nicht gefunden.");
    }

    // Daten entschlüsseln
    $subject = cryptMail($mail['subject_hash'], false);
    $bodyRaw = cryptMail($mail['body_hash'], false);

    // HTML säubern und für die Anzeige aufbereiten
    // Hier nutzen wir die Funktion aus dem vorherigen Schritt
    $cleanBody = cleanHTML($bodyRaw);

    echo json_encode([
        'success' => true,
        'subject' => $subject,
        'body'    => $cleanBody
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}