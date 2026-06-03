<?php
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

    public function __construct($dashboard) {
        parent::__construct($dashboard);
        $this->dashboard = $dashboard;
    }

    
    /**
     * Destruktor: Schließt die Verbindung sauber.
     */
    public function __destruct() {
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
        
    

        if($folderPath!==null)
        {
            $connectionString = "{" . $host . ":" . $port . $ssl . "}" . $folderPath;
        }else{
            $connectionString = "{" . $host . ":" . $port . $ssl . "}";
        }
        
        error_log("connectionstring: ". $connectionString);
        
        $stream = @imap_open($connectionString, $user, $pw, OP_SILENT, 1);

        if (!$stream) {
            throw new \Exception("IMAP-Verbindung fehlgeschlagen: " . imap_last_error());
        }
        
        return $stream;
    }

    /**
     * Verschlüsselt den Mail-Inhalt via PHP (AES-256-CBC)
     */
    private function encryptContent(string $data): string {
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
    public function getMailByHash($hash) 
    {
        error_log("## " . __FUNCTION__ . " ##");
        $sql = "SELECT * FROM mails 
                WHERE 
                    tenant_id=:tenant_id
                    AND id_hash = UNHEX(:hash)
                    AND (status NOT IN('deleted') OR status IS NULL) 
                    LIMIT 1";

        $dbResult = $this->queryIt(
            $sql, 
            ['hash' => $hash],
            false,
            false,
            false
        );

        return ($dbResult->success && !empty($dbResult->data) && !empty($dbResult->data->fetched)) 
            ?  $dbResult->data->fetched[0]
            : null;
        

    }

    /**
     * Holt den HTML-Inhalt einer E-Mail
     * @param resource $imapStream Die aktive IMAP-Verbindung
     * @param int $msgNumber Die Nummer der Mail im Postfach
     * @return string HTML-Inhalt oder leerer String
     */
    public function getHtmlBody($hash, $folder) 
    {
        error_log("## " . __FUNCTION__ . " mit $hash aufgerufen ##");
        $mail = $this->getMailByHash($hash);


        if (empty($mail) || !isset($mail['uid']) || (int)$mail['uid'] <= 0) {
            error_log("NO MAIL OR UID");
            $this->dashboard->sendResponse(false, "", "Ungültige Mail-Referenz oder UID fehlt in der lokalen Datenbank.");
            return false;
        }
        
        
        $uid = (int)$mail['uid'];
        $imapStream = $this->connectIMAP($folder);

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
            
            return $purifier->purify($htmlContent);
        } catch (\Throwable $e) {
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
    private function getFullFolderPath(string $folder):string
    {
        $imapPath = "{" . $this->systemProcess->getRequiredEnv('IMAP_HOST') . ":" . $this->systemProcess->getRequiredEnv('IMAP_PORT'). "/imap/ssl}";

        error_log($imapPath . " FOLDER: $folder");
        if ($folder !== "INBOX")
        {
            $finalPath = $imapPath .= $folder;
            error_log($finalPath);
            return $finalPath;
        }

        error_log($imapPath);

        return $imapPath;
    }


    /**
     * Holt den reinen Text-Inhalt einer E-Mail (Fallback)
     */
    public function getTxtBody($hash, &$folder) 
    {
        error_log("## " . __FUNCTION__ . " ##");
        try {
            $imapStream = $this->connectIMAP($folder);
            $mail = $this->getMailByHash($hash);
            
            $msgNumber = imap_msgno($imapStream, $mail['uid']);
            if (!$msgNumber) {
                return "Mail existiert nicht mehr auf dem Server.";
            }

            $structure = imap_fetchstructure($imapStream, $msgNumber);
            $plainText = $this->findPart($msgNumber, $structure, 'TEXT/PLAIN', $imapStream);
            
            if (!$plainText) {
                return "<i>(Kein lesbarer Textinhalt gefunden)</i>";
            }

            $cleanText = mb_convert_encoding($plainText, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            return nl2br(htmlspecialchars($cleanText));

        }catch (\Throwable $e) {
            // Reicht den Fehler mit Kontext nach oben an getMails2Paperless weiter
            throw new \Exception("Fehler beim Lesen des Mail-Inhalts: " . $e->getMessage());
        }
    }

    /**
     * Interne Hilfsfunktion: Durchsucht die Mail-Struktur rekursiv nach dem gewünschten MIME-Type
     */
    private function findPart($uid, $structure, $mimeType, &$imapStream, $partNumber = null) 
    {
        try{
            error_log("## " . __FUNCTION__ . " aufgerufen mit UID: $uid, Typ: " . ($structure->type ?? 'n/a') . ", MIME: $mimeType ##");            // Basis-MIME-Typ bestimmen
            // 1. Sicherer Zugriff auf den Typ
            $type = isset($structure->type) ? $structure->type : 0;
            
            // 2. MIME-Typ Logik (kein Array-Zugriff auf $structure!)
            $primaryTypes = ["TEXT", "MULTIPART", "MESSAGE", "APPLICATION", "AUDIO", "IMAGE", "VIDEO", "OTHER"];
            $primary = $primaryTypes[$type] ?? "TEXT";
            $subtype = isset($structure->subtype) ? strtoupper($structure->subtype) : "PLAIN";
            $currentMimeType = $primary . '/' . $subtype;

            if ($currentMimeType === $mimeType) {
                $part = $partNumber ?: "1";
                $data = imap_fetchbody($imapStream, $uid, $part, FT_UID);
                
                $encoding = isset($structure->encoding) ? $structure->encoding : 0;
                return ($encoding == 3) ? base64_decode($data) : quoted_printable_decode($data);
            }

            // 3. WICHTIG: Hier liegt oft der Fehler!
            // Wir prüfen, ob $structure->parts ein Array ist, bevor wir es in eine Schleife schicken.
            if ($type == 1 && isset($structure->parts) && is_array($structure->parts)) {
                foreach ($structure->parts as $index => $sub) {
                    $newPart = $partNumber ? $partNumber . '.' . ($index + 1) : (string)($index + 1);
                    $found = $this->findPart($uid, $sub, $mimeType, $imapStream, $newPart);
                    if ($found) return $found;
                }
            }
            return null;
        }catch(\Throwable $e) {
            error_log(__FUNCTION__ . ": ERROR: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());        }
    }

    /**
     * Verschiebt eine Mail in den DMS-Ordner. Wichtig: Diese Methode sollte erst nach erfolgreicher PDF-Erstellung aufgerufen werden, um Datenverlust zu vermeiden.
     * @param string $hash Der Hash der Mail, die verschoben werden soll.
     * @param string $targetFolder Optional: Zielordner im DMS, Standard ist "Recht". Der tatsächliche IMAP-Ordner könnte z.B. "INBOX/DMS" sein, abhängig von der Serverstruktur.
     */
    public function moveToDMS($hash, $targetFolder = 'DMS') 
    {
        try {
            // Verbindung sicherstellen (nutzt die private connect Methode)
            $this->connectIMAP();

            $mail = $this->getMailByHash($hash);

            
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
                    AND id_hash = :hash", 
                ['hash' => hash('sha256', $messageId . $salt, true)]);

            return [
                'success' => true,
                'message' => "Erfolgreich nach $destFolder verschoben."
            ];

        } catch (Throwable $e) {
            throw new \Exception("Fehler in moveToDMS: " . $e->getMessage());
        }
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

    public function syncWithICloud(int $maxNewMailsLimit, string $folder): array 
    {
        
        //@set_time_limit(120);
        $pid = getmypid();
        error_log("[$pid] Starte Sync für Ordner: $folder");

        $added = 0;
        $result = ['success' => false, 'added' => 0, 'error' => null];

        try {
            
            $imapFolder = $this->getFullFolderPath($folder);
            $imapStream = $this->connectIMAP($folder);

            // 1. Hole alle UIDs sortiert vom Server - VOR dem Debugging!
            $allUids = imap_sort($imapStream, SORTARRIVAL, 0, SE_UID);
            $lastUid = $this->getSyncCheckpoint($folder);

            // DEBUG-BLOCK (Jetzt mit Werten)
            $maxServerUid = !empty($allUids) ? max($allUids) : 0;
            error_log("DEBUG-SYNC: Gefundene Mails im Ordner ($folder): " . count($allUids) . " | Letzte UID in DB: $lastUid | Höchste UID Server: $maxServerUid");

            // 2. Filtern
            $newUids = array_filter($allUids, function($uid) use ($lastUid) {
                return (int)$uid > (int)$lastUid;
            });
            
            sort($newUids); 
            $uidsToProcess = array_slice($newUids, 0, $maxNewMailsLimit);

            if (empty($uidsToProcess)) {
                error_log("Sync: Keine neuen Mails gefunden.");
                $result['success'] = true;
                return $result;
            }

            error_log("Sync: Verarbeite " . count($uidsToProcess) . " neue Mails.");

            $highestUid = $lastUid;

            foreach ($uidsToProcess as $uid) 
            {
                
                try{
                    //starte Transaktion
                    $this->transaction(function() use ($uid, $folder, $imapFolder, &$imapStream, $pid) 
                    {
                        $overview = imap_fetch_overview($imapStream, (string)$uid, FT_UID);
                        $ov = $overview[0];
                        
                        if (!empty($overview)) ;

                        

                        if (empty($ov->from)) {
                            $header = imap_headerinfo($imapStream, imap_msgno($imapStream, $uid));
                            $ov->from = $header->fromaddress ?? 'unknown@unknown.local';
                        }

                        if (empty($ov->to)) {
                            $header = imap_headerinfo($imapStream, imap_msgno($imapStream, $uid));
                            $ov->to = $header->toaddress ?? 'unknown@unknown.local';
                        }

                        // E-Mail-Datum konvertieren
                        $formattedDate = date('Y-m-d H:i:s', strtotime($ov->date));

                        $messageId = $ov->message_id ?? 'no-id-' . $ov->uid;
                        $salt = $this->dashboard->getHashSalt(); // Falls du das in der Dashboard-Klasse hast

                        // 2. Erzeuge den Hash (Binär für die DB ist korrekt!)
                        $idHash = hash('sha256', $messageId . $salt, true);

                        $contactProcess = $this->getService("processContact", \App\Services\ContactProcessor::class);
                        
                        // Anwendung in deinem Loop
                        $fromData = $this->parseAndDecodeAddress($ov->from ? : '');
                        $toData   = $this->parseAndDecodeAddress($ov->to ? : '');

                        $fromName  = $fromData['name'];
                        $fromEmail = $fromData['email'];
                        $toName    = $toData['name'];
                        $toEmail   = $toData['email'];
                        // Für die DB-Felder:
                        $senderString    = "$fromName <$fromEmail>";
                        $recipientString = "$toName <$toEmail>";
                        $senderId = $contactProcess->insertContact($fromName, $fromEmail, 'person');
                        $reciepId = $contactProcess->insertContact($toName, $toEmail, 'person');

                        $sceid = ($senderId > 0) ? (int)$senderId : null;
                        $rceid = ($reciepId > 0) ? (int)$reciepId : null;

                        error_log("$pid: speicher ($uid) in DB mit $folder in $imapFolder");
                        $sqlMaillog = "INSERT INTO mail_log (uid, folder, imap_folder) VALUES(:uid, :folder, :imapFolder)";
                        $this->queryIt(
                            $sqlMaillog,
                            [
                                "uid"           => $uid,
                                "folder"        => $folder,
                                "imapFolder"    => $imapFolder
                            ]
                        );


                        $exists = $this->mail_exists(bin2Hex($idHash), $uid, $folder);
                        if (!$exists)
                        {
                            // 1. Mail anlegen und ID zurückholen
                            $sqlInsert = "INSERT INTO mails (uid, id_hash, message_id, subject_hash, sender, recipient, date_received, imap_folder, status, tenant_id, is_read, is_flagged, is_answered, sender_ceid, recipient_ceid) 
                                        VALUES (:uid, :id_hash, :message_id, :subject_hash, :sender, :recipient, :date_received, :folder, 'active', :tenant_id, :is_read, :is_flagged, :is_answered, :sceid, :rceid)
                                        ;";
                            /*ON DUPLICATE KEY UPDATE
                                        date_received = VALUES(date_received),
                                        sender = VALUES(sender),
                                        status = 'active',
                                        is_read = VALUES(is_read),
                                        is_flagged = VALUES(is_flagged),
                                        is_answered = VALUES(is_answered)*/

                            $params = [
                                'uid'           => $ov->uid,
                                'id_hash'       => $idHash,
                                'message_id'    => $ov->message_id ?? '',
                                'subject_hash'  => $this->dashboard->encrypt($this->decodeMimeHeader($ov->subject)),
                                'sender'        => $senderString,
                                'recipient'     => $recipientString,
                                'date_received' => $formattedDate,
                                'folder'        => $folder,
                                'is_read'       => (int)$ov->seen,
                                'is_flagged'    => (int)$ov->flagged,
                                'is_answered'   => (int)$ov->answered,
                                'sceid'         => (int)$sceid,
                                'rceid'         => (int)$rceid,

                            ];
                            $paramsLog = $params;
                            $paramsLog['subject_hash']="HASH";
                            $paramsLog['recipient']="NIX FÜR KI";
                            $paramsLog['sender']="NIX FÜR KI";
                            $paramsLog['id_hash']="NIX FÜR KI";
                            $paramsLog['message_id']="NIX FÜR KI";

                            $dbResult = $this->queryIt(
                                $sqlInsert, 
                                $params, 
                                true, 
                                true, 
                                false
                            );
                            # error_log(" übegebene Parameter: " . print_r($paramsLog, true));
                            
                        }else{
                            error_log("MAIL EXISTIERT BEREITS ($uid, $folder, $imapFolder)");
                        }
                    });
                    
                    
                    
                }catch(\Throwable $e)
                {
                    //imap_close($imapStream);
                    error_log("Fehler bei Mail: " . $e->getMessage());
                }

                
                $added++;
                $highestUid = max($highestUid, (int)$uid);
            }


            imap_close($imapStream);

            if ($added > 0) {
                $this->saveSyncCheckpoint($folder, $highestUid);
            }
            
            $result['success'] = true;
            $result['added']   = $added;

        } catch (Throwable $e) {
            error_log("Kritischer Fehler im Sync: " . $e->getMessage());
            $result['error'] = $e->getMessage();
        }

        return $result;
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

    private function mail_exists($id_hash, $uid, $folder)
    {
        $debugSQL = "SELECT Count(0) as Anzahl from mails 
                      WHERE tenant_id=:tenant_id 
                        AND (
                             (id_hash = UNHEX(:id_hash) AND imap_folder=:folder)
                         OR (uid=:uid AND imap_folder=:folder)
                        );";
        $params = [
            'id_hash' => $id_hash, 
            'folder'  => $folder,
            'uid'     => $uid
        ];
        /*$debugSQL = "SELECT Count(0) as Anzahl from mails WHERE tenant_id=:tenant_id AND uid=:uid;";
        $params = [
            'uid'     => $uid
        ];*/
        $dbResult = $this->queryIt(
            $debugSQL,
            $params,
            false,
            false, 
            false
        );

        //error_log(print_r($dbResult, true));

        $exists = ($dbResult->success && 
                 !empty($dbResult->data->fetched) && 
                 $dbResult->data->fetched[0]['Anzahl'] > 0) 
                 ? true 
                 : false;
        
        # error_log($exists);

        return $exists;
    }

    /**
     * Diese Funktion decodiert MimeHeader
     */
    private function decodeMimeHeader(mixed $text):string
    {
        if (empty($text)&& !is_string($text)) return '';

        // Sicherstellen, dass das System intern auf UTF-8 arbeitet
        $internalEncoding = mb_internal_encoding();
        mb_internal_encoding('UTF-8');

        // Mime-Header dekodieren
        $decoded = mb_decode_mimeheader($text);

        // Zurücksetzen, falls das System anders konfiguriert war (gute Praxis)
        mb_internal_encoding($internalEncoding);

        // UTF-8 Validierung
        if (!mb_check_encoding($decoded, 'UTF-8')) {
            return mb_convert_encoding($decoded, 'UTF-8', 'ISO-8859-1, Windows-1252, ASCII');
        }

        return $decoded;
    }

    /**
     * Holt die letzte erfolgreich gesyncte UID für diesen Ordner.
     */
    private function getSyncCheckpoint( string $folder): int|bool
    {
        $dbResult = $this->queryIt(
            "SELECT last_uid FROM imap_sync_checkpoint WHERE tenant_id = :tenant_id AND imap_folder = :folder",
            ['folder' => $folder],
            false,
            false,
            true
        );

        if ($dbResult->success) {
            //error_log(__FUNCTION__ . ": " . print_r($dbResult, true));

            $rows = $dbResult->data->fetched;
            // Prüfe, ob $rows ein Array ist und ob es nicht leer ist
            if (is_array($rows) && !empty($rows)) {
                // Falls queryIt ein numerisches Array von Zeilen zurückgibt (z.B. [[ 'last_uid' => 4933 ]])
                // greifen wir auf das erste Element zu:
                $firstRow = isset($rows[0]) ? $rows[0] : $rows;
                
                return isset($firstRow['last_uid']) ? (int)$firstRow['last_uid'] : false;
            }else{
                return false;
            }
        } else {
            error_log("Fehler beim Abrufen des Sync-Checkpoints: " . $dbResult->error);
            return false;
        }

    }

    /**
     * Speichert den Fortschritt.
     */
    private function saveSyncCheckpoint(string $folder, int $lastUid): bool 
    {
        $tenantId = $_SESSION['tenant_id'] ?? null;
        if(!$tenantId) {
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
    public function searchMail(string $folder, string $search, int $page = 1, int $limit = 20): array
    {
        // 1. Offset mathematisch berechnen und als sicheren Integer erzwingen
        $limit = (int)$limit;
        $offset = (int)(($page - 1) * $limit);
        
        // 2. Das SQL-Statement aufbauen
        // Da LIMIT und OFFSET im queryIt-Array oft als String übergeben würden (was MySQL crasht),
        // betten wir die vorab typisierten Zahlen direkt sicher in den String ein.
        $sql = "SELECT m.uid, m.id_hash as hash, m.subject_hash as sHash, m.sender, m.date_received, m.paperless_id, 
                        c.email as contact_name
                FROM mails m
                LEFT JOIN contact_emails c ON m.sender_ceid = c.id
                WHERE m.tenant_id = :tenant_id 
                AND m.imap_folder = :folder
                AND (
                    c.email LIKE :search 
                    OR c.email LIKE :search 
                    OR m.sender LIKE :search
                )
                ORDER BY m.date_received DESC;";
        
        try {
            // 3. Ausführung über deine native queryIt-Methode
            $dbResult = $this->queryIt($sql, [
                'folder' => $folder,
                'search' => '%' . $search . '%',
            ]);

            if(!$dbResult->success){
                return [];
            }

            $results = $dbResult->data->fetched;

            // Sicherstellen, dass immer ein Array zurückgegeben wird (falls queryIt im Leerzustand false/null liefert)
            // 2. Filter-Logik in PHP
            $filtered = [];
            $search = strtolower($search);

            foreach ($results as $row) 
            {
                if (isset($row['hash'])) {
                    $row['hash'] = bin2hex($row['hash']);
                }
                $subject = strtolower($this->dashboard->decrypt($row['sHash']));
                $sender = strtolower($row['sender']);
                $contactName = strtolower($row['contact_name'] ?? '');

                // Suche im Betreff, Absender ODER dem verknüpften Kontaktnamen
                if (str_contains($subject, $search) || 
                    str_contains($sender, $search) || 
                    str_contains($contactName, $search)) {
                    
                    $row['subject'] = $subject; // Zurückgeben als Klartext
                    $filtered[] = $row;
                }
            }

            // 3. Paging auf dem gefilterten Array
            $offset = ($page - 1) * $limit;
            return array_slice($filtered, $offset, $limit);

        } catch (\Throwable $e) {
            // Reicht den Fehler mit Kontext nach oben weiter, damit dein safeFetch ein Notify wirft
            throw new \Exception("Fehler bei der Mail-Suche in der Datenbank: " . $e->getMessage());
        }
    }

    /**
     * Prüft unfertige lokale Mails gegen die realen IMAP-Ordner.
     * Nutzt die Message-ID für ordnerübergreifendes Tracking.
     */
    public function trackAndSyncFolderStatus(): array
    {
        // 1. Alle Mails holen, die lokal noch aktiv sind (mit Mandanten-Schutz)
        $localMails = $this->queryIt(
            "SELECT id, uid, message_id, imap_folder FROM mails WHERE tenant_id = :tenant_id AND imap_folder != 'DMSDone'"
        );

        if (empty($localMails)) {
            return ['success' => true, 'updated' => 0];
        }

        // 2. IMAP-Verbindung aufbauen
        try {
            $this->connectIMAP();
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'IMAP Verbindung fehlgeschlagen'];
        }

        $targetFolders = ['INBOX', 'DMS', 'DMSDone','Sent Messages']; 
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
    public function getDecryptedMailBody(string $hash): ?string 
    {
        error_log(__FUNCTION__);

        $sql = "SELECT mb.body_encrypted 
            FROM mail_bodies AS mb 
            INNER JOIN mails AS m ON m.id = mb.mail_id 
            WHERE id_hash = UNHEX(:hash)
            AND m.tenant_id = :tenant_id;";

        $dbResult = $this->queryIt($sql, [
            'hash' => $hash
        ]);

        if (!$dbResult->success && !empty($dbResult->data->fetched)){
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
     * * @param string $hash Der Hash (oder ID) der Mail aus der lokalen DB
     * @return string Der bereinigte, lesbare UTF-8 Body der Mail
     * @throws Exception Wenn die Mail nicht existiert oder IMAP fehlschlägt
     */
    public function getMailBody($hash): string
    {
       $mail = $this->getMailByHash($hash);
        if (!$mail) return 'Inhalt nicht gefunden.';

        // 1. Erst versuchen, aus der DB zu laden (entschlüsselt)
        $htmlContent = $this->getDecryptedMailBody($hash);

        // 2. Wenn DB leer, IMAP anzapfen
        if (empty($htmlContent)) {
            $htmlContent = $this->getHtmlBody($hash, $mail['folder']);
            
            if (empty($htmlContent)) {
                // Fallback: Wenn kein HTML, versuche PlainText
                $htmlContent = $this->getTxtBody($hash, $mail['folder']);
            }
            
            if (empty($htmlContent)) {
                throw new \Exception("Kein Inhalt auf IMAP gefunden.");
            }
            
            // Jetzt in die DB schreiben (Sicherheit vor Speichern!)
            $this->saveMailWithContent($hash, $htmlContent, strip_tags($htmlContent));
        }

        // 3. Hier kommt die finale Bereinigung (HTML Purifier!)
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

    private function getIMAPFolderByHash(&$hash)
    {
        $sql = "SELECT imap_folder FROM mails WHERE tenant_id=:tenant_id AND id_hash=UNHEX(:hash);";
        $dbResult = $this->queryIt(
            $sql,
            ["hash" => $hash]
        );

        if (!$dbResult->success)
        {
            return "";
        }else{
            error_log(print_r($dbResult, true));
            return $dbResult->data->fetched[0]['imap_folder'];
        }
    }

    /**
     * Löscht eine Mail anhand ihres Hashes. WICHTIG: Diese Methode sollte mit Vorsicht verwendet werden, da sie die Mail endgültig entfernt. Es wird empfohlen, stattdessen moveToDMS zu verwenden, um die Mail zu archivieren.
     * @param string $hash Der Hash der Mail, die gelöscht werden soll.
     */
    public function deleteMailByHash($hash) 
    {
        
        //in TRASH schieben
        try {
            // Verbindung sicherstellen (nutzt die private connect Methode)
            $this->connectIMAP();

            $mail = $this->getMailByHash($hash);

            //error_log(print_r($mail, true));
            
            $msgUid = $mail['uid']; 
            $destFolder = "Deleted Messages"; // Oder dynamisch basierend auf $targetFolder

            // Verschieben via UID
            $moveSuccess = imap_mail_move($imapStream, $msgUid, $destFolder, CP_UID);

            if (!$moveSuccess) {
                throw new \Exception("IMAP-Move nach $destFolder fehlgeschlagen: " . imap_last_error());
            }
            
            // Wichtig, um die Mail aus der INBOX zu löschen
            imap_expunge($imapStream);

            // Lokalen DB Status aktualisieren
            
            //$res = $this->queryIt("DELETE FROM mails WHERE tenant_id = :tenant_id AND id = :id", 
            $res = $this->queryIt("UPDATE mails Set status='deleted' WHERE tenant_id = :tenant_id AND id_hash = :hash", 
            [
                'hash' => hash('sha256', $messageId . $salt, true)
            ] );

            return [
                'success' => true,
                'message' => "Erfolgreich nach $destFolder verschoben."
            ];

        } catch (Throwable $e) {
            throw new \Exception("Fehler in moveToDMS: " . $e->getMessage());
        }

    }

    /**
     * Holt eine paginierte Liste von Mails aus der Datenbank, basierend auf dem angegebenen Ordner. Es werden nur Mails zurückgegeben, die nicht als 'deleted' markiert sind. Der Betreff wird entschlüsselt und sowohl Betreff als auch Sender werden mit mb_convert_encoding bereinigt, um sicherzustellen, dass sie korrekt im Dashboard angezeigt werden, selbst wenn sie ursprünglich in einem anderen Zeichensatz vorlagen.
      * @param int $page Die Seite der Ergebnisse (1-basiert)
      * @param int $limit Anzahl der Mails pro Seite
      * @param string $folder Der IMAP-Ordner, aus dem die Mails gefiltert werden sollen (z.B. 'INBOX', 'DMS')
      * @return array Eine Liste von Mails mit Hash, bereinigtem Betreff, bereinigtem Sender, Datum und Paperless-Status
     */
    public function getMailsPaginated(int $page, int $limit, string $folder, string $search): array 
    {
        try{
            $offset = ($page - 1) * $limit;
            $search = trim($search);

            // 1. SQL und Parameter dynamisch generieren lassen
            [$sql, $params] = $this->buildSearchSQLAndParams($folder, $search, $limit, $offset);
            

            // 2. Datenbank abfragen
            $dbResult = $this->queryIt($sql, $params, false, false, false);

            if ($dbResult->success)
            {
                error_log("SQL-Abfrage erfolgreich. Gefundene Mails: " . count($dbResult->data->fetched ?? []));
                $mailsFromDb = $dbResult->data->fetched;
                $returnMails = []; 

                // 3. Daten filtern und für das Frontend aufbereiten
                foreach ($mailsFromDb as $row) {
                    $preparedMail = $this->prepareMailRow($row, $search);
                    
                    // Wenn die Mail den PHP-Filter passiert hat, ab ins Array
                    if ($preparedMail !== null) {
                        $returnMails[] = $preparedMail;
                    }
                }
                return $returnMails;
                
            } else {
                throw new \Exception("Datenbankfehler: " . ($dbResult->error ?? 'Unbekannter Fehler'));
            }            
            

            
        } catch (\Throwable $e) {
            error_log(__FUNCTION__ . ": " . $e->getMessage());
            return [
                'success' => false,
                'error'   => 'Fehler in ' . __FUNCTION__ .': ' . $e->getMessage(),
                'matches' => []
            ];
        }
    }


    /**
     * Baut das dynamische SQL-Statement und die zugehörigen PDO-Parameter.
     */
    private function buildSearchSQLAndParams(string $folder, string $search, int $limit, int $offset, ?string $targetHash = null): array
    {
        $salt = defined('HASH_SALT') ? HASH_SALT : 'default_fallback_salt';
        
        $params = [
            'folder' => $folder,
            'limit'  => (int)$limit,
            'offset' => (int)$offset,
            'tenant_id' => 6
        ];
        
        $sql = "SELECT 
                    m.id, uid, sender, recipient, subject_hash, m.paperless_id, imap_folder,
                    date_received as date, id_hash as hash,
                    is_read, is_flagged, is_answered, c.display_name as displayname, cm.email_hash as emailhash
                FROM mails as m
                LEFT JOIN contact_emails as cm ON cm.id = m.sender_ceid
                LEFT JOIN contacts as c ON c.id = cm.contact_id
                WHERE m.tenant_id = :tenant_id
                AND imap_folder = :folder
                AND (status NOT IN('deleted') OR status IS NULL)";

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

            $sql .= "LOWER(sender) LIKE :like_q OR body_search_index LIKE :hash_q";
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
        try{
            // Betreff entschlüsseln
            $decryptedSubject = $this->dashboard->decrypt($row['subject_hash']);

            // UTF-8 Absicherung
            $cleanSubject = mb_convert_encoding($decryptedSubject, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            $cleanSender  = mb_convert_encoding($row['sender'], 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            $cleanRecipient    = mb_convert_encoding($row['recipient'], 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

            // Zusätzlicher PHP-Filter bei aktiver Suche
            if (!empty($search)) {
                $searchLower  = mb_strtolower($search, 'UTF-8');
                $subjectLower = mb_strtolower($cleanSubject, 'UTF-8');
                $senderLower  = mb_strtolower($cleanSender, 'UTF-8');
                $dateLower    = mb_strtolower($row['date'], 'UTF-8');
                $idLower      = mb_strtolower($row['id'], 'UTF-8');

                $matchesMeta = (
                    mb_strpos($subjectLower, $searchLower) !== false || 
                    mb_strpos($senderLower, $searchLower) !== false ||
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
                'hash'         => $row['hash'],         
                'uid'          => $row['uid'] ?? null, 
                'subject'      => $cleanSubject,        
                'sender'       => $cleanSender,         
                'recipient'    => $cleanRecipient,
                'date'         => date('d.m.Y H:i', strtotime($row['date'])),
                'paperless_id' => $row['paperless_id'], 
                'is_read'       => $row['is_read'],
                'is_flagged'      => $row['is_flagged'],
                'is_answered'     => $row['is_answered'],
                'displayname'  => $row['displayname'],
                'emailhash'    => $row['emailhash']
            ];

        } catch (\Exception $e) {
            error_log(__FUNCTION__);
            return [
                'success' => false,
                'error'   => 'Fehler in ' . __FUNCTION__ .': ' . $e->getMessage(),
                'matches' => []
            ];
        }
    }

    

    /**
     * OBSOLET
     * Holt Mails, die für Paperless bereit sind, basierend auf einem optionalen Hash oder dem Alter der Mails. Wenn ein Hash übergeben wird, wird gezielt nach dieser Mail gesucht. Ansonsten werden die ältesten unverarbeiteten Mails zurückgegeben, basierend auf dem Limit. Es werden nur Mails zurückgegeben, die nicht als 'deleted' markiert sind und noch keinen Paperless-Eintrag haben. Der Betreff wird entschlüsselt und bereinigt, um sicherzustellen, dass er korrekt im Dashboard angezeigt wird.
      * @param string|null $hash Optionaler Hash der Mail, die gezielt gesucht werden soll. Wenn null, werden die ältesten Mails zurückgegeben.
      * @param int $limit Anzahl der Mails, die zurückgegeben werden sollen (nur relevant, wenn kein Hash übergeben wird)
      * @return array Eine Liste von Mails mit Hash, bereinigtem Betreff, Sender, Datum und Paperless-Status
     */
    public function getMails2Paperless(int $limit = 1): array 
    {
        try {
            $salt = defined('HASH_SALT') ? HASH_SALT : 'default_fallback_salt';
            $limit = (int)$limit;

            // Nur unverarbeitete Mails holen
            $sql = "SELECT *, SHA2(CONCAT(id, :salt), 256) as hashed_id 
                    FROM mails 
                    WHERE 
                      tenant_id = :tenant_id
                      AND(status NOT IN('deleted', 'synced') OR status IS NULL) 
                      AND paperless_id IS NULL 
                    ORDER BY date_received DESC 
                    LIMIT $limit";

            $result = $this->queryIt($sql, ['salt' => $salt]);
            $processedMails = [];

            if (empty($result)) {
                return [
                    'success' => true,
                    'message' => 'Keine unverarbeiteten Mails in der Datenbank gefunden.',
                    'matches' => []
                ];
            }

            $paperless = new \App\Services\PaperlessProcessor($this->dashboard);

            foreach ($result as $row) {
                $decryptedSubject = $this->dashboard->decrypt($row['subject_hash']);
                $cleanSubject = mb_convert_encoding($decryptedSubject, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
                $pureMailDate = date('Y-m-d', strtotime($row['date_received']));

                if (empty($cleanSubject)) {
                    $processedMails[] = [
                        'id'      => $row['id'],
                        'subject' => '[Kein Betreff]',
                        'match'   => false,
                        'reason'  => 'Suche übersprungen: Betreff leer oder unleserlich.'
                    ];
                    continue;
                }

                // Paperless API Abfrage
                $a = $paperless->searchBySubject($cleanSubject, $pureMailDate);
                $trefferAnzahl = is_array($a) ? count($a) : 0;

                if ($trefferAnzahl === 1) {
                    // Eindeutiger Treffer -> Sync und DB-Update
                    try {
                        $updateSql = "UPDATE mails SET status = 'synced', paperless_id = :paperless_id WHERE tenant_id=:tenant_id AND 
                        id_hash = :hash";
                        $this->queryIt($updateSql, ['hash' => hash('sha256', $messageId . $salt, true), 'paperless_id' => $a[0]['id']]);

                        $processedMails[] = [
                            'id'                 => $row['id'],
                            'subject'            => $cleanSubject,
                            'paperless_match_id' => $a[0]['id'],
                            'paperless_title'    => $a[0]['title'],
                            'match'              => true,
                            'reason'             => 'Erfolgreich synchronisiert.'
                        ];
                    } catch (\Exception $e) {
                        $processedMails[] = [
                            'id'      => $row['id'],
                            'subject' => $cleanSubject,
                            'match'   => false,
                            'reason'  => 'Datenbank-Update fehlgeschlagen: ' . $e->getMessage()
                        ];
                    }
                } elseif ($trefferAnzahl > 1) {
                    // Mehrfach-Match -> Überspringen
                    $processedMails[] = [
                        'id'      => $row['id'],
                        'subject' => $cleanSubject,
                        'match'   => false,
                        'reason'  => "Mehrdeutig: {$trefferAnzahl} Treffer in Paperless gefunden."
                    ];
                } else {
                    // Kein Match
                    $processedMails[] = [
                        'id'      => $row['id'],
                        'subject' => $cleanSubject,
                        'match'   => false,
                        'reason'  => 'Kein passendes Dokument in Paperless gefunden.'
                    ];
                }
            }

            return [
                'success' => true,
                'matches' => $processedMails
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => 'Fehler in getMails2Paperless: ' . $e->getMessage(),
                'matches' => []
            ];
        }
    }

    /**
     * @deprecated !!
     */
    public function getSingleMail2Paperless(?string $hash = null, ?int $id = null): array 
    {
        try {
            if (empty($hash) && empty($id)) {
                return ['success' => false, 'error' => 'Weder ID noch Hash übergeben.'];
            }
            
            
            $salt = defined('HASH_SALT') ? HASH_SALT : 'default_fallback_salt';

            // Query je nachdem ob ID oder Hash gegeben ist
            if (!empty($id)) {
                $sql = "SELECT *, SHA2(CONCAT(id, :salt), 256) as hashed_id FROM mails WHERE tenant_id = :tenant_id AND id = :mailid LIMIT 1";
                $params = ['salt' => $salt, 'mailid' => $id];
            } else {
                $sql = "SELECT *, SHA2(CONCAT(id, :salt), 256) as hashed_id FROM mails WHERE tenant_id = :tenant_id HAVING hashed_id = :hash LIMIT 1";
                $params = ['salt' => $salt, 'hash' => $hash];
            }


            $result = $this->queryIt($sql, $params);

            if (empty($result)) {
                return ['success' => false, 'error' => 'Die gesuchte E-Mail existiert nicht oder wurde bereits gelöscht.'];
            }

            $row = $result[0];

            // Falls das Dokument bereits synchronisiert wurde, melden wir das direkt zurück
            if (!empty($row['paperless_id'])) {
                return [
                    'success' => true,
                    'match'   => true,
                    'message' => 'E-Mail ist bereits synchronisiert.',
                    'paperless_match_id' => $row['paperless_id']
                ];
            }

            $decryptedSubject = $this->dashboard->decrypt($row['subject_hash']);
            $cleanSubject = mb_convert_encoding($decryptedSubject, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            $pureMailDate = date('Y-m-d', strtotime($row['date_received']));

            if (empty($cleanSubject)) {
                return ['success' => false, 'error' => 'Der Betreff der E-Mail ist leer oder unleserlich. Suche nicht möglich.'];
            }

            // Live-Suche in Paperless anstoßen
            $paperless = new Paperless($this->dashboard);
            $a = $paperless->searchBySubject($cleanSubject, $pureMailDate);
            $trefferAnzahl = is_array($a) ? count($a) : 0;

            // Fehlerfall 1: Kein Eintrag
            if ($trefferAnzahl === 0) {
                return [
                    'success' => false,
                    'error'   => 'Kein passender Eintrag in Paperless gefunden.',
                    'subject' => $cleanSubject
                ];
            }

            // Fehlerfall 2: Mehrdeutigkeit
            if ($trefferAnzahl > 1) {
                return [
                    'success' => false,
                    'error'   => "Keinen eindeutigen Eintrag gefunden (Es gibt {$trefferAnzahl} mögliche Treffer in Paperless).",
                    'subject' => $cleanSubject
                ];
            }

            // Erfolgsfall: Genau 1 Treffer gefunden -> DB aktualisieren
            $paperlessId = $a[0]['id'];
            $paperlessTitle = $a[0]['title'];

            $updateSql = "UPDATE mails SET status = 'synced', paperless_id = :paperless_id WHERE tenant_id = :tenant_id AND id_hash = :hash";
            $this->queryIt($updateSql, ['hash' => $hash, 'paperless_id' => $paperlessId]);

            return [
                'success'            => true,
                'match'              => true,
                'message'            => 'E-Mail erfolgreich mit Paperless verknüpft.',
                'mail_id'            => $row['id'],
                'subject'            => $cleanSubject,
                'paperless_match_id' => $paperlessId,
                'paperless_title'    => $paperlessTitle
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => 'Schwerwiegender Fehler bei der Einzelverarbeitung: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Aktualisiert eine bestehende Mail mit dem verschlüsselten Inhalt 
     * und dem kryptografischen Suchindex anhand ihres Hashes.
     * * @param string $hash Der SHA-256 Hash der Mail (id + salt)
     * @param string $htmlBody Der volle HTML-Inhalt der Mail
     * @param string $plainText Der reine Textinhalt der Mail für den Index
     * @return void
     */
    public function saveMailWithContent(string $hash, string $htmlBody, string $plainText): void 
    {
        // 1. Erst die ID zur Hash-Referenz finden
        $sql = "SELECT id FROM mails WHERE id_hash = UNHEX(:hash) LIMIT 1";
        //$result = $this->queryIt($sql, ['hash' => $hash]);
        
        $dbResult = $this->queryIt(
            $sql,
            ['hash' => $hash],
            false,
            false,
            false
        );

        if (!$dbResult->success) {
            $this->dashboard->sendResponse(false, "", "Keine Mail gefunden für diesen Hash, kann body nicht speichern.");
            throw new \Exception("Keine Mail gefunden für diesen Hash, kann body nicht speichern. $hash");
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

    private function sanitizeEmailBody($rawHtml) {
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
    public function buildSearchIndex(string $plainText): string {
        // 1. Text normalisieren (Umlaute weg)
        $text = $this->normalizeString($plainText);
        
        // 2. Nur noch Standard-Buchstaben und Zahlen erlauben
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        
        $words = array_filter(explode(' ', $text), function($word) {
            return strlen($word) > 2; 
        });
        
        $salt = defined('HASH_SALT') ? HASH_SALT : '';
        $hashedWords = array_map(function($word) use ($salt) {
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