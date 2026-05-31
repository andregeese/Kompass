<?php
require_once 'functions.php';
header('Content-Type: application/json');

$response = [
    'success' => false,
    'added' => 0,
    'details' => [],
    'errors' => []
];

try {
    $pdo = getPDO();
    $mbox = imap_open(MAIL_HOST, MAIL_USER, MAIL_PASS);
    
    if (!$mbox) {
        throw new \Exception("iCloud-Verbindung fehlgeschlagen: " . imap_last_error());
    }

    // Suche Mails der letzten 14 Tage (etwas Puffer für eBons)
    $since = date("d-M-Y", strtotime("-14 days"));
    $mail_ids = imap_search($mbox, 'SINCE "'.$since.'"');

    if ($mail_ids) {
        foreach ($mail_ids as $id) {
            $uid = imap_uid($mbox, $id);
            
            // Existenz-Check
            $check = $pdo->prepare("SELECT id FROM mails WHERE uid = ?");
            $check->execute([$uid]);
            
            if (!$check->fetch()) {
                $header = imap_headerinfo($mbox, $id);
                
                // 1. Betreff dekodieren (für die Anzeige im Dashboard)
                $elements = imap_mime_header_decode($header->subject);
                $subject = "";
                foreach ($elements as $el) $subject .= $el->text;
                
                // 2. Body holen & dekodieren
                $body = imap_fetchbody($mbox, $id, 1);
                $body_decoded = quoted_printable_decode($body);

                // 3. In DB speichern (Verschlüsselt)
                $stmt = $pdo->prepare("INSERT INTO mails (uid, subject_hash, body_hash, sender, date_received) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $uid,
                    cryptMail($subject, true),
                    cryptMail($body_decoded, true),
                    $header->fromaddress,
                    date('Y-m-d H:i:s', $header->udate)
                ]);

                $response['added']++;
                $response['details'][] = "Neu: " . $subject;
            }
        }
    }
    
    imap_close($mbox);
    $response['success'] = true;

} catch (Exception $e) {
    $response['errors'][] = $e->getMessage();
}

echo json_encode($response);