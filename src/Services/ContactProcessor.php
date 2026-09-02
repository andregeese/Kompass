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
use Throwable;
use DateTime;
use PDO;

/**
 * @method void contactProcess() 
 */
class ContactProcessor extends \App\Core\Database
{
    use \App\Core\ServiceTrait;
    protected $dashboard;
    public function __construct($dashboard)
    {
        parent::__construct($dashboard);
        $this->dashboard = $dashboard;
    }

    /**
     * Legt einen neuen Hauptkontakt und die zugehörige E-Mail-Adresse an.
     * * @param string $name Der Anzeigename des Kontakts
     * @param string $email Die E-Mail-Adresse
     * @param string $type Typ des Kontakts ('company' oder 'person')
     * @param int $paperless_id Paperless-Verknüpfung
     * @return int|bool Die neue Contact-ID bei Erfolg, sonst false
     */
    public function insertContact(string $name, string $email, string $type)
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        $tenantId = $_SESSION['tenant_id'] ?? null;

        if (empty($name) || empty($email) || empty($tenantId)) {
            error_log("Kontakt konnte nicht angelegt werden: Daten fehlen.");
            return false;
        }

        $salt = $this->dashboard->getHashSalt();
        // 1. ZUERST schauen, ob es die E-Mail schon gibt (Tenant-bezogen!)
        $contactEmailId = $this->getContactEmailIdInternal($email);
        error_log("Kontakt-Email gefunden $contactEmailId");

        if ($contactEmailId) {
            return $contactEmailId;
        }

        // 2. Wenn nein: Kontakt suchen oder anlegen
        $contactId = $this->getContactIdInternal($name, $email, $type);
        //error_log("Kontakt gefunden $contactId");

        if (!$contactId) {
            //error_log("Kontakt anlegen");
            // Kontakt neu anlegen
            $sqlContact = "INSERT INTO contacts (display_name, type, tenant_id, is_active) VALUES (:name, :type, :tenant_id, 1);";
            $dbResult = $this->queryIt(
                $sqlContact, 
                [
                    'name' => $name, 
                    'type' => $type
                ], 
                false, 
                true, 
                false
            );

            if (!$dbResult->success) return false;
            $contactId = $dbResult->data->lastId;
            //error_log("Kontakt angelegt: $contactId");
        }
        // 3. E-Mail Eintrag erzwingen (da wir hier gelandet sind, existiert sie noch nicht für diesen Tenant)
        //error_log("$contactId -> $email => anlegen");
        return $this->insertContactEmail($contactId, $email, $salt);
    }

    /**
     * Sucht in der DB nach einem Kontakt mit genau diesem Namen, E-Mail und Typ.
     * Rückgabe der ID ist verschlüsselt
     * @param string $name Name
     * @param string $email E-Mail-Adresse
     * @param string $type Person oder Company
     * @return ID Hash AES_ENCRYPTED
     */
    private function getContactId($name, $email, $type, $bInternal=false)
    {
        //error_log("Suche Kontakt: Name='$name', Email='$email', Type='$type'");
        
        if (!$bInternal){
            $sql = "SELECT id";
        }else{
            $sql = "SELECT HEX(AES_ENCRYPT(CAST(c.id as CHAR), :crypto_key)) as id";
        }

        $sql = $sql. "
                  FROM contacts as c
                 WHERE tenant_id = :tenant_id 
                   AND display_name = :name 
                   AND type = :type 
                 LIMIT 1;";
        $params = [
            "tenant_id" => $_SESSION['tenant_id'] ?? null,
            "name" => $name,
            "type" => $type
        ];
        $dbResult = $this->queryIt(
            $sql,
            $params,
            false,
            false,
            false
        );

        // 🚀 FIX 1: Sauber die ID extrahieren, statt das ganze Objekt zurückzugeben
        if ($dbResult && $dbResult->success && !empty($dbResult->data->fetched)) {
            return $dbResult->data->fetched[0]['id'];
        }
        
        return false;
    }

    private function getContactIdInternal($name, $email, $type):int|bool
    {
        //error_log("Suche Kontakt: Name='$name', Email='$email', Type='$type'");
        
        $sql = "SELECT id
                FROM contacts as c
                WHERE tenant_id = :tenant_id 
                AND display_name = :name 
                AND type = :type 
                LIMIT 1;";
        $params = [
            "tenant_id" => $_SESSION['tenant_id'] ?? null,
            "name" => $name,
            "type" => $type
        ];
        $dbResult = $this->queryIt(
            $sql,
            $params,
            false,
            false,
            false
        );

        // 🚀 FIX 1: Sauber die ID extrahieren, statt das ganze Objekt zurückzugeben
        if ($dbResult && $dbResult->success && !empty($dbResult->data->fetched)) {
            return $dbResult->data->fetched[0]['id'];
        }
        
        return false;
    }

    /**
     * Aktualisiert die ContactID in Contacts
     * @params int $cid => ContactID
     * @params int $ceid => ContactEmailID
     * @params string $link => Verlinken oder nicht!
     */
    public function updateContactEmailToContact($cid, $ceid, $action)
    {
        $sql = "UPDATE contact_emails 
                   SET contact_id=CAST(AES_DECRYPT(UNHEX(:cid), :crypto_key) AS UNSIGNED) 
                 WHERE tenant_id=:tenant_id 
                   AND id=CAST(AES_DECRYPT(UNHEX(:ceid), :crypto_key) AS UNSIGNED)";
        $params = [
            'cid' => ($action === 'link') ? $cid : null,
            'ceid' => $ceid
        ];

        $dbResult = $this->queryIt($sql, $params, true, false, true);

        return $dbResult;
    }

    private function getContactEmailIdInternal(string $email): ?int
    {
        $sql = "SELECT id FROM contact_emails WHERE email = :email AND tenant_id = :tenant_id LIMIT 1";
        $res = $this->queryIt($sql, ['email' => $email]);
        return ($res->success && !empty($res->data->fetched)) ? (int)$res->data->fetched[0]['id'] : null;
    }

    private function getContactEmailId(string $email, $internal=false)
    {
        // Sicherheitscheck: Ohne TenantId ist die Suche in einer Multi-Tenant Umgebung ungültig
        //if (empty($tenantId)) return false;

        if (!$internal){
            $sql = "SELECT HEX(AES_ENCRYPT(CAST(ce.id as CHAR), :crypto_key)) as id";
        }else{
            $sql = "SELECT id";
        }

        $sql = $sql." FROM contact_emails ce
                 WHERE ce.tenant_id = :tenant_id 
                   AND ce.email = :email 
                 LIMIT 1;";
                 
        $dbResult = $this->queryIt($sql, ["email" => $email], false, false, false);

        // 🚀 FIX 2: Erst prüfen, ob überhaupt Daten gefunden wurden, um Fatal Errors zu vermeiden
        if ($dbResult && $dbResult->success && !empty($dbResult->data->fetched)) {
            return $dbResult->data->fetched[0]['id'];
        }
        
        return false;
    }


    private function insertContactEmail($contactId, $email, $salt)
    {
        //error_log("Kontakt Email setzen Email='$email', ContactId='$contactId'");
        $tenantId = $_SESSION['tenant_id'] ?? null;
        if (
            $tenantId === null
            || empty($email)
            //|| !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            error_log("Ungültige Daten für insertContactEmail: ContactID='$contactId', Email='$email', TenantID='$tenantId'");
            return false;
        }

        $ceId = $this->getContactEmailIdInternal($email);

        if (!$ceId) {

            $sqlEmail = "INSERT INTO contact_emails (contact_id, email, email_hash, tenant_id) 
                    VALUES (:cid, :email, :hash, :tenant_id);";

            $dbResult = $this->queryIt(
                $sqlEmail,
                [
                    'cid' => $contactId,
                    'email' => $email,
                    'hash' => hash('sha256', $email . $salt)
                ],
                false,
                true,
                false
            );

            // 🚀 FIX: Zuweisung korrigiert (error_log gibt true/false zurück, nicht die ID!)
            if ($dbResult && $dbResult->success) {
                $ceId = $dbResult->data->lastId;
            } else {
                error_log("Kontakt-E-Mail konnte nicht angelegt werden: " . $email);
                return false;
            }
        }

        return $ceId;
    }

    /**
     * Aktualisiert den Kontakt mit der paperless_id
     */
    public function updatePaperlessId($contact_id, $paperless_id)
    {
        $sql = "UPDATE contacts SET paperless_id= :pid WHERE tenant_id=:tenant_id AND id=:cid";
        $params = ["cid" => $contact_id, "pid" => $paperless_id];

        return $this->queryIt($sql, $params, true, false, false);
    }

    /**
     * Speichert den Kontakt
     */
    public function saveContact($data)
    {
        $sql = "UPDATE contacts SET display_name=:dname, type=:type WHERE tenant_id=:tenant_id AND id=CAST(AES_DECRYPT(UNHEX(:cid), :crypto_key) AS UNSIGNED);";
        error_log(print_r($data, true));
        $params = [
            'dname' => $data['displayname'],
            'type'  => $data['type'],
            'cid'   => $data['id']
        ];

        return $this->queryIt($sql, $params, true, false, false);
    }

    /**
     * Kontakte anhand von hashed ID
     * wird benötigt um den Tree in right_sidebar zu erstellen
     */
    public function getEmailsAndContactsByHash($hash)
    {
        $sql = "SELECT 
                HEX(AES_ENCRYPT(CAST(c.id as CHAR), :crypto_key)) as contact_id,
                c.is_active,
                c.display_name,
                c.type,
                GROUP_CONCAT(DISTINCT ce.email SEPARATOR ', ') AS additional_emails
            FROM contacts AS c
            INNER JOIN mails AS m ON c.id = m.sender_ceid
            LEFT JOIN contact_emails AS ce ON c.id = ce.contact_id
            WHERE id_hash = UNHEX(:hash)
            AND m.tenant_id = :tenant_id
            GROUP BY c.id, c.display_name, c.type";
        $ret = $this->queryIt(
            $sql,
            ["hash" => "%" . $hash . "%"]
        );
        return $ret;
    }

    
    /**
     * @deprecated
     * Hilfsfunktion zum Inaktivsetzen leerer Kontakte
     */
    private function cleanupOrphanedContacts($tenant_id)
    {
        // Setzt alle Kontakte auf inaktiv, die keine Einträge in contact_emails haben
        $sql = "UPDATE contacts c
                SET c.is_active = 0
                WHERE c.tenant_id = :tenant_id 
                AND NOT EXISTS (SELECT 1 FROM contact_emails ce WHERE ce.contact_id = c.id)
                AND c.is_active != 0";

        $this->queryIt($sql, [], true, false, true);
        error_log("Orphaned Cleanup abgeschlossen (inaktive Kontakte markiert).");
    }

    /**
     * Alle Email-Adressen
     */
    public function getAllEmails()
    {
        $sql = "SELECT 
                    HEX(AES_ENCRYPT(CAST(c.id as CHAR), :crypto_key)) as contact_id,
                    c.is_active as is_active,
                    c.display_name,
                    c.type,
                    ce.id AS ceid,
                    ce.email
                FROM contacts c
                LEFT JOIN contact_emails ce ON c.id = ce.contact_id
                WHERE c.tenant_id = :tenant_id
                -- Hier kannst du nach Bedarf filtern
                ORDER BY ce.email";

        $dbRequest = $this->queryIt(
            $sql,
            [],
            false,
            false,
            false
        );

        if (!$dbRequest->success) {
            throw new \Exception("Keine Mails gefunden!");
        }
        return $dbRequest->data->fetched;
    }

    
    /**
     * Bekommt eine Liste von Kontakten und dessen Verknüpfungen
     */
    public function getContactListByContactID($hash)
    {
        $sql = "SELECT 
                    HEX(AES_ENCRYPT(CAST(c.id as CHAR), :crypto_key)) as contact_id,
                    c.is_active as is_active,
                    -- c.paperless_id as paperless_id,
                    cp.*,
                    c.display_name,
                    c.type,
                    HEX(AES_ENCRYPT(CAST(ce.id as CHAR), :crypto_key)) AS ceid,
                    ce.email
                FROM contacts c
                LEFT JOIN contact_emails ce ON c.id = ce.contact_id
                LEFT JOIN contact_paperless_map cp ON cp.contact_id = c.id
                WHERE c.tenant_id = :tenant_id
                  AND ce.contact_id=CAST(AES_DECRYPT(UNHEX(:hash), :crypto_key) AS UNSIGNED)
                -- Hier kannst du nach Bedarf filtern
                ORDER BY c.display_name, ce.email";

        $dbRequest = $this->queryIt(
            $sql,
            ["hash" => $hash],
            false,
            false,
            false
        );

        if (!$dbRequest->success) {
            return [];
        }

        $ret = $dbRequest->data->fetched;

        $contacts = [];
        foreach ($ret as $row) 
        {
            $cid = $row['contact_id'];
            if (!isset($contacts[$cid])) {
                $contacts[$cid] = [
                    'id' => $cid,
                    'paperless_id' => $row['paperless_id'],
                    'is_active' => $row['is_active'],
                    'displayname' => $row['display_name'],
                    'type' => $row['type'],
                    'emails' => []
                ];
            }
            if ($row['ceid']) {
                $contacts[$cid]['emails'][] = [
                    'ceid' => $row['ceid'],
                    'email' => $row['email']
                ];
            }
        }
        $finalContacts = array_values($contacts);
        return $finalContacts;
    }

    public function getContactListByQuery(string $query = "", int $contact_id = null)
    {
        $sql = "SELECT 
                    HEX(AES_ENCRYPT(CAST(c.id as CHAR), :crypto_key)) as contact_id,
                    c.is_active as is_active,
                    c.display_name,
                    c.type,
                    ce.id AS ceid,
                    ce.email,
                    (SELECT GROUP_CONCAT(correspondent_id) 
                    FROM contact_paperless_map 
                    WHERE contact_id = c.id) AS linked_paperless_ids
                FROM contacts c
                LEFT JOIN contact_emails ce ON c.id = ce.contact_id
                WHERE c.tenant_id = :tenant_id
                  AND
                    (c.display_name like :search OR ce.email like :search)
                -- Hier kannst du nach Bedarf filtern
                ORDER BY c.display_name, ce.email";

        $dbResult = $this->queryIt(
            $sql,
            ["search" => "%" . $query . "%"],
            false,
            false,
            false
        );

        if (!$dbResult->success) {
            throw new \Exception("Datenbank-Fehler beim Abrufen der Kontaktliste: " . $dbResult->error);
        }

        $ret = $dbResult->data->fetched;
        $contacts = [];
        foreach ($ret as $row) {
            $cid = $row['contact_id'];
            if (!isset($contacts[$cid])) {
                $contacts[$cid] = [
                    'id' => $cid,
                    'paperless_id' => $row['paperless_id'],
                    'is_active' => $row['is_active'],
                    'displayname' => $row['display_name'],
                    'type' => $row['type'],
                    'emails' => [],
                    'paperless_ids' => $row['linked_paperless_ids']
                ];
            }
            if ($row['ceid']) {
                $contacts[$cid]['emails'][] = [
                    'ceid' => $row['ceid'],
                    'email' => $row['email']
                ];
            }
        }
        $finalContacts = array_values($contacts);
        return $finalContacts;
    }

    /**
     * Sucht einen Kontakt anhand der Mail-ID
     * @param string $hash: die verschlüsselte Mail-ID
     * @return array -> fetched
     */
    public function getContactByHash($hash)
    {
        $sql = "SELECT 
                    HEX(AES_ENCRYPT(CAST(c.id as CHAR), :crypto_key)) as contact_id,
                    c.is_active as is_active,
                    c.display_name as displayname,
                    c.type,
                    HEX(AES_ENCRYPT(CAST(ce.id as CHAR), :crypto_key)) as ceid,
                    ce.email
                FROM contact_emails ce
                LEFT JOIN contacts as c ON c.id=ce.contact_id
                WHERE c.tenant_id = :tenant_id
                  AND c.id=CAST(AES_DECRYPT(UNHEX(:hash), :crypto_key) AS UNSIGNED)
                -- Hier kannst du nach Bedarf filtern
                ORDER BY c.display_name, ce.email;";

        return  $this->queryIt(
            $sql,
            ["hash" => $hash],
            false,
            false,
            false
        );

    }

    /**
     * Sucht E-Mail
     */
    public function getEmailsBySearch(string $query)
    {
        $sql = "SELECT 
                    HEX(AES_ENCRYPT(CAST(c.id as CHAR), :crypto_key)) as contact_id,
                    c.is_active as is_active,
                    c.display_name,
                    c.type,
                    HEX(AES_ENCRYPT(CAST(ce.id as CHAR), :crypto_key)) as ceid,
                    ce.email
                FROM contacts c
                LEFT JOIN contact_emails ce ON c.id = ce.contact_id
                WHERE c.tenant_id = :tenant_id
                  AND ce.email like :search
                -- Hier kannst du nach Bedarf filtern
                ORDER BY c.display_name, ce.email";
        $dbResult = $this->queryIt(
            $sql,
            ["search" => "%" . $query . "%"],
            false,
            false,
            false
        );


        return $dbResult;
    }

    /**
     * Erzeugt die Timeline-Daten für einen Kontakt anhand der Mail-ID
     * @param mixed $id: die verschlüsselte Mail-ID
     * @return array -> Timeline-Daten
     */
    public function getTimelineData(mixed $id)
    {
        error_log(__FUNCTION__);
        
        $timeline = [];
        error_log("## " . __FUNCTION__ . " ## START");
        // ==========================================
        // 1. MAILS QUELLE
        // ==========================================

        $mail = $this->getService('mailProcess', \App\Services\MailProcessor::class);
        $mailItems = $mail->getMailItems($id);

        //error_log(print_r($mailItems, true));
        foreach ($mailItems as $row) 
        {
            $timeline[] = $this->formatItemTL(
                $row['type'],
                $row['id'],
                $row['mdate'] ?? null,
                $this->dashboard->decrypt($row['title']),
                $row['detail'] ?? '',
                '#hash-' . $row['id'],
                $row['id']
            );
        }

        //Notizen
        $note = new NotesProcessor($this->dashboard);
        $noteItems = $note->getNotesByMailId($id);

        foreach ($noteItems as $rowNote) {
            $timeline[] = $this->formatItemTL(
                'note',
                $rowNote['id'],
                !empty($rowNote['due_date']) ? $rowNote['due_date'] : (!empty($rowNote['created_at']) ? $rowNote['created_at'] : null),
                $rowNote['content'],
                $rowNote['content'],
                '#note-' . $rowNote['id'],
                $rowNote['id']
            );
        }
        //Appointments
        $appoint = new AppointmentsProcessor($this->dashboard);
        $appointItems = $appoint->getAppointmentsById($id);

        foreach ($appointItems as $rowAppoint) {
            $timeline[] = $this->formatItemTL(
                'appointment',
                $rowAppoint['id'],
                $rowAppoint['start_time'] ?? null,
                $rowAppoint['extracted_summary'],
                $rowAppoint['extracted_summary'],
                '#appointment-' . $rowAppoint['id'],
                $rowAppoint['id']
            );
        }


        // Paperless IDs im Vorfeld holen
        $paperless = new PaperlessProcessor($this->dashboard);
        $paperlessItems = $paperless->getPaperlessIdsByMailID($id);
        
        // ==========================================
        // 2. DOKUMENTE QUELLE: KORRESPONDENTEN
        // ==========================================
        $corres = $this->getDocumentItemsByCorrespondent($paperlessItems['korrespondenten'] ?? []);

        if (!empty($corres) && is_array($corres)) {
            foreach ($corres as $_cor) {
                $timeline[] = $this->formatItemTL(
                    'document',
                    $_cor['id'],
                    $_cor['created'] ?? $_cor['created_date'] ?? 'now',
                    $_cor['title'],
                    'Paperless Dokument',
                    '/dms/document/' . $_cor['id']
                );
            }
        } else {
            error_log(__FILE__ . ": " . __FUNCTION__ . ": keine Korrespondenten gefunden");
        }

        // ==========================================
        // 3. DOKUMENTE QUELLE: DIREKTE IDS
        // ==========================================
        $dokumenteIds = $paperlessItems['dokumente'] ?? [];
        error_log(print_r($dokumenteIds, true));

        if (!empty($dokumenteIds)) {
            $cleanIdsD = implode(",", array_map('intval', $dokumenteIds));
            $resDocs = $this->getDocumentsById($cleanIdsD);
            foreach ($resDocs as $_doc) {
                $timeline[] = $this->formatItemTL(
                    'document',
                    $_doc['id'],
                    $_doc['created'] ?? $_doc['created_date'] ?? 'now',
                    $_doc['title'],
                    'Paperless Dokument',
                    '/dms/document/' . $_doc['id']
                );
            }
        } else {
            error_log(__FILE__ . ": " . __FUNCTION__ . ": keine Dokumente gefunden");
        }

        // ==========================================
        // 4. DOKUMENTE QUELLE: DIREKTE IDS via TAGS
        // ==========================================
        $paperlessTags = $paperless->getTagsByContactTimeLine($id);
        error_log("DOCS BY TAGS");
        //error_log(print_r($paperlessTags, true));
        
        ## alle Dokumente nach Tags holen
        if (!empty($paperlessTags) && is_array($paperlessTags)) {
            foreach ($paperlessTags as $_tag) {
                $timeline[] = $this->formatItemTL(
                    'document',
                    $_tag['id'],
                    $_tag['created'] ?? $_cor['created_date'] ?? 'now',
                    $_tag['title'],
                    'Paperless Dokument',
                    '/dms/document/' . $_tag['id']
                );
            }
        } else {
            error_log(__FILE__ . ": " . __FUNCTION__ . ": keine Tags zu Mailkontakt gefunden");
        }

        // ==========================================
        // 5. SORTIERUNG & FINISH
        // ==========================================
        // Fix: Wir sortieren jetzt bombenfest nach dem echten Unix-Timestamp!

        usort($timeline, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        
        $TL = $this->buildTimeline($timeline);
        
        return $TL;
    }

    
    
    /**
     * Suche nach einer E-Mail-Adresse
     * @param string $query: die E-Mail-Adresse
     * @return array -> fetched
     */
    public function searchMailAddress(string $query)
    {
        // 2. Das SQL-Statement aufbauen
        // Da LIMIT und OFFSET im queryIt-Array oft als String übergeben würden (was MySQL crasht),
        // betten wir die vorab typisierten Zahlen direkt sicher in den String ein.
        $sql = "SELECT ce.email as mail, 
                  HEX(AES_ENCRYPT(CAST(ce.id as CHAR), :crypto_key)) as id, 
                  HEX(AES_ENCRYPT(CAST(ce.contact_id as CHAR), :crypto_key)) as contact_id, 
                  c.display_name as display_name, 
                  ce.email_hash as email_hash
                  FROM contact_emails as ce 
             LEFT JOIN contacts as c ON c.id = ce.contact_id
                 WHERE ce.tenant_id=:tenant_id 
                   AND ce.email like :search; ";

        // 3. Ausführung über deine native queryIt-Methode
        return $this->queryIt(
            $sql,
            [
                'search' => '%' . $query . '%',
            ],
            false,
            false,
            false
        );
    }


    /**
     * Sucht einen Kontakt anhand der Mail-ID
     * @param string $id: die verschlüsselte Mail-ID
     * @return array -> fetched
     */
    public function getContactByIdMail(string $id)
    {
        error_log(__FUNCTION__);

        $sql = "SELECT 
                    HEX(AES_ENCRYPT(CAST(c.id as CHAR), :crypto_key)) as contact_id,
                    c.display_name,
                    c.type,
                    GROUP_CONCAT(ce.email SEPARATOR ', ') AS additional_emails
                FROM mails AS m
                -- 1. Verbindung zur E-Mail-Tabelle über die sender_ceid
                INNER JOIN contact_emails AS ce ON m.sender_ceid = ce.id
                -- 2. Verbindung zur Kontakt-Tabelle
                INNER JOIN contacts AS c ON ce.contact_id = c.id
                WHERE m.id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED)
                AND m.tenant_id = :tenant_id
                GROUP BY c.id, c.display_name, c.type;";
            
        $dbResult = $this->queryIt(
            $sql,
            ["id" => $id],
            false,
            false,
            false
        );

        if ($dbResult->success && !empty($dbResult->data->fetched)) {
            return $dbResult->data->fetched;
        } else {
            return ["return" => $dbResult->error];
        }
    }



    /**
     * Dokumente aus Paperless (TimeLineData)
     * @param int $docIDs (holt alle Dokumente zu einem Korrespondenten)
     * return array of Documents
     */
    private function getDocumentItemsByCorrespondent($docIDs)
    {
        error_log(__FUNCTION__);
        if (empty($docIDs)) {
            return [];
        }
        // Hier logik für Paperless API
        $paperless = new PaperlessProcessor($this->dashboard);
        $docs = $paperless->fetchDocumentsByCorrespondentId($docIDs);
        return $docs;
    }

    /**
     * @deprecated
     */
    private function getDocumentItemsByTag($tagIds)
    {
        error_log(__FUNCTION__);

        if (empty($tagIds)) {
            return [];
        }

        // Hier logik für Paperless API
        $paperless = new PaperlessProcessor($this->dashboard);
        $docs = $paperless->getDocumentItemsByTag($tagIds);
        
        return $docs;
    }

    /**
     * Dokumente aus Paperless (TimeLineData)
     * @param int $contactId (holt alle Dokumente zu einem Tag)
     * return array of Documents
     */
    private function getTagsByContact($contactId)
    {
        error_log(__FUNCTION__);

        if (empty($contactId)) {
            return [];
        }

        // Hier logik für Paperless API
        $paperless = new PaperlessProcessor($this->dashboard);
        $docs = $paperless->getTagsByContact($contactId);
        
        return $docs;
    }

    /**
     * Dokumente aus Paperless (TimeLineData) anhand der DokumentenID
     * @param any $docIds (holt alle Dokumente anhand des $docids)
     */
    private function getDocumentsById($docIds)
    {
        if (empty($docIds)) {
            error_log(__FUNCTION__ . "keine docIds");
            return [];
        }

        $docs = array();
        // Hier logik für Paperless API
        $paperless = new PaperlessProcessor($this->dashboard);
        $docs = $paperless->getDocumentById($docIds);
        return $docs;
    }

    /**
     * Gibt ein einheitliches ITEM für die Timeline zurück
     */
    public function formatItemTL(string $type, $id, ?string $dateString, ?string $title, ?string $detail, ?string $link, ?string $hash = null): array
    {

        $eventDate = new \DateTime($dateString ?: 'now');

        // Dynamischer Farb-Index basierend auf der ID
        $colors = ['green', 'red', 'yellow', 'none'];
        $colorIndex = $id ? (int)$id % count($colors) : 0;
        return [
            'type'      => $type,
            'id'        => $id,
            // Mails bekommen Uhrzeit, Dokumente nur das Datum
            'date'      => $eventDate->format($type === 'mail' ? 'd.m.Y H:i' : 'd.m.Y'),
            'date_raw' => $eventDate->format('Y-m-d H:i:s'),
            'title'     => $title ?? 'Kein Titel',
            'detail'    => $detail,
            'is_recent' => ($eventDate >= new \DateTime('-30 days')),
            'hash'      => $hash ?? '',
            'critical'  => $colors[$colorIndex],
            'link'      => $link,
            'timestamp' => $eventDate->getTimestamp() // Unverzichtbar für das usort() oben
        ];
    }
    /**
     * 
     */
    private function buildTimeline(array $items)
    {
        $timeline = [];
        $monthsDe = [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];
        
        foreach ($items as $item) {
            $dateRaw = $item['date'] ?? '';

            // 2. Erstelle Objekt (Achtung: Format muss exakt zum String passen!)
            $date = DateTime::createFromFormat('d.m.Y H:i', $item['date']);
            if (!$date) {
                $date = DateTime::createFromFormat('d.m.Y', $item['date']);
            }
            // 3. Wenn $date kein DateTime-Objekt ist (also false), setze Fallback
            if ($date === false) {
                $year = 'Unbekannt';
                $month = 'Sonstiges';
            } else {
                $year = $date->format('Y');
                $month = $monthsDe[(int)$date->format('n')];
            }
            if (!isset($timeline[$year])) {
                $timeline[$year] = ['year' => $year, 'is_open' => false, 'months' => []];
            }
            if (!isset($timeline[$year]['months'][$month])) {
                $timeline[$year]['months'][$month] = ['name' => $month, 'is_open' => false, 'items' => []];
            }
            // Status setzen
            if ($item['is_recent']) {
                $timeline[$year]['is_open'] = true;
                $timeline[$year]['months'][$month]['is_open'] = true;
            }
            $timeline[$year]['months'][$month]['items'][] = $item;
        }
        // Sortierung und Bereinigung
        krsort($timeline);
        foreach ($timeline as &$yearData) {
            $yearData['months'] = array_values($yearData['months']);
        }
        return array_values($timeline);
    }
}
