<?php

/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */ 

namespace App\Services;

use Exception; // Falls du Exceptions nutzt
use App\Services\PaperlessProcessor;
use App\Services\ContactProcessor;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Carbon\Carbon;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Throwable;
use PDO;
use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * @property \App\Services\MailProcessor $mailProcess
 */
class MailProcessor extends \App\Core\Database
{
    use \App\Core\ServiceTrait;
    protected $dashboard;
    //private $imapStream = null;
    public function __construct($dashboard)
    {
        parent::__construct($dashboard);
        $this->dashboard = $dashboard;
    }

    /**
     * Destruktor: Schließt die Verbindung sauber.
     */
    public function __destruct()
    {
        // 🚀 Nur schließen, wenn es eine valide, offene Ressource/Verbindung ist!
        if (isset($imapStream) && $imapStream instanceof \IMAP\Connection) {
            try {
                //@imap_close($imapStream);
            } catch (\Throwable $e) {
                // Falls sie intern schon zu war: Lautlos ignorieren
            }
        }
    }
    /**
     * Private Methode für den Verbindungsaufbau (auch für Reconnects).
     * @param string $host IMAP-Host
     * @param string $user Benutzername
     * @param string $password Passwort
     */
    private function connectIMAP($folderPath = null)
    {
        error_log(__FILE__ . ": " . __FUNCTION__);

        $host = $this->systemProcess->getRequiredEnv('IMAP_HOST');
        $port = $this->systemProcess->getRequiredEnv('IMAP_PORT');
        $user = $this->systemProcess->getRequiredEnv('IMAP_USER');
        $pw = $this->systemProcess->getRequiredEnv('IMAP_PASSWORD');

        $ssl = ($port == 993) ? '/imap/ssl' : '';


        if ($folderPath !== null) {
            $connectionString = "{" . $host . ":" . $port . $ssl . "}" . $folderPath;
        } else {
            $connectionString = "{" . $host . ":" . $port . $ssl . "}";
        }

        error_log("connectionstring: " . $connectionString);

        $stream = @imap_open($connectionString, $user, $pw, OP_SILENT, 1);
        if (!$stream) {
            throw new \Exception("IMAP-Verbindung fehlgeschlagen: " . imap_last_error());
        }

        return $stream;
    }
    /**
     * Verschlüsselt den Mail-Inhalt via PHP (AES-256-CBC)
     */
    private function encryptContent(string $data): string
    {
        $encryption_key = defined('HASH_SALT') ? HASH_SALT : 'fallback_key_32_chars_long!!';

        // 1. Schlüssel als 32-Byte Raw-Binary generieren (echtes AES-256)
        $key = hash('sha256', $encryption_key, true);

        // 2. Einen echten, zufälligen IV erzeugen (16 Bytes für AES-CBC)
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));

        // 3. Verschlüsseln
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        // 4. IV und verschlüsselte Daten zusammenführen
        // Wir speichern den IV einfach vor den Daten, damit wir ihn beim Entschlüsseln wieder haben
        return base64_encode($iv . $encrypted);
    }


    /**
     * Holt eine Mail anhand ihres Hashes. WICHTIG: Der Hash wird in der DB generiert, um die ID zu verschleiern.
     * @param string $hash Der SHA256-Hash der Mail-ID mit Salt.
     */
    public function getMailById($id)
    {
        error_log("## " . __FUNCTION__ . " ##");
        $sql = "SELECT HEX(AES_ENCRYPT(CAST(m.id AS CHAR), :crypto_key)) AS id,
                       c.display_name as sender,
                       m.subject_hash as sHash,
                       m.uid, m.date_received, m.sender_ceid, m.recipient_ceid, m.status, m.imap_folder, ce.email, c.display_name
                  FROM mails m
                INNER JOIN contact_emails as ce ON ce.id = m.sender_ceid
                INNER JOIN contacts as c ON c.id = ce.contact_id
                 WHERE m.tenant_id=:tenant_id
                    AND m.id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED)
                    AND (m.status NOT IN('deleted') OR m.status IS NULL) 
                    LIMIT 1;";

        $dbResult = $this->queryIt(
            $sql,
            [
                'id' => $id
            ],
            false,
            false,
            false
        );

       
        if($dbResult->success && !empty($dbResult->data))
        {
            $row = $dbResult->data->fetched[0];
            //error_log(print_r($row, true));
            $row['subject'] = $this->dashboard->decrypt($row['sHash']);
        }

        return ($dbResult->success && !empty($dbResult->data) && !empty($dbResult->data->fetched))
            ?  $row
            : null;
    }
    /**
     * Holt den HTML-Inhalt einer E-Mail
     * @param resource $imapStream Die aktive IMAP-Verbindung
     * @param int $msgNumber Die Nummer der Mail im Postfach
     * @return string HTML-Inhalt oder leerer String
     */
    public function getHtmlBody($id)
    {
        $mail = $this->getMailById($id);
        $folder = $mail['imap_folder'];

        error_log("## " . __FUNCTION__ . " mit $id und $folder aufgerufen ##");

        if (empty($mail) || !isset($mail['uid']) || (int)$mail['uid'] <= 0) {
            error_log("NO MAIL OR UID");
            throw new \Exception("Ungültige Mail-Referenz oder UID fehlt in der lokalen Datenbank.");
        }

        $uid = (int)$mail['uid'];
        $imapStream = $this->connectIMAP($folder);

        if ($folder !== 'INBOX') {
            if (!imap_reopen($imapStream, $folder)) {
                throw new \Exception("Konnte Ordner $folder nicht öffnen: " . imap_last_error());
            }
        }
        try {
            $structure = imap_fetchstructure($imapStream, $uid, FT_UID);

            if ($structure === false) {
                $imapError = imap_last_error();
                throw new \Exception("IMAP Fehler: " . ($imapError ?: "Unbekannter Fehler beim Abrufen der Struktur"));
            }

            // 1. Inhalt holen
            $htmlContent = $this->findPart($uid, $structure, 'TEXT/HTML', $imapStream);

            if (!$htmlContent) return null;

            // 2. Security: HTML Purifier zum Säubern
            $config = \HTMLPurifier_Config::createDefault();
            $purifier = new \HTMLPurifier($config);
            imap_close($imapStream);

            return $purifier->purify($htmlContent);

        } catch (\Throwable $e) {
            imap_close($imapStream);
            // Logge den Fehler für deine Analyse
            error_log("IMAP-Struktur Fehler bei UID $uid: " . $e->getMessage());

            error_log(__FUNCTION__ . ": ERROR: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());

            // Je nach Wunsch: Wirf eine eigene Exception oder gib null/einen Fehlertext zurück
            throw new \Exception("Fehler beim Analysieren der E-Mail: " . $e->getMessage());
        }
    }
    /**
     * erhalte den vollen Order IMAP Path
     */
    private function getFullFolderPath(string $folder): string
    {
        $imapPath = "{" . $this->systemProcess->getRequiredEnv('IMAP_HOST') . ":" . $this->systemProcess->getRequiredEnv('IMAP_PORT') . "/imap/ssl}";
        if ($folder !== "INBOX") {
            $finalPath = $imapPath .= $folder;
            return $finalPath;
        }
        error_log($imapPath);
        return $imapPath;
    }
    /**
     * Holt den reinen Text-Inhalt einer E-Mail (Fallback)
     */
    public function getTxtBody($hash)
    {
        error_log("## " . __FUNCTION__ . " ##");
        
        $mail = $this->getMailById($hash);
        if (empty($mail) || !isset($mail['uid'])) {
            throw new \Exception("Keine gültige Mail oder UID gefunden.");
        }

        // 1. Immer den echten imap_folder nutzen!
        $folder = $mail['imap_folder']; 
        $uid = (int)$mail['uid'];

        try {
            $imapStream = $this->connectIMAP($folder);
            if ($folder !== 'INBOX') {
                if (!imap_reopen($imapStream, $folder)) {
                    throw new \Exception("Konnte Ordner $folder nicht öffnen: " . imap_last_error());
                }
            }
            
            //$mail = $this->getMailById($hash);

            /*$msgNumber = imap_msgno($imapStream, $mail['uid']);
            if (!$msgNumber) {
                return "Mail existiert nicht mehr auf dem Server.";
            }*/
            $structure = imap_fetchstructure($imapStream, $uid, FT_UID);
            $plainText = $this->findPart($uid, $structure, 'TEXT/PLAIN', $imapStream);

            if (!$plainText) {
                return "<i>(Kein lesbarer Textinhalt gefunden)</i>";
            }
            imap_close($imapStream);
            $cleanText = mb_convert_encoding($plainText, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            return nl2br(htmlspecialchars($cleanText));
        
            } catch (\Throwable $e) {
            imap_close($imapStream);
            
            throw new \Exception("Fehler beim Lesen des Mail-Inhalts: " . $e->getMessage());
        }
    }
    /**
     * Interne Hilfsfunktion: Durchsucht die Mail-Struktur rekursiv nach dem gewünschten MIME-Type
     */
    private function findPart($uid, $structure, $mimeType, &$imapStream, $partNumber = null)
    {
        try {
            $type = $structure->type ?? 0;
            $primaryTypes = ["TEXT", "MULTIPART", "MESSAGE", "APPLICATION", "AUDIO", "IMAGE", "VIDEO", "OTHER"];
            $primary = $primaryTypes[$type] ?? "TEXT";
            $subtype = isset($structure->subtype) ? strtoupper($structure->subtype) : "PLAIN";
            $currentMimeType = $primary . '/' . $subtype;

            if ($currentMimeType === $mimeType) {
                $part = $partNumber ?: "1";
                $data = imap_fetchbody($imapStream, $uid, $part, FT_UID);

                $encoding = $structure->encoding ?? 0;

                // Sauberer Check für IMAP Encodings:
                // 3 = BASE64, 4 = QUOTED-PRINTABLE
                switch ($encoding) {
                    case 3:
                        return base64_decode($data);
                    case 4:
                        return quoted_printable_decode($data);
                    default:
                        return $data;
                }
            }

            if ($type == 1 && isset($structure->parts) && is_array($structure->parts)) {
                foreach ($structure->parts as $index => $sub) {
                    $newPart = $partNumber ? $partNumber . '.' . ($index + 1) : (string)($index + 1);
                    $found = $this->findPart($uid, $sub, $mimeType, $imapStream, $newPart);
                    if ($found) return $found;
                }
            }
            return null;
        } catch (\Throwable $e) {
            error_log(__FUNCTION__ . ": ERROR: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verschiebt eine Mail in den DMS-Ordner. Wichtig: Diese Methode sollte erst nach erfolgreicher PDF-Erstellung aufgerufen werden, um Datenverlust zu vermeiden.
     * @param string $hash Der Hash der Mail, die verschoben werden soll.
     * @param string $targetFolder Optional: Zielordner im DMS, Standard ist "Recht". Der tatsächliche IMAP-Ordner könnte z.B. "INBOX/DMS" sein, abhängig von der Serverstruktur.
     */
    public function moveToDMS($id, $targetFolder = 'DMS')
    {
        // Verbindung sicherstellen (nutzt die private connect Methode)
        $imapStream = $this->connectIMAP();
        $mail = $this->getMailById($id);
        $msgUid = $mail['uid'];
        // Verschieben via UID
        $moveSuccess = imap_mail_move($imapStream, $msgUid, $targetFolder, CP_UID);
        if (!$moveSuccess) {
            throw new \Exception("IMAP-Move nach $targetFolder fehlgeschlagen: " . imap_last_error());
        }

        // Wichtig, um die Mail aus der INBOX zu löschen
        imap_expunge($imapStream);
        // Lokalen DB Status aktualisieren

        $res = $this->queryIt(
            "UPDATE mails SET status = 'archived' 
                WHERE tenant_id=:tenant_id 
                AND id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED)",
            ['id' => $id]
        );
        imap_close($imapStream);
        return true;
    }

    private function loadAllEmailsIntoCache(): array
    {
        $cache = [];

        // Wir brauchen nur die E-Mail und die ID. 
        // ACHTUNG: Filter auf den aktuellen tenant_id setzen!
        $tenantId = $_SESSION['tenant_id'] ?? null;

        $sql = "SELECT id, email FROM contact_emails WHERE tenant_id = :tenant_id";

        $dbResult = $this->queryIt(
            $sql,
            ['tenant_id' => $tenantId],
            false, // keine Transaktion nötig
            false  // kein InsertID
        );
        if ($dbResult->success && !empty($dbResult->data->fetched)) {
            foreach ($dbResult->data->fetched as $row) {
                $cache[$row['email']] = (int)$row['id'];
            }
        }
        error_log("Cache geladen: " . count($cache) . " E-Mail-Adressen im Speicher.");
        
        return $cache;
    }


    /**
     * lehnt sich an die Suche Mail an, wird in Notizen genutzt.
     * @param $last int
     * @return array 
     */
    public function getRecentMails(int $last = 10): array
    {
        $sql = "SELECT 
                        HEX(AES_ENCRYPT(CAST(m.id AS CHAR), :crypto_key)) AS id,
                        -- m.id_hash as hash,
                        ce.contact_id, 
                        m.imap_folder as folder,
                        ce.email as sender,
                        c.display_name as contact_name, 
                        m.uid as uid,
                        m.subject_hash as sHash,
                        ce.id as ce_id,
                        m.date_received as date_received
                FROM mails as m 
                INNER JOIN contact_emails as ce ON ce.id = m.sender_ceid
                INNER JOIN contacts as c on c.id = ce.contact_id
                WHERE 
                    m.tenant_id = :tenant_id
                    AND (m.status NOT IN('deleted') OR m.status IS NULL) 
                ORDER BY m.date_received DESC
                LIMIT :limit;";
        $dbResult = $this->queryIt(
            $sql,
            ['limit' => (int)$last],
            false,
            false,
            false
        );
        $results = $dbResult->data->fetched;
        if (!$dbResult->success) {
            throw new \Exception("Fehler beim Abruf");
        }
        $filtered = [];
        foreach ($results as $row) {
            if (isset($row['hash'])) {
                $row['hash'] = bin2hex($row['hash']);
            }
            $subject = strtolower($this->dashboard->decrypt($row['sHash']));
            $sender = strtolower($row['sender']);
            $contactName = strtolower($row['contact_name'] ?? '');
            $row['subject'] = $subject; // Zurückgeben als Klartext
            $filtered[] = $row;
        }
        return $filtered;
    }

    /**
     * Gleicht die Datenbank mit dem IMAP-Server ab, um gelöschte/verschobene Mails zu erkennen.
     */
    public function reconcileFolder(string $folder): array
    {
        $pid = getmypid();
        error_log("Prozess: [$pid] Starte Reconciliation für Ordner: $folder");

        $imapStream = $this->connectIMAP($folder);
        if (!$imapStream) return ['deleted' => 0];

        // 1. Alle UIDs aktuell auf dem IMAP-Server holen
        $serverUids = imap_sort($imapStream, SORTARRIVAL, 0, SE_UID);
        @imap_close($imapStream);

        if (!is_array($serverUids)) $serverUids = [];
        $serverUidMap = array_flip($serverUids); // Für schnelle isset() Abfrage

        // 2. Alle aktiven UIDs aus der DB für diesen Ordner holen
        $sqlDB = "SELECT uid, id FROM mails WHERE tenant_id = :tenant_id AND imap_folder = :folder AND status = 'active';";
        $dbRes = $this->queryIt($sqlDB, ['folder' => $folder], false, false, false);
        
        $deletedCount = 0;

        if ($dbRes->success && is_array($dbRes->data->fetched)) {
            foreach ($dbRes->data->fetched as $row) {
                $dbUid = (int)$row['uid'];
                $dbId  = (int)$row['id'];

                // 3. Wenn die UID der DB auf dem IMAP-Server fehlt -> Mail wurde gelöscht/verschoben
                if (!isset($serverUidMap[$dbUid])) {
                    $sqlDelete = "UPDATE mails SET status = 'deleted' WHERE id = :id AND tenant_id = :tenant_id;";
                    $this->queryIt($sqlDelete, ['id' => $dbId], true, false, false);
                    $deletedCount++;
                }
            }
        }

        error_log("Reconciliation [$folder]: $deletedCount Mails als gelöscht/entfernt markiert.");

        return ['deleted' => $deletedCount];
    }

    /**
     * E-Mail Syncronisation
     */
    public function syncMails(int $maxNewMailsLimit, string $folder): array
    {
        @set_time_limit(240);
        $pid = getmypid();
        error_log("Prozess: [$pid] Starte Sync für Ordner: $folder");
        
        $added = 0;
        $updateCount = 0;

        $imapStream = $this->connectIMAP($folder);
        if (!$imapStream) {
            return ["added" => 0, "update" => 0];
        }

        // 1. Alle UIDs aufsteigend vom Server holen (SORTARRIVAL: 0 = Älteste zuerst)
        $allUids = imap_sort($imapStream, SORTARRIVAL, 0, SE_UID);
        if (!is_array($allUids) || empty($allUids)) {
            if ($imapStream instanceof \IMAP\Connection) {
                @imap_close($imapStream);
            }
            return ["added" => 0, "update" => 0];
        }

        // 2. Alle UIDs aus der DB für diesen Ordner in Key-Map laden (O(1) Lookup)
        $mailsInDB = [];
        $sql = "SELECT uid, id FROM mails WHERE tenant_id = :tenant_id AND imap_folder = :folder;";
        $dbRes = $this->queryIt($sql, ['folder' => $folder], false, false, false);
        if ($dbRes->success && is_array($dbRes->data->fetched)) {
            foreach ($dbRes->data->fetched as $row) {
                $mailsInDB[(int)$row["uid"]] = (int)$row["id"];
            }
        }

        // 3. Echte Differenz bilden: Welche Server-UIDs fehlen noch in der DB?
        // Da $allUids aufsteigend ist, beginnt $missingUids automatisch bei der ÄLTESTEN fehlenden Mail!
        $missingUids = array_values(array_filter($allUids, function ($uid) use ($mailsInDB) {
            return !isset($mailsInDB[(int)$uid]);
        }));

        $totalMissing = count($missingUids);
        error_log("DEBUG-SYNC ($folder): Server gesamt: " . count($allUids) . " | In DB: " . count($mailsInDB) . " | Fehlend: $totalMissing");

        if (empty($missingUids)) {
            if ($imapStream instanceof \IMAP\Connection) {
                @imap_close($imapStream);
            }
            return ["added" => 0, "update" => 0];
        }

        // 4. Nimm die ältesten X fehlenden Mails (ab der kleinsten fehlenden UID)
        $uidsToProcess = array_slice($missingUids, 0, $maxNewMailsLimit);
        $firstUid = $uidsToProcess[0];
        $lastUidInBatch = end($uidsToProcess);

        error_log("Sync ($folder): Verarbeite " . count($uidsToProcess) . " Mails (Von ältester UID $firstUid bis UID $lastUidInBatch). Noch verbleibend: " . ($totalMissing - count($uidsToProcess)));

       // 5. IMAP-Batch-Fetch der Overviews (NUR für die tatsächlich fehlenden Mails!)
        $uidSequence = implode(',', $uidsToProcess);
        $overviews = imap_fetch_overview($imapStream, $uidSequence, FT_UID);

        $overviewMap = [];
        if (is_array($overviews)) {
            foreach ($overviews as $ov) {
                if (isset($ov->uid)) {
                    $overviewMap[(int)$ov->uid] = $ov;
                }
            }
        }

        // Hier sammeln wir die fertigen Daten für die Datenbank
        $mailsToWrite = [];

        error_log("mails in Cache laden");
        foreach ($uidsToProcess as $uid) 
        {
            $uid = (int)$uid;
            if (!isset($overviewMap[$uid])) continue;
            $ov = $overviewMap[$uid];

            $rawSubject = $ov->subject ?? '';
            $rawHeaders = '';
            
            # error_log("\t $uid in Cache geladen");

            try {
                //error_log(print_r($ov, true));
                // Fallback-Header holen (Das bleibt leider ein Einzel-Request, falls nötig)
                if (empty($ov->from) || empty($ov->to)) {
                    $header = imap_headerinfo($imapStream, imap_msgno($imapStream, $uid));
                    $ov->from = $ov->from ?: ($header->fromaddress ?? 'unknown@unknown.local');
                    $ov->to = $ov->to ?: ($header->toaddress ?? 'unknown@unknown.local');
                }

                $formattedDate = $ov->date ? date('Y-m-d H:i:s', strtotime($ov->date)) : null;
                $fromData = $this->parseAndDecodeAddress($ov->from ?: '');
                $toData   = $this->parseAndDecodeAddress($ov->to ?: '');

                // Erst HIER den teuren Netzwerk-Call machen
                $rawHeaders = imap_fetchheader($imapStream, $uid, FT_UID);
                if (preg_match('/^Subject:\s+(.*?)(?=\r?\n[^\s]|$)/mi', $rawHeaders, $matches)) {
                    $rawSubject = trim(str_replace(["\r", "\n"], '', $matches[1]));
                }
                
                // Ggf. Header-Fallback
                if (empty($ov->from)) {
                    $header = imap_headerinfo($imapStream, imap_msgno($imapStream, $uid));
                    $ov->from = $header->fromaddress ?? 'unknown@unknown.local';
                    $ov->to = $header->toaddress ?? 'unknown@unknown.local';
                }

                $cleanSubject = $this->decodeMimeHeader($rawSubject);
                $subjectHash = $this->dashboard->encrypt($cleanSubject);
                $message_id = $ov->message_id ?? '';

                // Wir packen die vorbereiteten Daten einfach in unser lokales Array
                $mailsToWrite[] = [
                    'uid'           => (int)$uid,
                    'message_id'    => $message_id,
                    'subject_hash'  => $subjectHash ?? '',
                    'date_received' => $formattedDate,
                    'from_name'     => $fromData['name'],
                    'from_email'    => $fromData['email'],
                    'to_name'       => $toData['name'],
                    'to_email'      => $toData['email'],
                    'is_read'       => (int)($ov->seen ?? 0),
                    'is_flagged'    => (int)($ov->flagged ?? 0),
                    'is_answered'   => (int)($ov->answered ?? 0),
                    'is_newsletter' => (int)$this->checkIfNewsletter($cleanSubject, $rawHeaders ?? '')               
                ];

            } catch (\Throwable $e) {
                error_log("Fehler bei der Datenvorbereitung für UID $uid: " . $e->getMessage());
                error_log("Fehler bei der Datenvorbereitung für UID $uid: " . print_r($ov, true));
            }
        }
        error_log("mails im Cache");


        $ceCache = [];
        $sql = "SELECT email, id FROM contact_emails WHERE tenant_id=:tenant_id;";
        $dbRes = $this->queryIt($sql, [], false, false, false);

        if ($dbRes->success && is_array($dbRes->data->fetched))
        {
            //error_log(print_r($dbRes->data->fetched, true));
            foreach ($dbRes->data->fetched as $row)
            {
                $ceCache[$row["email"]] = (int)$row["id"];
            }
        }

        $mailsInDB = [];
        $sql = "SELECT uid, id FROM mails WHERE tenant_id=:tenant_id AND imap_folder = :folder;";
        $dbRes = $this->queryIt($sql, ['folder' => $folder], false, false, false);
        if ($dbRes->success && is_array($dbRes->data->fetched))
        {
            //error_log(print_r($dbRes->data->fetched, true));
            foreach ($dbRes->data->fetched as $row)
            {
                $mailsInDB[$row["uid"]] = (int)$row["id"];
            }
        }


        // 🚀 SCHRITT 2: Reiner, ultraschneller Datenbank-Schreibprozess innerhalb der Transaktion
        if (!empty($mailsToWrite)) 
        {
            
            $contactProcess = $this->getService("processContact", \App\Services\ContactProcessor::class);
            $highestSafeUid =0;
            foreach ($mailsToWrite as $mail) 
            {
                try {
                    $currentUid = $mail['uid'];
                    
                    // A) Bereits in diesem Ordner mit gleicher UID verarbeitet?
                    if (isset($mailsInDB[$currentUid])) {
                        error_log ("-- mail (" . $mail['uid'] . ") in DB!--");
                        $highestSafeUid = max($highestSafeUid, $currentUid);
                        continue;
                    }
                    // B) MOVE-ERKENNUNG: Existiert diese Message-ID bereits in einem anderen Ordner?
                    $existingMail = null;
                    if (!empty($mail['message_id'])) {
                        $sqlCheck = "SELECT id, imap_folder FROM mails WHERE tenant_id = :tenant_id AND message_id = :msg_id LIMIT 1;";
                        $resCheck = $this->queryIt($sqlCheck, ['msg_id' => $mail['message_id']], false, false, false);
                        if ($resCheck->success && !empty($resCheck->data->fetched)) {
                            $existingMail = $resCheck->data->fetched[0];
                        }
                    }

                    /*if ($existingMail) {
                        // Mail wurde verschoben -> UPDATE Ordner, UID & Status
                        $sqlUpdate = "UPDATE mails 
                                      SET imap_folder = :folder, uid = :uid, status = 'active', 
                                          is_read = :is_read, is_flagged = :is_flagged, is_answered = :is_answered 
                                      WHERE id = :id AND tenant_id = :tenant_id;";
                        
                        // $this->queryIt($sqlUpdate, [
                        //     'folder'      => $folder,
                        //     'uid'         => $currentUid,
                        //     'is_read'     => $mail['is_read'],
                        //     'is_flagged'  => $mail['is_flagged'],
                        //     'is_answered' => $mail['is_answered'],
                        //     'id'          => $existingMail['id']
                        // ], true, false, false);

                        $updateCount++;
                        $mailsInDB[$currentUid] = (int)$existingMail['id'];
                        $highestSafeUid = max($highestSafeUid, $currentUid);
                        
                        error_log("MAIL VERSCHOBEN: ID {$existingMail['id']} von '{$existingMail['imap_folder']}' nach '$folder' (Neue UID: $currentUid)");
                        continue;
                    }*/

                    $senderId = (int)$ceCache[$mail['from_email']] ?? null;
                    //error_log("senderId 1.: $senderId");

                    if (empty($senderId) || $senderId === 0){
                        $senderId = $contactProcess->insertContact($mail['from_name'], $mail['from_email'], 'person');
                        $ceCache[$mail['from_email']] = (int)$senderId;
                        //error_log("senderId 2.: $senderId");
                    }
                    
                    $reciepId = (int)$ceCache[$mail['to_email']] ?? null;
                    //error_log("reciepId 1.: $reciepId");

                    if (empty($reciepId) || $reciepId === 0 ){
                        $reciepId = $contactProcess->insertContact($mail['to_name'], $mail['to_email'], 'person');
                        $ceCache[$mail['to_email']] = (int)$reciepId;
                        //error_log("reciepId 2.: $reciepId");
                    }
                    
                    if ($reciepId<=0 || $senderId<=0)
                    {
                        error_log("SYNC ERROR: UID $currentUid abgebrochen (Sender/Empfänger ungültig).");
                        //$hasErrorInBatch = true; // Lücke entsteht!
                        continue;  
                    }

                    $sqlInsert = "INSERT INTO mails (uid, message_id, subject_hash, date_received, imap_folder, status, tenant_id, is_read, is_flagged, is_answered, is_newsletter, sender_ceid, recipient_ceid) 
                                VALUES (:uid, :message_id, :subject_hash, :date_received, :folder, 'active', :tenant_id, :is_read, :is_flagged, :is_answered, :is_newsletter, :sceid, :rceid)";
                    
                    $dbResult = $this->queryIt($sqlInsert, [
                        'uid'           => $mail['uid'],
                        'message_id'    => $mail['message_id'],
                        'subject_hash'  => $mail['subject_hash'],
                        'date_received' => $mail['date_received'],
                        'folder'        => $folder,
                        'is_read'       => $mail['is_read'],
                        'is_flagged'    => $mail['is_flagged'],
                        'is_answered'   => $mail['is_answered'],
                        'is_newsletter' => $mail['is_newsletter'],
                        'sceid'         => (int)$senderId,
                        'rceid'         => (int)$reciepId
                    ], true, true, false);
                        

                    if ($dbResult->success) {
                        $added++;
                        $mailsInDB[$currentUid] = (int)$dbResult->data->lastId;
                        $highestSafeUid = max($highestSafeUid, $currentUid);
                    } else {
                        error_log("Mail Insert fehlgeschlagen für UID $currentUid: " . ($dbResult->error ?? 'Unbekannter Fehler'));
                    }
                    

                } catch (\Throwable $dbEx) {
                    error_log("DB-Insert-Fehler bei UID " . $mail['uid'] . ": " . $dbEx->getMessage());
                }
            }
        }

        error_log($added . " - " . $folder . " - " . $highestUid . " - " . $lastUid . " - " . $highestSafeUid);

       // 8. Checkpoint aktualisieren
        if ($highestSafeUid > $lastUid) {
            $this->saveSyncCheckpoint($folder, $highestSafeUid);
        }

        // FIX: Erst ganz am Ende des gesamten Sync-Vorgangs schließen
        if (isset($imapStream) && $imapStream instanceof \IMAP\Connection) {
            try {
                @imap_close($imapStream);
            } catch (\Throwable $ex) {
                // Falls sie serverseitig schon zu war: Stumm ignorieren
            }
        }
    
        error_log("Prozess: [$pid] End Sync Ordner: $folder | Neu: $added | Verschoben/Updated: $updateCount");

        return [
            "added"     => $added,
            "update"    => $updateCount
        ];
    }

    /**
     * Prüft anhand der Header und des Betreffs, ob eine Mail ein Newsletter ist.
     * * @param string $subject Der Betreff der Mail
     * @param string $headers Der komplette Roh-Header der Mail
     * @return bool
     */
    private function checkIfNewsletter(?string $subject, string $headers): bool
    {
        $subject = $subject ?? '';
        //error_log("NL check");
        // 1. Der "List-Unsubscribe" Header ist das stärkste Indiz.
        // Professionelle Newsletter enthalten diesen Header zwingend.
        if (preg_match('/^List-Unsubscribe:/mi', $headers)) {
            return true;
        }

        // 2. Keywords im Betreff prüfen (optional, für manuelle Newsletter)
        $keywords = ['newsletter', 'abmelden', 'unsubscribe', 'angebot', 'wochenrückblick'];
        $lowerSubject = mb_strtolower($subject);
        
        foreach ($keywords as $keyword) {
            if (str_contains($lowerSubject, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Synchronisiert Statusänderungen für bereits existierende Mails
     * Konzentriert sich nur auf die letzten N Mails, um Performance zu sparen.
     */
    public function deltaSync(string $folder, int $limit = 100): int|null
    {
        $imapStream = $this->connectIMAP($folder);
        if (!$imapStream) return null;

        $allUids = imap_sort($imapStream, SORTARRIVAL, 0, SE_UID);
        if ($allUids === false) {
            @imap_close($imapStream);
            return 0;
        }

        $checkUids = array_slice($allUids, -$limit);
        $namedPlaceholders = [];
        $params = ['folder' => $folder];

        foreach ($checkUids as $index => $uid) {
            $paramKey = 'uid_' . $index; 
            $namedPlaceholders[] = ':' . $paramKey;
            $params[$paramKey] = $uid;             
        }
        
        $inClause = implode(', ', $namedPlaceholders);

        $sqlFetch = "SELECT HEX(AES_ENCRYPT(CAST(m.id AS CHAR), :crypto_key)) AS id, uid, is_read, is_flagged, is_answered 
                    FROM mails as m
                    WHERE uid IN ($inClause) 
                        AND status='active' 
                        AND tenant_id = :tenant_id 
                        AND imap_folder = :folder;";

        $dbMailsResult = $this->queryIt($sqlFetch, $params, false, false, false);
        
        // 🚀 FIX: Sicherer Zugriff auf ->data->fetched analog zum unteren Code-Teil
        $dbMap = [];
        if ($dbMailsResult && $dbMailsResult->success && !empty($dbMailsResult->data->fetched)) {
            foreach ($dbMailsResult->data->fetched as $m) {
                $dbMap[$m->uid] = $m;
            }
        }

        $updatedCount = 0;
        
        // 🚀 FIX: $dbMap wurde dem `use`-Block hinzugefügt, damit es inside der Transaktion existiert!
        //$this->transaction(function () use ($checkUids, $imapStream, $folder, $allUids, $dbMap, &$updatedCount) {

            foreach ($checkUids as $uid) {
                if (!isset($dbMap[$uid])) continue; 

                $ov = imap_fetch_overview($imapStream, (string)$uid, FT_UID)[0] ?? null;
                if (!$ov) continue;

                $dbMail = $dbMap[$uid];
                if ($dbMail->is_read != $ov->seen || $dbMail->is_flagged != $ov->flagged || $dbMail->is_answered != $ov->answered) {

                    // 🚀 FIX: Doppelpunkt bei :tenant_id korrigiert
                    $this->queryIt(
                        "UPDATE mails 
                        SET is_read = :is_read, is_flagged = :is_flagged, is_answered = :is_answered 
                        WHERE id = :id AND tenant_id = :tenant_id",
                        [
                            'is_read'     => (int)$ov->seen,
                            'is_flagged'  => (int)$ov->flagged,
                            'is_answered' => (int)$ov->answered,
                            'id'          => $dbMail->id
                        ]
                    );
                    $updatedCount++;
                }
            }

            // HINWEIS: Dieser Block zieht im Live-Betrieb viel Performance (siehe oben)
            $sql = "SELECT uid FROM mails WHERE imap_folder = :folder AND tenant_id = :tenant_id";
            $rawResult = $this->queryIt($sql, ['folder' => $folder], false);

            $dbUidList = [];
            if ($rawResult && $rawResult->success && !empty($rawResult->data->fetched)) {
                $dbUidList = array_map('intval', array_column($rawResult->data->fetched, 'uid'));
            }

            $toDelete = array_diff($dbUidList, array_map('intval', $allUids));

            if (!empty($toDelete)) {
                $namedPlaceholders = [];
                $params = ['folder' => $folder];

                foreach (array_values($toDelete) as $index => $uid) {
                    $paramKey = 'uid_' . $index;
                    $namedPlaceholders[] = ':' . $paramKey;
                    $params[$paramKey] = (int)$uid;
                }

                $inClause = implode(', ', $namedPlaceholders);
                
                $sqlUpdate = "UPDATE mails 
                            SET status = 'moved' 
                            WHERE uid IN ($inClause) AND imap_folder = :folder AND tenant_id = :tenant_id;";

                $this->queryIt($sqlUpdate, $params);
            }
        //});

        @imap_close($imapStream);

        return $updatedCount;
    }

    /**
     * Zerlegt einen E-Mail-Header-String in Name und E-Mail-Adresse.
     * Dekodiert dabei MIME-kodierte Teile korrekt, bevor geparst wird.
     *
     * @param string $addressString
     * @return array{name: string, email: string}
     */
    private function parseAndDecodeAddress(string $addressString): array
    {
        $default = ['name' => '', 'email' => 'unknown@unknown.local'];
        if (empty($addressString)) {
            return $default;
        }
        // 1. Zuerst den gesamten String dekodieren, um "=?"-Blöcke und Unterstriche aufzulösen
        $decodedString = $this->decodeMimeHeader($addressString);
        // 2. Den sauberen String parsen
        $addresses = imap_rfc822_parse_adrlist($decodedString, 'unknown.local');
        if (empty($addresses[0])) {
            return $default;
        }
        //print_r($addresses);
        //die("ENDE");
        $addr = $addresses[0];
        // 3. Name und E-Mail extrahieren
        $name = !empty($addr->personal) ? $addr->personal : ($addr->mailbox ?? '');
        $email = ($addr->mailbox ?? 'unknown') . '@' . ($addr->host ?? 'unknown.local');
        return [
            'name'  => trim($name),
            'email' => strtolower(trim($email)) // E-Mail-Adressen sollten einheitlich sein
        ];
    }
    private function getFrameworkClient()
    {
        // Statische Variable, um die Verbindung innerhalb eines Requests zu halten
        static $client = null;
        if ($client !== null) {
            return $client;
        }
        $cm = new ClientManager();
        // Konfiguration aus deinen Umgebungsvariablen
        $client = $cm->make([
            'host'          => $this->systemProcess->getRequiredEnv('IMAP_HOST'),
            'port'          => 993,
            'encryption'    => 'ssl',
            'validate_cert' => false, // iCloud benötigt dies oft, da Zertifikate teilweise eigenwillig sind
            'username'      => $this->systemProcess->getRequiredEnv('IMAP_USER'),
            'password'      => $this->systemProcess->getRequiredEnv('IMAP_PASSWORD'),
            'protocol'      => 'imap'
        ]);
        $client->connect();

        return $client;
    }

    /**
     * Prüft, ob eine E-Mail schon existiert oder nicht
     */
    private function mail_exists($uid, $message_id, $folder)
    {
        $debugSQL = "SELECT Count(0) as Anzahl from mails 
                      WHERE tenant_id=:tenant_id 
                        AND imap_folder=:folder
                        AND (uid=:uid OR message_id=:messageid) ;";
        
        $params = [
            'folder'        => $folder,
            'uid'           => $uid,
            'messageid'    => $message_id
        ];

        $dbResult = $this->queryIt(
            $debugSQL,
            $params,
            false,
            false,
            false
        );

        return ($dbResult &&
            $dbResult->success &&
            !empty($dbResult->data->fetched) &&
            $dbResult->data->fetched[0]['Anzahl'] > 0);
    }

    /**
     * Diese Funktion decodiert MimeHeader
     */
    private function decodeMimeHeader(mixed $text): string
    {
        if (empty($text)) return '';
        if (!is_string($text)) $text = (string)$text;
        // Nutze die native IMAP-Funktion statt mb_decode_mimeheader
        $elements = imap_mime_header_decode($text);
        if (!$elements) {
            return $text;
        }
        $result = '';
        foreach ($elements as $element) {
            $charset = strtoupper($element->charset);
            $partText = $element->text;
            if ($charset === 'DEFAULT' || empty($charset)) {
                // Falls kein Charset angegeben ist, prüfen wir auf valides UTF-8
                if (!mb_check_encoding($partText, 'UTF-8')) {
                    $partText = mb_convert_encoding($partText, 'UTF-8', 'ISO-8859-1, Windows-1252, ASCII');
                }
            } else {
                // Konvertiere das vom Mailserver gemeldete Charset sauber nach UTF-8
                try {
                    $partText = mb_convert_encoding($partText, 'UTF-8', $charset);
                } catch (\Throwable $e) {
                    // Fallback bei völlig exotischen Charsets
                    $partText = mb_convert_encoding($partText, 'UTF-8', 'ISO-8859-1, Windows-1252');
                }
            }
            $result .= $partText;
        }
        return $result;
    }
    /**
     * Holt die letzte erfolgreich gesyncte UID für diesen Ordner.
     */
    private function getSyncCheckpoint(string $folder)
    {
        $dbResult = $this->queryIt(
            "SELECT last_uid FROM imap_sync_checkpoint WHERE tenant_id = :tenant_id AND imap_folder = :folder",
            ['folder' => $folder],
            false,
            false,
            false
        );

        return $dbResult;
    }

    /**
     * Speichert den Fortschritt.
     */
    private function saveSyncCheckpoint(string $folder, int $lastUid): bool
    {
        $tenantId = $_SESSION['tenant_id'] ?? null;
        if (!$tenantId) {
            error_log("Fehler: Kein tenant_id in der Session gefunden. Sync-Checkpoint kann nicht gespeichert werden.");
            return false;
        }
        $dbResult = $this->queryIt(
            "INSERT INTO imap_sync_checkpoint (tenant_id, imap_folder, last_uid) 
                        VALUES (:tenant_id, :folder, :lastUid) 
                        ON DUPLICATE KEY UPDATE last_uid = :lastUid",
            ['folder' => $folder, 'lastUid' => $lastUid],
            false,
            false,
            false
        );
        return (bool)$dbResult->success;
    }

    /**
     * Durchsucht die Mails in einem bestimmten Ordner nach einem Suchbegriff im Betreff oder Absender. Unterstützt Pagination für große Postfächer. Rückgabewert ist eine Liste von Mails mit Hash, entschlüsseltem Betreff, Sender, Datum und Paperless-Status.
     * @param string $folder Der IMAP-Ordner, in dem gesucht werden soll (z.B. "INBOX", "DMS")
     * @param string $search Der Suchbegriff, der im Betreff oder Absender gefunden werden soll
     * @param int $page Die Seite der Ergebnisse (1-basiert)
     * @param int $limit Anzahl der Mails pro Seite
     * @return array Eine Liste von Mails mit Hash, bereinigtem Betreff, Sender, Datum und Paperless-Status
     */
    public function searchMail(string $folder, string $search, int $page = 1, int $limit = 50): array
    {
        // 1. Offset mathematisch berechnen und als sicheren Integer erzwingen
        $limit = (int)$limit;
        if ($page < 1) {
            $page = 1;
        }
        if ($limit < 1) {
            $limit = 20; // Fällt zurück auf den Standard, falls 0 übergeben wurde
        }

        $offset = (int)(($page - 1) * $limit);

        // 2. Das SQL-Statement aufbauen
        // Da LIMIT und OFFSET im queryIt-Array oft als String übergeben würden (was MySQL crasht),
        // betten wir die vorab typisierten Zahlen direkt sicher in den String ein.
        $sql = "SELECT 
                    m.uid, 
                    HEX(AES_ENCRYPT(CAST(m.id AS CHAR), :crypto_key)) AS id, 
                    m.subject_hash AS sHash, 
                    m.date_received, 
                    m.paperless_id, 
                    m.is_read, 
                    m.is_flagged, 
                    m.is_answered, 
                    m.is_newsletter,
                    
                    -- Formatierter Absender aus SQL 2
                    IF(c_snd.display_name IS NOT NULL AND c_snd.display_name != '', 
                    CONCAT(c_snd.display_name, ' <', cm_snd.email, '>'), 
                    cm_snd.email) AS sender,
                    
                    cm_snd.email_hash AS emailhash,
                    
                    -- Formatierter Empfänger aus SQL 2
                    IF(c_rcp.display_name IS NOT NULL AND c_rcp.display_name != '', 
                    CONCAT(c_rcp.display_name, ' <', cm_rcp.email, '>'), 
                    cm_rcp.email) AS recipient,
                    
                    c_snd.display_name AS contact_name

                FROM mails AS m
                -- Absender-Joins
                LEFT JOIN contact_emails AS cm_snd ON cm_snd.id = m.sender_ceid
                LEFT JOIN contacts AS c_snd ON c_snd.id = cm_snd.contact_id

                -- Empfänger-Joins (aus SQL 2 übernommen)
                LEFT JOIN contact_emails AS cm_rcp ON cm_rcp.id = m.recipient_ceid
                LEFT JOIN contacts AS c_rcp ON c_rcp.id = cm_rcp.contact_id

                WHERE m.tenant_id = :tenant_id 
                AND m.imap_folder = :folder
                AND (m.status IN ('active') OR m.status IS NULL)
                -- Suchfilter aus SQL 1 beibehalten
                AND (
                    cm_snd.email LIKE :search 
                    OR c_snd.display_name LIKE :search
                )
                ORDER BY m.date_received DESC;";


        // 3. Ausführung über deine native queryIt-Methode
        $dbResult = $this->queryIt(
            $sql,
            [
                'folder' => $folder,
                'search' => '%' . $search . '%',
            ],
            false,
            false,
            false
        );

        $results = $dbResult->data->fetched;
        // Sicherstellen, dass immer ein Array zurückgegeben wird (falls queryIt im Leerzustand false/null liefert)
        // 2. Filter-Logik in PHP
        $filtered = [];
        $search = strtolower($search);
        foreach ($results as $row) {
            if (isset($row['hash'])) {
                $row['hash'] = bin2hex($row['hash']);
            }
            $subject = strtolower($this->dashboard->decrypt($row['sHash']));
            $contactName = strtolower($row['contact_name'] ?? '');
            $row['subject'] = $subject;
            $filtered[] = $row;
        }
        // 3. Paging auf dem gefilterten Array
        $offset = ($page - 1) * $limit;
        #error_log("DEBUG SLICE -> Gesamt im Array: " . count($filtered) . " | Aktuelle Seite: " . $page . " | Berechneter Offset: " . $offset . " | Limit: " . $limit);
        $ret = array_slice($filtered, $offset, $limit);
        return $ret;
    }

    /**
     * Emails per Hash einer Mail ermitteln
     */
    public function getMailsByAddress($hash)
    {
        $sql = "SELECT m.uid,
                    HEX(AES_ENCRYPT(CAST(m.id AS CHAR), :crypto_key)) AS id, 
                    m.subject_hash as sHash,
                    m.date_received,
                    m.paperless_id,
                    c.display_name as contact_name,
                    ce_all.email as contact_email
                FROM mails m
                -- 1. Finde den Kontakt, der zu diesem spezifischen Hash gehört
                JOIN contact_emails ce_target ON ce_target.email_hash = :hash
                -- 2. Finde alle E-Mail-Adressen, die zu DIESEM Kontakt gehören
                JOIN contact_emails ce_all ON ce_all.contact_id = ce_target.contact_id
                -- 3. Verknüpfe den Kontakt
                JOIN contacts c ON c.id = ce_target.contact_id
                -- 4. Hole alle Mails, bei denen der Sender eine der E-Mail-Adressen des Kontakts ist
                WHERE m.sender_ceid = ce_all.id
                AND m.tenant_id = :tenant_id
                ORDER BY m.date_received DESC";


        // 3. Ausführung über deine native queryIt-Methode
        $dbResult = $this->queryIt(
            $sql,
            [
                'hash' => $hash,
            ],
            false,
            false,
            true
        );

        if (!$dbResult->success) {
            return [];
        }

        $results = $dbResult->data->fetched;
        foreach ($results as &$row) {
            if (isset($row['hash'])) {
                $row['hash'] = bin2hex($row['hash']);
            }
            $subject = strtolower($this->dashboard->decrypt($row['sHash']));
            $contactName = strtolower($row['contact_name'] ?? '');
            $row['subject'] = $subject; // Zurückgeben als Klartext
            $row['contact_name'] = strtolower($row['contact_name'] ?? '');
            $row['date'] = !empty($row['date_received'])
                ? (new \DateTime($row['date_received']))->format('d.m.Y H:i')
                : '';
        }
        unset($row);

        return $results;
    }

    /**
     * 
     */
    public function getAccelorateClusters(int $weeks = 4, $unlimited = 0)
    {
        $sql = "WITH aggregated_mails AS (
                SELECT 
                    -- Welches E-Mail-Konto betrachten wir? (Sender bei Inbound, Recipient bei Outbound)
                    CASE WHEN v.imap_folder = 'Sent Messages' THEN v.recipient_email ELSE v.sender_email END AS sender,
                    v.contact_name AS showname,
                    v.imap_folder AS folder,
                    COUNT(v.mail_id) AS mail_count,
                    MAX(v.date_received) AS latest_received
                FROM vw_mails_resolved v
                WHERE v.tenant_id = :tenant_id
                AND v.status NOT IN ('deleted')
                AND (:weeks = 0 OR v.date_received >= NOW() - INTERVAL :weeks WEEK)
                AND (
                    (v.imap_folder IN ('INBOX') AND v.status = 'active')
                    OR
                    (v.imap_folder IN ('DMS', 'DMSDone') AND v.status IN ('active', 'dms', 'archived', 'DMSDone'))
                )
                AND v.sender_email NOT LIKE 'noreply%'
                AND v.sender_email NOT LIKE 'no-reply%'
                GROUP BY sender, showname, v.imap_folder
                HAVING mail_count > 1
            ),
            ranked_clusters AS (
                SELECT 
                    showname,
                    sender,
                    folder,
                    mail_count,
                    latest_received,
                    ROW_NUMBER() OVER (PARTITION BY folder ORDER BY latest_received DESC) AS rank_num
                FROM aggregated_mails
            )
            SELECT showname, sender, folder, mail_count, latest_received
            FROM ranked_clusters
            WHERE rank_num <= 5
            ORDER BY folder, latest_received DESC";

        $a = $this->queryIt(
            $sql, 
            ['weeks' => $weeks], 
            false, 
            false, 
            false
        );

        return $a;
    }

    public function getMailsThatShouldBeArchived()
    {
        $sql =" WITH contact_group_emails AS (
                    SELECT DISTINCT ce_all.email
                    FROM vw_mails_resolved v_target
                    INNER JOIN contact_emails ce_all ON ce_all.contact_id = v_target.sender_contact_id
                    WHERE v_target.tenant_id = :tenant_id
                    AND v_target.imap_folder IN ('DMS', 'DMSDone')
                ),
                dms_senders AS (
                    SELECT email AS sender_email FROM contact_group_emails
                )
                SELECT 
                    v.sender_email AS sender,
                    v.contact_name AS showname,
                    COUNT(v.mail_id) AS unarchived_count,
                    MAX(v.date_received) AS latest_received
                FROM vw_mails_resolved v
                INNER JOIN dms_senders ds ON ds.sender_email = v.sender_email
                WHERE v.tenant_id = :tenant_id
                AND v.imap_folder = 'INBOX'
                AND v.status = 'active'
                AND NOT EXISTS (
                    SELECT 1 
                    FROM vw_mails_resolved v_dms 
                    WHERE v_dms.tenant_id = v.tenant_id
                        AND v_dms.sender_email = v.sender_email 
                        AND v_dms.imap_folder IN ('DMS', 'DMSDone')
                )
                GROUP BY v.sender_email, v.contact_name
                ORDER BY unarchived_count DESC, latest_received DESC;";

        $dbResult = $this->queryIt(
            $sql,
            [],
            false,
            false,
            true
        );

        return ($dbResult->success && !empty($dbResult->data) && !empty($dbResult->data->fetched)) ?
            $dbResult->data->fetched :
            [];
    }

    public function getMailsThatShouldBeAnswered(int $weeks = 4)
    {
        $sql =" SELECT 
                    v.contact_id,
                    v.contact_name,
                    CAST(
                        SUBSTRING_INDEX(
                            GROUP_CONCAT(v.mail_id ORDER BY v.date_received DESC, v.mail_id DESC), 
                            ',', 1
                        ) AS UNSIGNED
                    ) AS latest_mail_id,

                    GROUP_CONCAT(DISTINCT v.sender_email SEPARATOR ', ') AS associated_emails,
                    COUNT(v.mail_id) AS unanswered_count,
                    MAX(v.date_received) AS latest_unanswered_date,
                    r.response_rate_pct
                FROM vw_mails_resolved v
                INNER JOIN vw_contact_response_rates r 
                    ON r.contact_id = v.contact_id AND r.tenant_id = v.tenant_id
                WHERE v.tenant_id = :tenant_id
                AND v.status = 'active'
                AND v.is_answered = 0
                AND v.is_newsletter = 0
                AND (:weeks = 0 OR v.date_received >= NOW() - INTERVAL :weeks WEEK)
                AND r.response_rate_pct >= 30.0
                AND r.total_mails >= 2
                GROUP BY v.contact_id, v.contact_name, r.response_rate_pct
                HAVING unanswered_count <= 15
                ORDER BY latest_unanswered_date DESC, r.response_rate_pct DESC";

        
        // 3. Ausführung über deine native queryIt-Methode
        $dbResult = $this->queryIt(
            $sql,
            ['weeks' => $weeks],
            false,
            false,
            false
        );

        return ($dbResult->success && !empty($dbResult->data) && !empty($dbResult->data->fetched)) ?
            $dbResult->data->fetched :
            [];

    }

    

    /**
     * Prüft unfertige lokale Mails gegen die realen IMAP-Ordner.
     * Nutzt die Message-ID für ordnerübergreifendes Tracking.
     */
    public function trackAndSyncFolderStatus(): array
    {
        // 1. Alle Mails holen, die lokal noch aktiv sind (mit Mandanten-Schutz)
        $localMails = $this->queryIt(
            "SELECT HEX(AES_ENCRYPT(CAST(m.id AS CHAR), :crypto_key)) AS id,  uid, message_id, imap_folder FROM mails as m WHERE tenant_id = :tenant_id AND imap_folder != 'DMSDone'"
        );
        if (empty($localMails)) {
            return ['success' => true, 'updated' => 0];
        }
        // 2. IMAP-Verbindung aufbauen
        try {
            $imapStream = $this->connectIMAP();
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'IMAP Verbindung fehlgeschlagen'];
        }

        $targetFolders = $this->systemProcess->getRequiredEnv('SYNC_FOLDERS');
        $baseConnString = preg_replace('/\}.*$/', '}', $this->systemProcess->getRequiredEnv('IMAP_HOST'));
        $results = [];
        // 3. Nur den aktuellen Live-Status ermitteln, OHNE in die DB zu schreiben
        foreach ($localMails as $mail) {
            $msgId = $mail['message_id'];
            if (empty($msgId)) continue;
            //$currentFolder = 'INBOX'; // Fallback, falls nirgends mehr auffindbar
            $currentUid = 0;
            foreach ($targetFolders as $folder) {
                $fullFolderString = $baseConnString . $folder;

                error_log("IMAP Folder Sync: " . $fullFolderString);
                if (@imap_reopen($imapStream, $fullFolderString)) {
                    $searchRes = imap_search($imapStream, 'HEADER Message-ID "' . $msgId . '"');
                    if (!empty($searchRes)) {
                        $currentFolder = $folder;
                        $currentUid = imap_uid($imapStream, $searchRes[0]);
                        break;
                    }
                }
            }
            // Wir sammeln den Live-Status einfach für das Frontend, falls es ihn braucht
            $results[$mail['id']] = [
                'folder' => $currentFolder,
                'uid'    => (int)$currentUid
            ];
        }
        return [
            'success' => true,
            'live_status' => $results
        ];
    }

    /**
     * Entschlüsselt den Mail-Inhalt für das Iframe
     */
    public function getDecryptedMailBody(string $id): ?string
    {
        error_log(__FUNCTION__);
        $sql = "SELECT mb.body_encrypted 
            FROM mail_bodies AS mb 
            INNER JOIN mails AS m ON m.id = mb.mail_id 
            WHERE m.tenant_id = :tenant_id
            AND m.id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED);";

        $dbResult = $this->queryIt($sql, [
            'id' => $id
        ]);
        if (!$dbResult->success && !empty($dbResult->data->fetched)) {
            return "Mail Body leer";
        }
        $rows = $dbResult->data->fetched;

        if (empty($rows) || empty($rows[0]['body_encrypted'])) return null;
        // 1. Daten dekodieren
        $decoded = base64_decode($rows[0]['body_encrypted']);

        // 2. IV-Länge für AES-256-CBC bestimmen (üblicherweise 16 Bytes)
        $ivSize = openssl_cipher_iv_length('AES-256-CBC');

        // 3. IV und verschlüsselte Daten trennen
        $iv = substr($decoded, 0, $ivSize);
        $data = substr($decoded, $ivSize);

        // 4. Schlüssel ableiten
        $encryption_key = defined('HASH_SALT') ? HASH_SALT : 'fallback_key_32_chars_long!!';
        $key = hash('sha256', $encryption_key, true);
        // 5. Entschlüsseln mit OPENSSL_RAW_DATA
        return openssl_decrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Holt den Body einer Mail. Falls noch nicht in der DB vorhanden (Lazy Loading),
     * wird er live via IMAP von iCloud nachgeladen, verschlüsselt gecached und zurückgegeben.
     * * @param string $id Der Hash (oder ID) der Mail aus der lokalen DB
     * @return string Der bereinigte, lesbare UTF-8 Body der Mail
     * @throws Exception Wenn die Mail nicht existiert oder IMAP fehlschlägt
     */
    public function getMailBody($id): string
    {
        error_log("getMailBody: " . $id);
        $mail = $this->getMailById($id);
        if (!$mail) return 'Inhalt nicht gefunden.';

        // 1. Erst versuchen, aus der DB zu laden (entschlüsselt)
        $htmlContent = ""; 
        $thmlfromDB = $this->getDecryptedMailBody($id);
        $htmlContent ="";

        // 2. Wenn DB leer, IMAP anzapfen
        if (empty($htmlContent)) {
            $htmlContent = $this->getHtmlBody($id);

            if (empty($htmlContent)) {
                // Fallback: Wenn kein HTML, versuche PlainText
                $htmlContent = $this->getTxtBody($id);
            }

            if (empty($htmlContent)) {
                throw new \Exception("Kein Inhalt auf IMAP gefunden.");
            }

            // Jetzt in die DB schreiben (Sicherheit vor Speichern!)
            //$this->saveMailWithContent($id, $htmlContent, strip_tags($htmlContent));
        }

        
        // Sicherheit: Erst hier wird das HTML für die Anzeige/Ausgabe "geputzt"
        return $this->purifyHtml($htmlContent);
        
        
    }
    private function purifyHtml(string $html): string
    {
        // Caching-Ordner für Performance (sehr wichtig bei vielen Mails!)
        $cacheDir = '/var/www/html/age_web/storage/purifier_cache';

        // Konfiguration erstellen
        $config = \HTMLPurifier_Config::createDefault();

        // Setze Cache-Verzeichnis (muss für den Webserver beschreibbar sein)
        if (is_writable(dirname($cacheDir))) {
            $config->set('Cache.SerializerPath', $cacheDir);
        }

        // Erlaube grundlegende Formatierung
        $config->set('HTML.Allowed', 'p,br,b,strong,i,em,u,a[href|title],ul,ol,li,blockquote,pre,code,h1,h2,h3,h4,img[src|alt|title|width|height]');

        // Sicherheit: Verhindere, dass Links in neuen Fenstern ohne Noopener öffnen
        $config->set('Attr.AllowedFrameTargets', []);

        $purifier = new \HTMLPurifier($config);

        return $purifier->purify($html);
    }


    private function getIMAPFolderByHash(&$id)
    {
        $sql = "SELECT imap_folder FROM mails WHERE tenant_id=:tenant_id AND id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED);";
        $dbResult = $this->queryIt(
            $sql,
            ["id" => $id]
        );
        if (!$dbResult->success) {
            return "";
        } else {
            return $dbResult->data->fetched[0]['imap_folder'];
        }
    }

    /**
     * Email und Kontakt für TimeLine
     */
    public function getMailItems(string $id)
    {
        error_log(__FUNCTION__);

        $sql = "SELECT CASE 
                        WHEN m.recipient_ceid = ce.id THEN 'mail-send'
                        ELSE 'mail'
                    END AS type,
                    HEX(AES_ENCRYPT(CAST(m.id AS CHAR), :crypto_key)) AS id,
                    m.paperless_id AS paperlessdocument,
                    m.date_received AS mdate,
                    m.subject_hash AS title
                    FROM mails m
                    -- Join, um alle ceids zu finden, die zum Kontakt der Ausgangs-Mail gehören
                    JOIN contact_emails ce ON (m.sender_ceid = ce.id OR m.recipient_ceid = ce.id)
                    WHERE ce.contact_id IN (
                        -- Subquery: Finde den Kontakt der E-Mail-Adresse
                        SELECT contact_id 
                        FROM contact_emails 
                        WHERE id = (
                            -- Subquery: Je nach Ordner sender_ceid (Eingang) oder recipient_ceid (Ausgang) wählen
                            SELECT 
                                CASE 
                                    WHEN UPPER(m_base.imap_folder) IN ('OUTBOX', 'SENT', 'GESENDET', 'SENT MESSAGES') THEN m_base.recipient_ceid
                                    ELSE m_base.sender_ceid
                                END
                            FROM mails m_base
                            WHERE m_base.id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED)
                        )
                    )
                    AND m.tenant_id = :tenant_id 
                    AND m.status != 'deleted'
                    ORDER BY m.date_received DESC
                    LIMIT 1000;"; // Dein SQL von oben

        $dbResult = $this->queryIt(
            $sql,
            [
                "id" => $id
            ],
            false,
            false,
            false
        );

        return ($dbResult->success && !empty($dbResult->data) && !empty($dbResult->data->fetched)) ?
            $dbResult->data->fetched :
            [];
    }

    /**
     * Alle Mails vom Absender löschen
     */
    public function deleteAllMailsByHash($id)
    {
        $sql = "SELECT HEX(AES_ENCRYPT(CAST(m.id AS CHAR), :crypto_key)) AS id, imap_folder, uid, sender_ceid 
                  FROM mails as m
                  WHERE 
                    tenant_id=:tenant_id
                    AND sender_ceid IN (
                        SELECT sender_ceid FROM mails WHERE id=CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED)
                    );";

        $dbResult = $this->queryIt(
            $sql,
            ['id' => $id],
            false,
            false,
            false
        );

        if (!$dbResult->success || empty($dbResult->data->fetched)) {
            // Keine Mails gefunden oder Fehler -> Abbruch
            throw new \Exception("Keine Mails gefunden oder Fehler in der Abfrage!");
        }

        $senderCeid = $dbResult->data->fetched[0]['sender_ceid'];
        $mailsByFolder = [];
        foreach ($dbResult->data->fetched as $row) {
            if (!empty($row['uid']) && !empty($row['imap_folder'])) {
                $mailsByFolder[$row['imap_folder']][] = $row['uid'];
            }
        }

        $imapStream = $this->connectIMAP();
        $destFolder = "Deleted Messages";

        if (!empty($mailsByFolder)) {
            
            $host = $this->systemProcess->getRequiredEnv('IMAP_HOST');
            $port = $this->systemProcess->getRequiredEnv('IMAP_PORT');
            $ssl  = ($port == 993) ? '/imap/ssl' : '';
            $imapServerBase = "{" . $host . ":" . $port . $ssl . "}";

            // 3. IMAP-Schleife: Wir gehen Ordner für Ordner durch
            foreach ($mailsByFolder as $folderName => $uids) {
                
                // Verbindung ohne Neu-Authentifizierung auf den nächsten Ordner umbiegen
                $fullMailboxPath = $imapServerBase . $folderName;
                
                // Bestehenden Stream ohne Re-Authentifizierung auf den neuen Ordner umbiegen
                if (!imap_reopen($imapStream, $fullMailboxPath)) {
                    throw new \Exception("Konnte Ordner $fullMailboxPath nicht öffnen: " . imap_last_error());
                }
                
                // Alle UIDs dieses Ordners als kommagetrennte Liste (Bulk-Operation)
                $sequence = implode(',', $uids);
                
                $moveSuccess = imap_mail_move($imapStream, $sequence, $destFolder, CP_UID);
                if (!$moveSuccess) {
                    throw new \Exception("IMAP-Move aus $folderName nach $destFolder fehlgeschlagen: " . imap_last_error($imapStream));
                }

                // Löschung im aktuellen Ordner finalisieren
                imap_expunge($imapStream);
                error_log("Mail $sequence gelöscht.");
            }

            // 4. DATENBANK UPDATE: Alle betroffenen Mails auf einmal als gelöscht markieren
            $sqlUpdate = "UPDATE mails 
                        SET status = 'deleted' 
                        WHERE sender_ceid = :senderceid AND tenant_id=:tenant_id";
                        
            return $this->queryIt($sqlUpdate, ['senderceid' => $senderCeid], false, false, true);
        }
    }

    /**
     * Löscht eine Mail anhand ihres Hashes. WICHTIG: Diese Methode sollte mit Vorsicht verwendet werden, da sie die Mail endgültig entfernt. Es wird empfohlen, stattdessen moveToDMS zu verwenden, um die Mail zu archivieren.
     * @param string $hash Der Hash der Mail, die gelöscht werden soll.
     */
    public function deleteMailByHash($hash)
    {
        // Verbindung sicherstellen (nutzt die private connect Methode)
        $imapStream = $this->connectIMAP();
        $mail = $this->getMailById($hash);
        $msgUid = $mail['uid'];
        $destFolder = "Deleted Messages"; // Oder dynamisch basierend auf $targetFolder
        // Verschieben via UID
        $moveSuccess = imap_mail_move($imapStream, $msgUid, $destFolder, CP_UID);
        if (!$moveSuccess) {
            throw new \Exception("IMAP-Move nach $destFolder fehlgeschlagen: " . imap_last_error());
        }

        // Wichtig, um die Mail aus der INBOX zu löschen
        imap_expunge($imapStream);
        $res = $this->queryIt(
            "UPDATE mails Set status='deleted' WHERE tenant_id = :tenant_id AND id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED)",
            [
                'hash' => $hash
            ]
        );
        imap_close($imapStream);
        return true;
    }
    
    /**
     * Holt eine paginierte Liste von Mails aus der Datenbank, basierend auf dem angegebenen Ordner. Es werden nur Mails zurückgegeben, die nicht als 'deleted' markiert sind. Der Betreff wird entschlüsselt und sowohl Betreff als auch Sender werden mit mb_convert_encoding bereinigt, um sicherzustellen, dass sie korrekt im Dashboard angezeigt werden, selbst wenn sie ursprünglich in einem anderen Zeichensatz vorlagen.
     * @param int $page Die Seite der Ergebnisse (1-basiert)
     * @param int $limit Anzahl der Mails pro Seite
     * @param string $folder Der IMAP-Ordner, aus dem die Mails gefiltert werden sollen (z.B. 'INBOX', 'DMS')
     * @return array Eine Liste von Mails mit Hash, bereinigtem Betreff, bereinigtem Sender, Datum und Paperless-Status
     */
    public function getMailsPaginated(int $page, int $limit, string $folder, string $search = ''): array
    {
        $offset = ($page - 1) * $limit;
        $search = trim($search);

        // 1. SQL und Parameter generieren
        [$sql, $params] = $this->buildSearchSQLAndParams($folder, $search, $limit, $offset);

        // 2. Datenbankabfrage
        $dbResult = $this->queryIt($sql, $params, false, false, false);

        if (!$dbResult->success) {
            throw new \Exception("Datenbankfehler: " . ($dbResult->error ?? 'Unbekannter Fehler'));
        }

        // 3. Daten verarbeiten (Nur nicht-null Ergebnisse behalten)
        $mails = $dbResult->data->fetched ?? [];
        $returnMails = [];

        foreach ($mails as $row) {
            if ($prepared = $this->prepareMailRow($row, $search)) {
                $returnMails[] = $prepared;
            }
        }

        error_log(sprintf("Mails geladen: %d in Ordner %s", count($returnMails), $folder));

        return $returnMails;
    }
    /**
     * Baut das dynamische SQL-Statement und die zugehörigen PDO-Parameter.
     */
    private function buildSearchSQLAndParams(string $folder, string $search, int $limit, int $offset, ?string $targetHash = null): array
    {
        error_log("buildSearchSQLAndParams: folder=$folder, search=$search, limit=$limit, offset=$offset, targetHash=$targetHash");

        //$salt = defined('HASH_SALT') ? HASH_SALT : 'default_fallback_salt';

        $params = [
            'folder' => $folder,
            'limit'  => (int)$limit,
            'offset' => (int)$offset,
        ];

        $sql = "SELECT 
                    HEX(AES_ENCRYPT(CAST(m.id AS CHAR), :crypto_key)) AS id,
                    uid, m.recipient_ceid, subject_hash, m.paperless_id, imap_folder,
                    date_received as date,
                    is_read, is_flagged, is_answered, is_newsletter,
                    -- 1. SENDER (Absender) sauber zusammenbauen mit Fallback, falls kein Name da ist
                    IF(c_snd.display_name IS NOT NULL AND c_snd.display_name != '', 
                    CONCAT(c_snd.display_name, ' <', cm_snd.email, '>'), 
                    cm_snd.email) AS sender,
                    cm_snd.email_hash AS emailhash, -- Für deine Gravatar-/Sidebar-Logik
                    
                    -- 2. RECIPIENT (Empfänger) exakt genau so auflösen
                    IF(c_rcp.display_name IS NOT NULL AND c_rcp.display_name != '', 
                    CONCAT(c_rcp.display_name, ' <', cm_rcp.email, '>'), 
                    cm_rcp.email) AS recipient
                FROM mails as m
                LEFT JOIN mail_bodies as mb on mb.mail_id = m.id
                -- Doppelte Join-Struktur: Einmal für den Sender...
                LEFT JOIN contact_emails AS cm_snd ON cm_snd.id = m.sender_ceid
                LEFT JOIN contacts AS c_snd ON c_snd.id = cm_snd.contact_id

                -- ...und einmal exakt das Gleiche für den Empfänger
                LEFT JOIN contact_emails AS cm_rcp ON cm_rcp.id = m.recipient_ceid
                LEFT JOIN contacts AS c_rcp ON c_rcp.id = cm_rcp.contact_id
                
                WHERE m.tenant_id = :tenant_id
                AND imap_folder = :folder
                AND (status IN('active') OR status IS NULL)";

        // Fall A: Zugriff auf eine spezifische Mail (Detailansicht)
        if ($targetHash !== null) {
            $sql .= " AND id_hash = :hash";
            $params['hash'] = $targetHash;
        }

        // Fall B: Suche in einer Liste (Übersicht)
        elseif (!empty($search)) {
            $sql .= " AND (";
            $searchNormalized = $this->normalizeString($search);
            $hashedSearch = substr(hash('sha256', $searchNormalized . $salt), 0, 12);
            $sql .= "LOWER(cm_snd.email) LIKE :like_q OR LOWER(cm_rcp.email) LIKE :like_q OR mb.body_search_index LIKE :hash_q";
            $params['like_q'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $params['hash_q'] = '%' . $hashedSearch . '%';

            if (preg_match('/[0-9.\-:]/', $search)) {
                $params['search_date'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
                $sql .= " OR date_received LIKE :search_date";
            }
            if (is_numeric($search)) {
                $params['search_id'] = intval($search);
                $sql .= " OR id = :search_id";
            }
            $sql .= ")";
        }
        $sql .= " ORDER BY date_received DESC LIMIT :limit OFFSET :offset";

        return [$sql, $params];
    }


    /**
     * Entschlüsselt, bereinigt und filtert eine einzelne Tabellenzeile.
     * Gibt das fertige Array zurück oder null, wenn der Filter nicht matcht.
     */
    private function prepareMailRow(array $row, string $search): ?array
    {
        // Betreff entschlüsseln
        $decryptedSubject = $this->dashboard->decrypt($row['subject_hash']);
        // UTF-8 Absicherung
        $cleanSubject = mb_convert_encoding($decryptedSubject, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        //$cleanSender  = mb_convert_encoding($row['sender'], 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        //$cleanRecipient    = mb_convert_encoding($row['recipient'], 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

        // Zusätzlicher PHP-Filter bei aktiver Suche
        if (!empty($search)) {
            $searchLower  = mb_strtolower($search, 'UTF-8');
            $subjectLower = mb_strtolower($cleanSubject, 'UTF-8');
            $dateLower    = mb_strtolower($row['date'], 'UTF-8');
            $idLower      = mb_strtolower($row['id'], 'UTF-8');
            $matchesMeta = (
                mb_strpos($subjectLower, $searchLower) !== false ||
                //mb_strpos($senderLower, $searchLower) !== false ||
                mb_strpos($dateLower, $searchLower) !== false ||
                mb_strpos($idLower, $searchLower) !== false
            );
            // Wenn es weder ein Metadaten-Treffer noch ein DB-Inhalts-Treffer ist -> aussortieren
            if (!$matchesMeta && empty($row['id'])) {
                return null;
            }
        }

        // Rückgabe-Format für das Frontend
        return [
            'id'         => $row['id'],
            'uid'          => $row['uid'] ?? null,
            'subject'      => $cleanSubject,
            'sender'       => $row['sender'],
            'recipient'    => $row['recipient'],
            'date'         => date('d.m.Y H:i', strtotime($row['date'])),
            'paperless_id' => $row['paperless_id'],
            'is_read'       => $row['is_read'],
            'is_flagged'      => $row['is_flagged'],
            'is_answered'     => $row['is_answered'],
            'is_newsletter'     => $row['is_newsletter'],
            //'displayname'  => $row['display_name'],
            'emailhash'    => $row['emailhash']
        ];
    
    }

    
    
    /**
     * Aktualisiert eine bestehende Mail mit dem verschlüsselten Inhalt 
     * und dem kryptografischen Suchindex anhand ihres Hashes.
     * * @param string $hash Der SHA-256 Hash der Mail (id + salt)
     * @param string $htmlBody Der volle HTML-Inhalt der Mail
     * @param string $plainText Der reine Textinhalt der Mail für den Index
     * @return void
     */
    public function saveMailWithContent(string $id, string $htmlBody, string $plainText): void
    {
        // 1. Erst die ID zur Hash-Referenz finden
        $sql = "SELECT HEX(AES_ENCRYPT(CAST(m.id AS CHAR), :crypto_key)) AS id FROM mails WHERE id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED) LIMIT 1";
        //$result = $this->queryIt($sql, ['hash' => $hash]);

        $dbResult = $this->queryIt(
            $sql,
            ['id' => $id],
            false,
            false,
            false
        );
        if (!$dbResult->success) {
            //$this->dashboard->sendResponse(false, "", "Keine Mail gefunden für diesen Hash, kann body nicht speichern.");
            throw new \Exception("Keine Mail gefunden für diesen Hash, kann body nicht speichern. $id");
        }
        $mailId = $dbResult->data->fetched[0]['id'];
        // 1. HTML Sanitization (Sicherheits-Layer)
        $cleanHTML = $this->sanitizeEmailBody($htmlBody);

        // 2. Verschlüsselung der bereinigten Daten
        $encryptedBody = $this->encryptContent($cleanHTML);
        // 2. Daten in mail_bodies einfügen oder aktualisieren
        // Wir nutzen hier INSERT ... ON DUPLICATE KEY UPDATE
        $sqlBody = "INSERT INTO mail_bodies (mail_id, body_encrypted, body_search_index, body_hash) 
                    VALUES (:mail_id, :encrypted_body, :search_index, :body_hash)
                    ON DUPLICATE KEY UPDATE 
                    body_encrypted = :encrypted_body,
                    body_search_index = :search_index,
                    body_hash = :body_hash";
        $this->queryIt($sqlBody, [
            'mail_id'          => $mailId,
            'encrypted_body'   => $encryptedBody,
            'search_index'     => $this->buildSearchIndex($plainText),
            'body_hash'        => $this->calculateBodyHash($plainText) // Falls du einen Body-Hash nutzt
        ], true);
    }
    private function calculateBodyHash(string $plainText): string
    {
        // Wir normalisieren den Text vorher leicht (Trimmen), 
        // um bei identischen Inhalten mit unterschiedlichen Leerzeichen 
        // denselben Hash zu erhalten.
        $hexHash = hash('sha256', trim($plainText));
        return hex2bin($hexHash);
    }
    private function sanitizeEmailBody($rawHtml)
    {
        $config = \HTMLPurifier_Config::createDefault();

        // Konfiguration für E-Mails:
        // Erlaube Bilder, aber keine gefährlichen Attribute
        $config->set('HTML.Allowed', 'p,b,i,u,br,a[href],table,tr,td,img[src|alt]');

        $purifier = new \HTMLPurifier($config);
        return $purifier->purify($rawHtml);
    }
    /**
     * Erstellt eine standardisierte Wortliste für den Suchindex
     */
    public function buildSearchIndex(string $plainText): string
    {
        // 1. Text normalisieren (Umlaute weg)
        $text = $this->normalizeString($plainText);

        // 2. Nur noch Standard-Buchstaben und Zahlen erlauben
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);

        $words = array_filter(explode(' ', $text), function ($word) {
            return strlen($word) > 2;
        });

        $salt = defined('HASH_SALT') ? HASH_SALT : '';
        $hashedWords = array_map(function ($word) use ($salt) {
            return substr(hash('sha256', $word . $salt), 0, 12);
        }, array_unique($words));
        return implode(' ', $hashedWords);
    }
    /**
     * Normalisiert Umlaute und Sonderzeichen für eine unfehlbare Hash-Suche
     */
    private function normalizeString(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        // Umlaute und scharfes S radikal ersetzen
        $replacements = [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss'
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
