<?php
namespace App;
use Exception; // Falls du Exceptions nutzt
use Throwable;
use PDO;

// classes/MailProcessor.php
class MailProcessor extends Database
{
    private $imapStream = null;

    private $dashboard; // Neu: Eigenschaft hinzufügen

    // Konstruktor hinzufügen
    public function __construct($dashboard, $dbConnection = null) {
        parent::__construct($dbConnection);
        $this->dashboard = $dashboard;
    }
    
    /**
     * Destruktor: Schließt die Verbindung sauber.
     */
    public function __destruct() {
        // 🚀 Nur schließen, wenn es eine valide, offene Ressource/Verbindung ist!
        if (isset($this->imapStream) && $this->imapStream instanceof \IMAP\Connection) {
            try {
                @imap_close($this->imapStream);
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
    private function connectIMAP($host, $user, $password) {
        // Falls der Stream schon existiert und noch antwortet, nichts tun
        if ($this->imapStream && @imap_ping($this->imapStream)) {
            return;
        }

        $connectionString = $host;
        
        // WICHTIG: Konstanten ohne $, imap_open nutzt die Parameter
        
        $this->imapStream = @imap_open($connectionString, $user, $password, OP_SILENT, 1);

        if (!$this->imapStream) {
            throw new Exception("IMAP-Verbindung fehlgeschlagen: " . imap_last_error());
        }
    }

    /**
     * Verschlüsselt den Mail-Inhalt via PHP (AES-256-CBC)
     */
    private function encryptContent(string $data): string {
        $encryption_key = defined('HASH_SALT') ? HASH_SALT : 'fallback_key_32_chars_long!!';
        // Sicherstellen, dass der Key lang genug ist
        $key = substr(hash('sha256', $encryption_key), 0, 32);
        $iv = substr(hash('sha256', $encryption_key), 0, 16);
        
        return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv));
    }


    /**
     * Holt eine Mail anhand ihres Hashes. WICHTIG: Der Hash wird in der DB generiert, um die ID zu verschleiern.
     * @param string $hash Der SHA256-Hash der Mail-ID mit Salt.
     */
    public function getMailByHash($hash) 
    {
        try {
            $salt = defined('HASH_SALT') ? HASH_SALT : '';
            
            $sql = "SELECT * FROM mails 
                    WHERE 
                      tenant_id=:tenant_id
                      AND SHA2(CONCAT(id, :salt), 256) = :hash 
                      AND (status NOT IN('deleted') OR status IS NULL) 
                      LIMIT 1";

            $rows = $this->queryIt($sql, 
                ['salt' => $salt, 'hash' => $hash]
            );
            
            if (empty($rows)) {
                throw new Exception("E-Mail mit diesem Hash wurde nicht gefunden.");
            }


            return $rows[0];

        }catch (Throwable $e) {            
            return "94: Fehler beim Laden des Mail-Bodys: " . print_r($rows) . $e->getMessage();
        }
    }

    /**
     * Holt den HTML-Inhalt einer E-Mail
     * @param resource $imapStream Die aktive IMAP-Verbindung
     * @param int $msgNumber Die Nummer der Mail im Postfach
     * @return string HTML-Inhalt oder leerer String
     */
    public function getHtmlBody($hash) 
    {
        
        try {
            $this->connectIMAP(MAIL_HOST, MAIL_USER, MAIL_PASS);
            $mail = $this->getMailByHash($hash);

            if (empty($mail) || !isset($mail['uid']) || (int)$mail['uid'] <= 0) {
                throw new \Exception("Ungültige Mail-Referenz oder UID fehlt in der lokalen Datenbank.");
            }

           
            $uid = (int)$mail['uid'];
            
            // Welcher Ordner gehört zur Mail?
            $folder = !empty($mail['imap_folder']) ? $mail['imap_folder'] : 'INBOX';

            // 🚀 SICHERHEITSMASSNAHME: Nur reopen aufrufen, wenn wir NICHT in der INBOX sind!
            // Da connectIMAP standardmäßig in der INBOX landet, sparen wir uns den fehleranfälligen Schritt.
            if (strtoupper($folder) !== 'INBOX') {
                
                // WICHTIG: Prüfe, ob deine connectIMAP den Host-String bereits mit geschweiften Klammern speichert!
                // Wir bauen ihn hier absolut sauber und standardkonform zusammen:
                $fullFolderString = MAIL_HOST . $folder;

                // Lautlos versuchen zu öffnen
                if (!imap_reopen($this->imapStream, $fullFolderString)) {
                    // Falls iCloud zickt (z.B. weil der Ordner auf dem Server "INBOX.DMSDone" statt "DMSDone" heißt):
                    // Probieren wir den iCloud-Standard-Präfix "INBOX." als Fallback
                    $fallbackFolderString =  MAIL_HOST . $folder;
                    
                    if (!imap_reopen($this->imapStream, $fallbackFolderString)) {
                        throw new \Exception(" Der IMAP-Ordner '$folder' konnte auf dem iCloud-Server nicht gefunden oder geöffnet werden.");
                    }
                }
            }
            
            
            // Jetzt wandeln wir die UID in die fortlaufende msgNo um
            $msgNumber = imap_msgno($this->imapStream, $uid);

            if (!$msgNumber || $msgNumber <= 0) {
                throw new \Exception("Mail existiert nicht mehr im Ordner '$folder' auf dem Server.");
            }

            $structure = imap_fetchstructure($this->imapStream, $msgNumber);
            if (!$structure) {
                throw new \Exception(" Mail-Struktur konnte nicht gelesen werden.");
            }

            
            // 1. Gezielte Suche nach HTML
            $htmlPart = $this->findPart($msgNumber, $structure, 'TEXT/HTML');
            if ($htmlPart) {
                return mb_convert_encoding($htmlPart, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            }
            
            
            // 2. Fallback auf Plaintext
            //return $this->getTxtBody($hash);

        } catch (Throwable $e) {
            throw new \Exception("166: Fehler beim Laden des Mail-Bodys: " . $e->getMessage());
        }
    }

    /**
     * Holt den reinen Text-Inhalt einer E-Mail (Fallback)
     */
    public function getTxtBody($hash) 
    {
        try {
            $this->connectIMAP(MAIL_HOST, MAIL_USER, MAIL_PASS);
            $mail = $this->getMailByHash($hash);
            
            $msgNumber = imap_msgno($this->imapStream, $mail['uid']);
            if (!$msgNumber) {
                return "Mail existiert nicht mehr auf dem Server.";
            }

            $structure = imap_fetchstructure($this->imapStream, $msgNumber);
            $plainText = $this->findPart($msgNumber, $structure, 'TEXT/PLAIN');
            
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
    private function findPart($msgNumber, $structure, $mimeType, $partNumber = "") 
    {
        try{
            $primaryTypes = ["TEXT", "MULTIPART", "MESSAGE", "APPLICATION", "AUDIO", "IMAGE", "VIDEO", "OTHER"];
            
            if (!$structure) return null;

            // MIME-Type des aktuellen Teils zusammensetzen
            $primary = $primaryTypes[$structure->type] ?? "TEXT";
            $subtype = !empty($structure->subtype) ? strtoupper($structure->subtype) : "PLAIN";
            $currentMimeType = $primary . '/' . $subtype;

            // Match gefunden!
            if ($currentMimeType === $mimeType) {
                $targetPart = ($partNumber === "") ? "1" : $partNumber;
                $text = imap_fetchbody($this->imapStream, $msgNumber, $targetPart);
                
                // Dekodierung
                if ($structure->encoding == 3) {
                    return base64_decode($text);
                } elseif ($structure->encoding == 4) {
                    return quoted_printable_decode($text);
                }
                return $text;
            }

            // Wenn es ein Multipart-Typ ist, rekursiv die Unterteile durchsuchen
            if ($structure->type == 1 && !empty($structure->parts)) {
                foreach ($structure->parts as $index => $subStructure) {
                    // IMAP Part-Nummerierung aufbauen (z.B. "1.1", "1.2")
                    $newPartNumber = ($partNumber === "") ? ($index + 1) : $partNumber . '.' . ($index + 1);
                    
                    $result = $this->findPart($msgNumber, $subStructure, $mimeType, $newPartNumber);
                    if ($result !== null && $result !== "") {
                        return $result;
                    }
                }
            }

        }catch (Throwable $e) {
            throw new Exception("Fehler in findPart: " . $e->getMessage());
        }

        return null;
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
            $this->connectIMAP(MAIL_HOST, MAIL_USER, MAIL_PASS);

            $mail = $this->getMailByHash($hash);

            
            $msgUid = $mail['uid']; 
            $destFolder = "DMS"; // Oder dynamisch basierend auf $targetFolder

            // Verschieben via UID
            $moveSuccess = imap_mail_move($this->imapStream, $msgUid, $destFolder, CP_UID);

            if (!$moveSuccess) {
                throw new Exception("IMAP-Move nach $destFolder fehlgeschlagen: " . imap_last_error());
            }
            
            // Wichtig, um die Mail aus der INBOX zu löschen
            imap_expunge($this->imapStream);

            // Lokalen DB Status aktualisieren
            
            $res = $this->queryIt("UPDATE mails SET status = 'archived' WHERE id = :id", ['id' => $mail['id']]);

            return [
                'success' => true,
                'message' => "Erfolgreich nach $destFolder verschoben."
            ];

        } catch (Throwable $e) {
            throw new Exception("Fehler in moveToDMS: " . $e->getMessage());
        }
    }

    /**
     * Synchronisiert im Hintergrund alle Mails in inbox /dms /dmsdone
     * @param boolean debug Debugging
     * @param int $maxNewMailsLimt (default 20)
     * @param string $folder (default Inbox)
     */
    public function syncWithICloud(bool $debug = true, int $maxNewMailsLimit = 200, string $folder = "INBOX"): array 
    {
        //$processor = $this->getService('mail', \App\MailProcessor::class);

        @set_time_limit(120);
        $added = 0;
        $result = ['success' => false, 'added' => 0, 'error' => null];

        try {
            $this->connectIMAP(MAIL_HOST, MAIL_USER, MAIL_PASS);
            if (strtoupper($folder) !== 'INBOX' && !@imap_reopen($this->imapStream, MAIL_HOST . $folder)) {
                throw new Exception("Konnte Ordner '$folder' nicht öffnen.");
            }

            // 1. Hole alle UIDs sortiert vom Server - VOR dem Debugging!
            $allUids = imap_sort($this->imapStream, SORTARRIVAL, 0, SE_UID);
            $lastUid = $this->getSyncCheckpoint(6, $folder);

            // DEBUG-BLOCK (Jetzt mit Werten)
            $maxServerUid = !empty($allUids) ? max($allUids) : 0;
            error_log("DEBUG-SYNC: Gefundene Mails im Ordner: " . count($allUids) . " | Letzte UID in DB: $lastUid | Höchste UID Server: $maxServerUid");

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

            foreach ($uidsToProcess as $uid) {
                $overview = imap_fetch_overview($this->imapStream, (string)$uid, FT_UID);
                if (empty($overview)) continue;

                $ov = $overview[0];

                // SQL mit korrektem ON DUPLICATE KEY UPDATE
                $insertSql = "INSERT INTO mails (uid, message_id, subject_hash, sender, date_received, imap_folder, status, tenant_id, is_answered, is_read, is_flagged) 
                            VALUES (:uid, :message_id, :subject_hash, :sender, :date_received, :folder, 'active', :tenant_id, :is_answered, :is_read, :is_flagged)
                            ON DUPLICATE KEY UPDATE
                            is_read = VALUES(is_read), 
                            is_flagged = VALUES(is_flagged), 
                            is_answered = VALUES(is_answered)";
                
                $this->queryIt($insertSql, [
                    'uid'           => (int)$uid,
                    'message_id'    => $ov->message_id ?? null,
                    'subject_hash'  => $this->dashboard->encrypt($ov->subject ?? ''),
                    'sender'        => $this->decodeMailHeader($ov->from ?? 'Unbekannt'),
                    'date_received' => date('Y-m-d H:i:s', strtotime($ov->date ?? 'now')),
                    'folder'        => $folder,
                    'tenant_id'     => 6,
                    'is_answered'   => (int)($ov->answered ?? 0),
                    'is_read'       => (int)($ov->seen ?? 0),
                    'is_flagged'    => (int)($ov->flagged ?? 0)
                ]);
                
                $added++;
                $highestUid = max($highestUid, (int)$uid);
            }

            if ($added > 0) {
                $this->saveSyncCheckpoint(6, $folder, $highestUid);
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
     * Holt die letzte erfolgreich gesyncte UID für diesen Ordner.
     */
    private function getSyncCheckpoint(int $tenantId, string $folder): int 
    {
        $rows = $this->queryIt(
            "SELECT last_uid FROM imap_sync_checkpoint WHERE tenant_id = :tenant_id AND imap_folder = :folder",
            ['folder' => $folder]
        );

        // Prüfe, ob $rows ein Array ist und ob es nicht leer ist
        if (is_array($rows) && !empty($rows)) {
            // Falls queryIt ein numerisches Array von Zeilen zurückgibt (z.B. [[ 'last_uid' => 4933 ]])
            // greifen wir auf das erste Element zu:
            $firstRow = isset($rows[0]) ? $rows[0] : $rows;
            
            return isset($firstRow['last_uid']) ? (int)$firstRow['last_uid'] : 0;
        }

        return 0;
    }

    /**
     * Speichert den Fortschritt.
     */
    private function saveSyncCheckpoint(int $tenantId, string $folder, int $lastUid): void 
    {
        $this->queryIt(
            "INSERT INTO imap_sync_checkpoint (tenant_id, imap_folder, last_uid) 
            VALUES (:tenant_id, :folder, :lastUid) 
            ON DUPLICATE KEY UPDATE last_uid = :lastUid",
            ['tenant_id' => $tenantId, 'folder' => $folder, 'lastUid' => $lastUid]
        );
    }

    private function decodeMailHeader(string $headerString): string 
    {
        if (empty($headerString)) return '';
        
        $elements = imap_mime_header_decode($headerString);
        $cleanText = "";
        
        foreach ($elements as $element) {
            $text = $element->text;
            // Falls es nicht standardmäßig UTF-8 oder default ist, konvertieren
            if ($element->charset != 'default' && $element->charset != 'utf-8') {
                // uppercase/lowercase absichern
                $charset = strtoupper($element->charset);
                $text = mb_convert_encoding($text, 'UTF-8', $charset);
            }
            $cleanText .= $text;
        }
        
        return $cleanText;
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
        $sql = "SELECT m.uid, m.subject_hash as hash, m.sender, m.date_received, m.paperless_id, 
                        c.email as contact_name
                    FROM mails m
                    LEFT JOIN contact_emails c ON m.contact_id = c.id
                    WHERE m.tenant_id = :tenant_id 
                    AND m.imap_folder = :folder
                    AND (
                        c.email LIKE :search 
                        OR c.email LIKE :search 
                        OR m.sender LIKE :search
                    );";
        
        try {
            // 3. Ausführung über deine native queryIt-Methode
            $results = $this->queryIt($sql, [
                'folder' => $folder,
                'search' => '%' . $search . '%',
            ]);

            // Sicherstellen, dass immer ein Array zurückgegeben wird (falls queryIt im Leerzustand false/null liefert)
            // 2. Filter-Logik in PHP
            $filtered = [];
            $search = strtolower($search);

            foreach ($results as $row) {
                $subject = strtolower($this->dashboard->decrypt($row['subject_hash']));
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
            $this->connectIMAP(MAIL_HOST, MAIL_USER, MAIL_PASS);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'IMAP Verbindung fehlgeschlagen'];
        }

        $targetFolders = ['INBOX', 'DMS', 'DMSDone']; 
        $baseConnString = preg_replace('/\}.*$/', '}', MAIL_HOST);
        $results = [];

        // 3. Nur den aktuellen Live-Status ermitteln, OHNE in die DB zu schreiben
        foreach ($localMails as $mail) {
            $msgId = $mail['message_id'];
            if (empty($msgId)) continue;

            $currentFolder = 'DMSDone'; // Fallback, falls nirgends mehr auffindbar
            $currentUid = 0;

            foreach ($targetFolders as $folder) {
                $fullFolderString = $baseConnString . $folder;
                
                if (@imap_reopen($this->imapStream, $fullFolderString)) {
                    $searchRes = imap_search($this->imapStream, 'HEADER Message-ID "' . $msgId . '"');

                    if (!empty($searchRes)) {
                        $currentFolder = $folder;
                        $currentUid = imap_uid($this->imapStream, $searchRes[0]);
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
    public function getDecryptedMailBody(string $hash): ?string {
        $sql = "SELECT body_encrypted FROM mails WHERE SHA2(CONCAT(id, :salt), 256) = :hash";
        $rows = $this->queryIt($sql, [
            'salt' => defined('HASH_SALT') ? HASH_SALT : '',
            'hash' => $hash
        ]);

        if (empty($rows) || !$rows[0]['body_encrypted']) return null;

        $encryption_key = defined('HASH_SALT') ? HASH_SALT : 'fallback_key_32_chars_long!!';
        $key = substr(hash('sha256', $encryption_key), 0, 32);
        $iv = substr(hash('sha256', $encryption_key), 0, 16);

        return openssl_decrypt(base64_decode($rows[0]['body_encrypted']), 'AES-256-CBC', $key, 0, $iv);
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
        
        // 1. Inhalt über das Dashboard entschlüsseln
        $decryptedBody = $this->dashboard->decrypt($mail['body_hash']); 
        
        // 2. --- HIER NEU: UTF-8 ABSICHERUNG FÜR DEN INHALT ---
        // Repariert kaputte Zeichensätze (z.B. aus alten Outlook- oder ISO-Mails) vor dem JSON-Encoding
        $cleanBody = mb_convert_encoding($decryptedBody, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

        return $cleanBody;
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
            $this->connectIMAP(MAIL_HOST, MAIL_USER, MAIL_PASS);

            $mail = $this->getMailByHash($hash);

            error_log(print_r($mail, true));
            
            $msgUid = $mail['uid']; 
            $destFolder = "Deleted Messages"; // Oder dynamisch basierend auf $targetFolder

            // Verschieben via UID
            $moveSuccess = imap_mail_move($this->imapStream, $msgUid, $destFolder, CP_UID);

            if (!$moveSuccess) {
                throw new Exception("IMAP-Move nach $destFolder fehlgeschlagen: " . imap_last_error());
            }
            
            // Wichtig, um die Mail aus der INBOX zu löschen
            imap_expunge($this->imapStream);

            // Lokalen DB Status aktualisieren
            
            //$res = $this->queryIt("DELETE FROM mails WHERE tenant_id = :tenant_id AND id = :id", 
            $res = $this->queryIt("UPDATE mails Set status='deleted' WHERE tenant_id = :tenant_id AND SHA2(CONCAT(id, :salt), 256) = :hash", 
            [
                'id' => $mail['id'],
                'salt' => defined('HASH_SALT') ? HASH_SALT : '', 
                'hash' => $hash
            ] );

            return [
                'success' => true,
                'message' => "Erfolgreich nach $destFolder verschoben."
            ];

        } catch (Throwable $e) {
            throw new Exception("Fehler in moveToDMS: " . $e->getMessage());
        }

    }

    /**
     * Holt Mails paginiert und durchsucht Betreff, Absender ODER den verschlüsselten Inhalt
     */
    public function getMailsPaginated(int $page, int $limit, string $folder, string $search): array 
    {
        try{
            $offset = ($page - 1) * $limit;
            $search = trim($search);

            // 1. SQL und Parameter dynamisch generieren lassen
            [$sql, $params] = $this->buildSearchSQLAndParams($folder, $search, $limit, $offset);

            // 2. Datenbank abfragen
            $mailsFromDb = $this->queryIt($sql, $params);
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
    private function buildSearchSQLAndParams(string $folder, string $search, int $limit, int $offset): array
    {
        try {
            $salt = defined('HASH_SALT') ? HASH_SALT : 'default_fallback_salt';
            
            $params = [
                'salt'   => $salt,
                'folder' => $folder,
                'limit'  => intval($limit),
                'offset' => intval($offset)
            ];
            
            $sql = "SELECT 
                        id, 
                        uid,
                        sender, 
                        subject_hash, 
                        paperless_id,
                        imap_folder,
                        date_received as date,
                        SHA2(CONCAT(id, :salt), 256) as hash 
                    FROM mails 
                    WHERE 
                    tenant_id = :tenant_id
                    AND imap_folder = :folder
                    AND (status NOT IN('deleted') OR status IS NULL)";

            if (!empty($search)) {
                
                $searchNormalized = $this->normalizeString($search);
                $hashedSearch = substr(hash('sha256', $searchNormalized . $salt), 0, 12);

                $sql .= " AND (
                    LOWER(sender) LIKE :like_q 
                    OR body_search_index LIKE :hash_q";

                $params['like_q'] = mb_strtolower($search, 'UTF-8');
                $params['hash_q'] = '%' . $hashedSearch . '%';
                
                if (preg_match('/[0-9.\-:]/', $search)) {
                    $params['search_date'] = mb_strtolower($search, 'UTF-8');
                    $sql .= " OR date_received LIKE :search_date";
                }
                
                if (is_numeric($search)) {
                    $params['search_id'] = intval($search);
                    $sql .= " OR id = :search_id";
                }

                $sql .= " )";
            }

            $sql .= " ORDER BY date_received DESC LIMIT :limit OFFSET :offset";

            
            return [$sql, $params];

        } catch (\Throwable $e) {
            error_log(__FUNCTION__);
            return [
                'success' => false,
                'error'   => 'Fehler in ' . __FUNCTION__ .': ' . $e->getMessage(),
                'matches' => []
            ];
        }
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
                'date'         => date('d.m.Y H:i', strtotime($row['date'])),
                'paperless_id' => $row['paperless_id'], 
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
     * Holt eine paginierte Liste von Mails aus der Datenbank, basierend auf dem angegebenen Ordner. Es werden nur Mails zurückgegeben, die nicht als 'deleted' markiert sind. Der Betreff wird entschlüsselt und sowohl Betreff als auch Sender werden mit mb_convert_encoding bereinigt, um sicherzustellen, dass sie korrekt im Dashboard angezeigt werden, selbst wenn sie ursprünglich in einem anderen Zeichensatz vorlagen.
      * @param int $page Die Seite der Ergebnisse (1-basiert)
      * @param int $limit Anzahl der Mails pro Seite
      * @param string $folder Der IMAP-Ordner, aus dem die Mails gefiltert werden sollen (z.B. 'INBOX', 'DMS')
      * @return array Eine Liste von Mails mit Hash, bereinigtem Betreff, bereinigtem Sender, Datum und Paperless-Status
     */
    public function getMailsPaginatedOLD(int $page = 1, int $limit = 15, string $folder = 'INBOX', string $search = '') {
        $offset = ($page - 1) * $limit;
        $salt = defined('HASH_SALT') ? HASH_SALT : 'default_fallback_salt';
        
        // Nutze das im Objekt hinterlegte Salt, passend zu den Params
        $params = [
            'salt'   => $salt,
            'folder' => '%'
        ];
        
        $search = trim($search);

        if (!empty($search)) {
            $page = 1;
        }

        // 1. Der Basis-String für beide Fälle
        $sql = "SELECT 
                    id, 
                    sender, 
                    subject_hash, 
                    paperless_id,
                    imap_folder,
                    date_received as date,
                    SHA2(CONCAT(id, :salt), 256) as hash 
                FROM mails 
                WHERE tenant_id = :tenant_id
                  AND imap_folder Like :folder
                  AND (status NOT IN('deleted') OR status IS NULL)";
        
        $searchLower = mb_strtolower($search, 'UTF-8');

        // 2. Fall-Unterscheidung für die SQL-Ebene
        if (!empty($search)) {
            $searchTerm = "%" . $searchLower . "%";
            $params['search_sender']  = $searchTerm;
            $params['search_date']    = $searchTerm;
            
            $sql .= " AND (
                LOWER(sender) LIKE :search_sender";

            // Das Datum durchsuchen wir NUR, wenn der Suchbegriff Zahlen oder Datums-Trennzeichen enthält
            if (preg_match('/[0-9.\-:]/', $search)) {
                $params['search_date'] = $searchTerm;
                $sql .= " OR date_received LIKE :search_date";
            }
            
            if (is_numeric($search)) {
                $params['search_id'] = intval($search);
                $sql .= " OR id = :search_id";
            }

            $sql .= " )";
            
            // Bei Suche KEIN SQL-Limit, da wir erst nach dem Entschlüsseln in PHP filtern können!
            $sql .= " ORDER BY date_received DESC";

        } else {
            // Normaler Ablauf mit nativer SQL-Paginierung
            $params['limit']  = intval($limit);
            $params['offset'] = intval($offset);
            $sql .= " ORDER BY date_received DESC LIMIT :limit OFFSET :offset";
        }

        $mailsFromDb = $this->queryIt($sql, $params, $limit, $offset);
        $returnMails = []; // ✅ Ein frisches Array für die sauberen Rückgabedaten

        foreach ($mailsFromDb as $row) { // ✅ Korrekte foreach-Syntax
            
            // Betreff entschlüsseln
            $decryptedSubject = $this->dashboard->decrypt($row['subject_hash']);

            // UTF-8 Absicherung
            $cleanSubject = mb_convert_encoding($decryptedSubject, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            $cleanSender  = mb_convert_encoding($row['sender'], 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

            // --- FILTER-LOGIK IN PHP ---
            if (!empty($searchLower)) {
                $senderLower  = mb_strtolower($cleanSender, 'UTF-8');
                $subjectLower = mb_strtolower($cleanSubject, 'UTF-8');
                $dateLower    = mb_strtolower($row['date'], 'UTF-8');
                $idLower      = mb_strtolower($row['id'], 'UTF-8');

                if (
                    mb_strpos($senderLower, $searchLower) === false && 
                    mb_strpos($subjectLower, $searchLower) === false &&
                    mb_strpos($dateLower, $searchLower) === false &&
                    mb_strpos($idLower, $searchLower) === false
                ) {
                    continue; // Begriff nicht gefunden -> Überspringen
                }
            }

            // Mail für die Rückgabe vorbereiten (Keys matchen exakt mit JavaScript!)
            $returnMails[] = [
                'hash'          => $row['hash'],         // ✅ Korrekt aus SQL (as hash)
                'subject'       => $cleanSubject,        // ✅ Damit klappt mail.subject im JS!
                'sender'        => $cleanSender,         // ✅ Wieder 'sender' statt 'mail'
                'date'          => date('d.m.Y H:i', strtotime($row['date'])),
                'paperless_id'  => $row['paperless_id'], 
            ];
        }

        // Bei manueller Suche eventuell künstlich paginieren (falls du das später implementierst)
        // Aktuell geben wir einfach die gesammelten Treffer zurück
        return $returnMails;
    }

    /**
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

            $paperless = new Paperless($this->dashboard);

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
                        $updateSql = "UPDATE mails SET status = 'synced', paperless_id = :paperless_id WHERE id = :id";
                        $this->queryIt($updateSql, ['id' => $row['id'], 'paperless_id' => $a[0]['id']]);

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

            $updateSql = "UPDATE mails SET status = 'synced', paperless_id = :paperless_id WHERE tenant_id = :tenant_id AND id = :id";
            $this->queryIt($updateSql, ['id' => $row['id'], 'paperless_id' => $paperlessId]);

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
        $sql = "UPDATE mails 
                SET body_encrypted = :encrypted_body, 
                    body_search_index = :search_index 
                WHERE SHA2(CONCAT(id, :salt), 256) = :hash AND tenant_id = :tenant_id";
                
        $this->queryIt($sql, [
            'encrypted_body' => $this->encryptContent($htmlBody),
            'search_index'   => $this->buildSearchIndex($plainText),
            'salt'           => defined('HASH_SALT') ? HASH_SALT : '',
            'hash'           => $hash
        ]);
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