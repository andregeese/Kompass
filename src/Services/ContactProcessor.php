<?php
/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
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
    public function __construct($dashboard) {
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
    public function insertContact(string $name, string $email, string $type) : int|bool
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
        $contactEmailId = $this->getContactEmailId($email, $tenantId);
        if ($contactEmailId) {
            return (int)$contactEmailId;
        }
        // 2. Wenn nein: Kontakt suchen oder anlegen
        $contactId = $this->getContactId($name, $email, $type);
        
        if (!$contactId) {
            // Kontakt neu anlegen
            $sqlContact = "INSERT INTO contacts (display_name, type, tenant_id, is_active) VALUES (:name, :type, :tenant_id, 1);";
            $dbResult = $this->queryIt($sqlContact, ['name' => $name, 'type' => $type, 'tenant_id' => $tenantId], true, true, false);
            
            if (!$dbResult->success) return false;
            $contactId = $dbResult->data->lastId;
        }
        // 3. E-Mail Eintrag erzwingen (da wir hier gelandet sind, existiert sie noch nicht für diesen Tenant)
        return $this->insertContactEmail($contactId, $email, $salt);
    }
    /**
     * Sucht in der DB nach einem Kontakt mit genau diesem Namen, E-Mail und Typ.
     */
    private function getContactId($name, $email, $type):int|bool
    {
        #error_log("Suche Kontakt: Name='$name', Email='$email', Type='$type'");
        $sql = "SELECT id FROM contacts WHERE tenant_id = :tenant_id AND display_name = :name AND type = :type LIMIT 1;";
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
        return ($dbResult->success && !empty($dbResult->data->fetched)) ? $dbResult->data->fetched[0]["id"] : false;
        
    }
    
    private function getContactEmailId($email, $tenantId): int|bool
    {
        // Sicherheitscheck: Ohne TenantId ist die Suche in einer Multi-Tenant Umgebung ungültig
        if (empty($tenantId)) return false;
        $sql = "SELECT id FROM contact_emails WHERE tenant_id = :tenant_id AND email = :email LIMIT 1;";
        $dbResult = $this->queryIt($sql, ["tenant_id" => $tenantId, "email" => $email], false, false, false);
        return ($dbResult->success && !empty($dbResult->data->fetched)) ? (int)$dbResult->data->fetched[0]["id"] : false;
    }
    private function insertContactEmail($contactId, $email, $salt)
    {
        #error_log("insertContactEmail aufgerufen mit: ContactID='$contactId', Email='$email'");
        $tenantId = $_SESSION['tenant_id'] ?? null;
        if ($tenantId === null
            || empty($email) 
            //|| !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) 
            {
            error_log("Ungültige Daten für insertContactEmail: ContactID='$contactId', Email='$email', TenantID='$tenantId'");
            return false;
        }
        $ceId = $this->getContactEmailId($email, $tenantId);
        if (!$ceId) {
            $sqlEmail = "INSERT INTO contact_emails (contact_id, email, email_hash, tenant_id) 
                    VALUES (:cid, :email, :hash, :tenant_id);";
            
            $dbResult = $this->queryIt(
                $sqlEmail,
                [
                    'cid' => $contactId,
                    'email' => $email,
                    'hash' => hash('sha256', $email . $salt),
                    'tenant_id' => $tenantId
                ],
                true,
                true,
                false
            );
            $ceId = $dbResult->success ? $dbResult->data->lastId : error_log("Kontakt-E-Mail konnte nicht angelegt werden: " . $email);
        }
        
        return $ceId;
    }
    /**
     * Kontakte anhand von hashed ID
     * wird benötigt um den Tree in right_sidebar zu erstellen
     */
    public function getEmailsAndContactsByHash($hash)
    {
        $sql ="SELECT 
                c.id AS contact_id,
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
        $ret = $this->queryIt($sql,
            ["hash" =>"%" . $hash . "%"]
        );
        return $ret;
    }
    /**
     * OBSOLET
     * Speicher die Kontakte und Mails
     * 1 Konakt n Emails
     */
    public function saveContactEmails($target_contact_id, $emails)
    {
        $tenant_id = is_int($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : "";
        error_log("--- START: " . __FUNCTION__ . " für Kontakt: $target_contact_id ---");
        error_log(print_r($emails, true));
        if ($tenant_id==="") {
            error_log("--- TENANT ID ist leer ---");
            return ['success' => false, 'message' => 'Keine E-Mails'];
        }
        if (empty($emails)) {
            error_log("Keine E-Mails übergeben.");
            return ['success' => false, 'message' => 'Keine E-Mails'];
        }
    
        $email_list = array_column($emails, 'email');
        
        // Nutze $email_list statt $emails
        $quotedEmails = array_map(function($email) {
            return "'" . addslashes($email) . "'";
        }, $email_list); // Hier auf email_list zeigen!
        $mails_string = implode(',', $quotedEmails);
        
        $sql = "UPDATE contact_emails 
                SET contact_id = :contact_id 
                WHERE tenant_id = :tenant_id AND email IN ($mails_string)";
        
        $params = [
            'contact_id' => $target_contact_id,
            'tenant_id'  => $tenant_id
        ];
        $this->queryIt($sql, $params, true, false, true);
        
        error_log("Erfolgreich umgehängt: " . implode(', ', $email_list) . " auf Kontakt $target_contact_id");
        // 3. Status der alten Kontakte prüfen & inaktiv setzen
        // Wir identifizieren Kontakte, die nun keine E-Mails mehr besitzen
        $this->cleanupOrphanedContacts($tenant_id);
        error_log("--- END: saveContactEmails erfolgreich abgeschlossen ---");
        
        return ['success' => true];
    }
    /**
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
    public function getAllEmails()
    {
        $sql = "SELECT 
                    c.id AS contact_id,
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
                            
        $dbRequest = $this->queryIt($sql,
            [], false, false, false
        );
        
        if (!$dbRequest->success)
        {
            throw new \Exception("Keine Mails gefunden!");
        }
        return $dbRequest->data->fetched;
    }
    /**
     * Bekommt eine Liste von Kontakten und dessen Verknüpfungen
     */
    public function getContactListByContactID(int $contact_id)
    {
        error_log(__FUNCTION__);
        $sql = "SELECT 
                    c.id AS contact_id,
                    c.is_active as is_active,
                    c.display_name,
                    c.type,
                    ce.id AS ceid,
                    ce.email
                FROM contacts c
                LEFT JOIN contact_emails ce ON c.id = ce.contact_id
                WHERE c.tenant_id = :tenant_id
                  AND ce.contact_id=:contact_id
                -- Hier kannst du nach Bedarf filtern
                ORDER BY c.display_name, ce.email";
                            
        $dbRequest = $this->queryIt($sql,
            ["contact_id" => $contact_id], false, false, false
        );
        
        if (!$dbRequest->success)
        {
            return [];
        }
        $ret = $dbRequest->data->fetched;
        $contacts=[];
        foreach ($ret as $row) {
            $cid = $row['contact_id'];
            if (!isset($contacts[$cid])) {
                $contacts[$cid] = [
                    'id' => $cid,
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
    public function getContactList(string $query="", int $contact_id=null)
    {
        error_log(__FUNCTION__);
        $sql = "SELECT 
                    c.id AS contact_id,
                    c.is_active as is_active,
                    c.display_name,
                    c.type,
                    ce.id AS ceid,
                    ce.email
                FROM contacts c
                LEFT JOIN contact_emails ce ON c.id = ce.contact_id
                WHERE c.tenant_id = :tenant_id
                  AND
                    (c.display_name like :search OR ce.email like :search)
                -- Hier kannst du nach Bedarf filtern
                ORDER BY c.display_name, ce.email";
        $dbResult = $this->queryIt($sql,
            ["search" =>"%" . $query . "%"], false, false, false
        );
        if (!$dbResult->success){
            return false;
        }
        $ret = $dbResult->data->fetched;
        $contacts=[];
        foreach ($ret as $row) {
            $cid = $row['contact_id'];
            if (!isset($contacts[$cid])) {
                $contacts[$cid] = [
                    'id' => $cid,
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
    /**
     * HASH von der Mail?
     */
    public function getContactByEmailHash($hash)
    {
        $sql = "SELECT 
                    c.id AS contact_id,
                    c.is_active as is_active,
                    c.display_name as displayname,
                    c.type,
                    ce.id AS ceid,
                    ce.email
                FROM contact_emails ce
                LEFT JOIN contacts as c ON c.id=ce.contact_id
                
                WHERE c.tenant_id = :tenant_id
                  AND ce.email_hash=:hash
                -- Hier kannst du nach Bedarf filtern
                ORDER BY c.display_name, ce.email";
        $dbResult = $this->queryIt($sql,
            ["hash" => $hash ], false, false, true
        );
        
        if (!$dbResult->success)
        {
            $this->dashboard->sendResponse(false,null, print_r($dbResult->error, true),'error');
        }
        $finalContacts = $dbResult->data->fetched;
        $this->dashboard->sendResponse(true, ["contacts" => $finalContacts], false, 'success');
        
    }
    public function getEmailsBySearch($query)
    {
        $sql = "SELECT 
                    c.id AS contact_id,
                    c.is_active as is_active,
                    c.display_name,
                    c.type,
                    ce.id AS ceid,
                    ce.email
                FROM contacts c
                LEFT JOIN contact_emails ce ON c.id = ce.contact_id
                WHERE c.tenant_id = :tenant_id
                  AND ce.email like :search
                -- Hier kannst du nach Bedarf filtern
                ORDER BY c.display_name, ce.email";
        $dbResult = $this->queryIt($sql,
            ["search" =>"%" . $query . "%"], false, false, false
        );
        
        if (!$dbResult->success)
        {
            $this->dashboard->sendResponse(false,null, print_r($dbResult->error, true),'error');
        }
        $finalContacts = $dbResult->data->fetched;
        return $finalContacts;
    }
    /**
     * Erzeugt die TimeLine von Kontakten
     */
    public function getTimelineData(mixed $hash, string $salt)
    {
        error_log(__FUNCTION__);
        $salt ??= $this->dashboard->getHashSalt();
        
        $tenantId = $_SESSION['tenant_id'];
        $timeline = [];
        error_log("## " . __FUNCTION__ . " ## START");
        // ==========================================
        // 1. MAILS QUELLE
        // ==========================================
        $mailItems = $this->getMailItems($hash, $salt, $tenantId);
        //error_log(print_r($mailItems, true));
        foreach ($mailItems as $row) {
            $timeline[] = $this->formatItemTL(
                $row['type'],
                $row['id'],
                $row['mdate'] ?? null,
                $this->dashboard->decrypt($row['title']),
                $row['detail'] ?? '',
                '#hash-' . $row['hash'],
                $row['hash']
            );
        }
        //Notizen
        $notes = $this->getNotesByMailHash($hash);
        
        foreach ($notes as $rowNote) {
            error_log(print_r($rowNote, true));
            $timeline[] = $this->formatItemTL(
                'note',
                $rowNote['id'],
                $rowNote['updated_date'] ?? null,
                $rowNote['content'],
                $rowNote['content'],
                '#note-' . $rowNote['id'],
                $rowNote['id']
            );
        }
        //Appointments
        $appoints = $this->getAppointmentsByHash($hash);
        error_log(print_r($appoints, true));
        foreach ($appoints as $rowAppoint) {
            error_log(print_r($rowAppoint, true));
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
        $paperlessIDs = $this->getPaperlessIdsByMailHash($hash, $salt, $tenantId);
        //error_log(print_r($paperlessIDs, true));
        // ==========================================
        // 2. DOKUMENTE QUELLE: KORRESPONDENTEN
        // ==========================================
        $corres = $this->getDocumentItemsByCorrespondent($paperlessIDs['korrespondenten'] ?? []);
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
        $dokumenteIds = $paperlessIDs['dokumente'] ?? [];
        //error_log(print_r($dokumenteIds, true));
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
        // 4. SORTIERUNG & FINISH
        // ==========================================
        // Fix: Wir sortieren jetzt bombenfest nach dem echten Unix-Timestamp!
        
        usort($timeline, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        //error_log(print_r($timeline,true));
        //error_log("buildTL");
        $TL = $this->buildTimeline($timeline);
        //error_log(print_r($TL, true));
        error_log("buildedTL");
        return $TL;   
    }
    private function getNotesByMailHash($hash)
    {
        // alle Notizen zu einer Mail
        $sql = "SELECT DISTINCT n.*
                FROM `notes` n
                -- 1. Finde alle E-Mail-Adressen/IDs, die zum selben Kontakt gehören
                JOIN `contact_emails` ce_all 
                ON (n.ceid = ce_all.id) -- OR LOWER(TRIM(n.email)) = LOWER(TRIM(ce_all.email)))
                -- 2. Schlage die Brücke zum gemeinsamen Hauptkontakt (contacts)
                JOIN `contact_emails` ce_current_mail 
                ON ce_all.contact_id = ce_current_mail.contact_id
                -- 3. Knüpfe an die aktuell in der Sidebar geöffnete Mail an (über sender oder recipient)
                JOIN `mails` m 
                ON (m.sender_ceid = ce_current_mail.id OR m.recipient_ceid = ce_current_mail.id)
                -- 4. Filter für die Sidebar
                WHERE m.id_hash = UNHEX(:hash)
                AND m.tenant_id = :tenant_id
                AND n.tenant_id = :tenant_id
                -- Neueste Notizen immer zuerst anzeigen
                ORDER BY n.created_at DESC;
        );";
        $params =['hash' => $hash];
        $dbResult = $this->queryIt($sql, $params,false, false, false);
        if (!$dbResult->success){
            throw new \Exception("Fehler bei Notiz!");
        }
        return $dbResult->data->fetched;
    }
    private function getAppointmentsByHash($hash)
    {
        error_log("Lade alle Appointments");
        // alle Notizen zu einer Mail
        $sql = "SELECT DISTINCT 
                a.*,
                TRIM(
                    SUBSTRING_INDEX(
                        SUBSTRING_INDEX(
                            REPLACE(REPLACE(a.ical_data, '\r', ''), '\n', '<BR>'), 
                        'SUMMARY:', -1), 
                    '<BR>', 1)
                ) AS extracted_summary
                FROM `appointments` a
                -- 1. Finde alle E-Mail-Adressen/IDs, die zum selben Kontakt gehören
                JOIN `contact_emails` ce_all 
                ON (a.ceid = ce_all.id) -- OR LOWER(TRIM(n.email)) = LOWER(TRIM(ce_all.email)))
                -- 2. Schlage die Brücke zum gemeinsamen Hauptkontakt (contacts)
                JOIN `contact_emails` ce_current_mail 
                ON ce_all.contact_id = ce_current_mail.contact_id
                -- 3. Knüpfe an die aktuell in der Sidebar geöffnete Mail an (über sender oder recipient)
                JOIN `mails` m 
                ON (m.sender_ceid = ce_current_mail.id) -- OR m.recipient_ceid = ce_current_mail.id)
                -- 4. Filter für die Sidebar
                WHERE m.id_hash = UNHEX(:hash)
                AND m.tenant_id = :tenant_id
                AND a.tenant_id = :tenant_id
                -- Neueste Notizen immer zuerst anzeigen
                ORDER BY a.updated_at DESC;";
        $params =['hash' => $hash];
        $dbResult = $this->queryIt($sql, $params,false, false, true);
        if (!$dbResult->success){
            throw new \Exception("Fehler bei Appointment!");
        }
        return $dbResult->data->fetched;
    }
    /**
     * Suche nach einer E-Mail-Adresse
     * @param string $query: die E-Mail-Adresse
     * @return array -> fetched
     */
    public function searchMailAddress(string $query):array
    {
        // 2. Das SQL-Statement aufbauen
        // Da LIMIT und OFFSET im queryIt-Array oft als String übergeben würden (was MySQL crasht),
        // betten wir die vorab typisierten Zahlen direkt sicher in den String ein.
        $sql = "SELECT * 
                FROM contact_emails as ce 
                LEFT JOIN contacts as c ON c.id = ce.contact_id
                WHERE ce.email like :search; ";
        
    
        // 3. Ausführung über deine native queryIt-Methode
        $dbResult = $this->queryIt($sql, [
            'search' => '%' . $query . '%',
        ],
        false,
        false,
        false);
        if(!$dbResult->success){
            return [];
        }
        return $dbResult->data->fetched;    
    }
    /**
     * Email und Kontakt
     */
    private function getMailItems($hash, $salt, $tenantId)
    {
        error_log(__FUNCTION__);
        
        $sql = "SELECT CASE 
                        WHEN m.recipient_ceid = ce.id THEN 'mail_send'
                        ELSE 'mail'
                    END AS type,
                    m.id AS id,
                    m.id_hash as idhash,
                    LOWER(HEX(id_hash)) as hash,
                    m.paperless_id AS paperlessdocument,
                    m.date_received AS mdate,
                    m.subject_hash AS title,
                    m.sender AS detail
                    FROM mails m
                    -- Join, um alle ceids zu finden, die zum Kontakt der Ausgangs-Mail gehören
                    JOIN contact_emails ce ON (m.sender_ceid = ce.id OR m.recipient_ceid = ce.id)
                    WHERE ce.contact_id IN (
                        -- Subquery: Finde den Kontakt, der zur Ausgangs-Mail gehört
                        SELECT contact_id 
                        FROM contact_emails 
                        WHERE id IN (
                            SELECT sender_ceid 
                            FROM mails 
                            WHERE id_hash = UNHEX(:hash)
                        )
                    )
                    AND m.tenant_id = :tenant_id 
                    AND m.status != 'deleted'
                    ORDER BY m.date_received DESC
                    LIMIT 1000;"; // Dein SQL von oben
        $dbResult = $this->queryIt(
            $sql, 
            [
                "hash" => $hash
            ],
            false,
            false,
            false
        );
        return ($dbResult->success && !empty($dbResult->data) && !empty($dbResult->data->fetched)) ?
                $dbResult->data->fetched :
                [];
        
    }
    private function getPaperlessIdsByMailHash(string $hash, string $salt, int $tenantId): array
    {
        error_log(__FUNCTION__);
        $salt = $this->dashboard->getHashSalt();
        $sql = "SELECT 
                GROUP_CONCAT(DISTINCT m.paperless_id) as dokumente,
                GROUP_CONCAT(DISTINCT c.paperless_id) AS korrespondenten
                -- CASE 
                --     WHEN m.paperless_id IS NOT NULL THEN 'linked' 
                --     ELSE 'standalone' 
                -- END AS relation_type
            FROM mails AS m
            -- 1. Von Mail zu der E-Mail-Adresse, von der sie kam
            INNER JOIN contact_emails ce ON m.sender_ceid = ce.id
            -- 2. Von der E-Mail-Adresse zum Kontakt
            INNER JOIN contacts c ON ce.contact_id = c.id
            -- 3. Vom Kontakt zu den Paperless-Mappings
            -- LEFT JOIN contact_paperless_mappings cpm ON c.id = cpm.contact_id
            WHERE m.id_hash = UNHEX(:hash) AND m.tenant_id=:tenant_id;";
        $dbResult = $this->queryIt($sql, ['hash' => $hash, 'tenant_id' => $tenantId]);
        // 1. Prüfen, ob überhaupt ein Ergebnis da ist
        if (!empty($dbResult->data) && is_array($dbResult->data->fetched)) {
            // Da GROUP_CONCAT nur eine Zeile liefert, nehmen wir den ersten Index [0]
            $row = $dbResult->data->fetched[0];
            // Filtert leere Strings (falls nur NULL vorhanden war) und gibt ein sauberes Array
            $resAr['dokumente'] = array_filter(explode(',', $row['dokumente'] ?? ''));
            $resAr['korrespondenten'] = array_filter(explode(',', $row['korrespondenten'] ?? ''));
        } else {
            $resAr['dokumente'] = [];
            $resAr['korrespondenten'] = [];
        }
        #error_log(print_r($resAr, true));
        // Wandle das Ergebnis-Array in ein flaches Array von IDs um
        return $resAr;
    }
    public function getContactByMail(string $hash)
    {
        error_log(__FUNCTION__);
        $sql="SELECT 
                    c.id AS contact_id,
                    c.display_name,
                    c.type,
                    c.paperless_id,
                    GROUP_CONCAT(ce.email SEPARATOR ', ') AS additional_emails
                FROM mails AS m
                -- 1. Verbindung zur E-Mail-Tabelle über die sender_ceid
                INNER JOIN contact_emails AS ce ON m.sender_ceid = ce.id
                -- 2. Verbindung zur Kontakt-Tabelle
                INNER JOIN contacts AS c ON ce.contact_id = c.id
                WHERE m.id_hash = UNHEX(:hash)
                AND m.tenant_id = 6
                GROUP BY c.id, c.display_name, c.type, c.paperless_id;";
        $dbResult = $this->queryIt($sql,
            ["hash" => $hash],
            false,
            false,
            false
        );
        
        if ($dbResult->success && !empty($dbResult->data->fetched)){
            return $dbResult->data->fetched;
        }else{
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
     * Dokumente aus Paperless (TimeLineData) anhand der DokumentenID
     * @param any $docIds (holt alle Dokumente anhand des $docids)
     */
    private function getDocumentsById($docIds)
    {
        if (empty($docIds)) {
            error_log(__FUNCTION__ . "keine docIds");
            return [];
        }
        
        $docs= array();
         // Hier logik für Paperless API
        $paperless = new PaperlessProcessor($this->dashboard);
        $docs = $paperless->getDocumentById($docIds);
        return $docs;
    }
    /**
     * Gibt ein einheitliches ITEM für die Timeline zurück
     */
    private function formatItemTL(string $type, $id, ?string $dateString, ?string $title, ?string $detail, ?string $link, ?string $hash = null): array
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
?>