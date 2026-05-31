<?php
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

    
    private $imapStream = null;

    public function autoLinkContact($data) 
    {
        $email = strtolower(trim($data['email']));
        $emailHash = hash('sha256', $email . HASH_SALT);
        
        // 1. Kontakt suchen oder erstellen (wie gehabt)
        $check = $this->queryIt("SELECT contact_id FROM contact_emails WHERE email_hash = :hash AND tenant_id = :tenant_id", [
            'hash' => $emailHash
        ]);

        $contactId = !empty($check) ? $check[0]['contact_id'] : $this->insertContact($data['name'], $email, 'company');

        // 2. Verknüpfung in mails über den sender_hash
        if ($idHash) {
            $binHash = hex2bin($data['hash']);
            $searchHash = hash('sha256', $messageId . $salt, true);
            $ret = $this->queryIt(
                "UPDATE mails SET contact_id = :cid WHERE id_hash = :hash AND tenant_id = :tenant_id",
                [
                    'hash' => $binHash
                ]
            ,true, false);
        }
    
        return ['success' => true, 'contact_id' => $contactId, "details"=> $ret];
    
    }

    /**
    * Legt einen neuen Hauptkontakt und die zugehörige E-Mail-Adresse an.
    * * @param string $name Der Anzeigename des Kontakts
    * @param string $email Die E-Mail-Adresse
    * @param string $type Typ des Kontakts ('company' oder 'person')
    * @param int $paperless_id Paperless-Verknüpfung
    * @return int|bool Die neue Contact-ID bei Erfolg, sonst false
    */
    public function insertContact(string $name, string $email, string $type = 'company', int $paperless_id=null) {
        $name = trim($name);
        $email = strtolower(trim($email));
        $tenantId = $_SESSION['tenant_id'] ?? null;
        $salt = $this->dashboard->getHashSalt();

        if (empty($name) || empty($email) || !$tenantId) {
            return false;
        }
                
        // 1. Kontakt anlegen
        // Nutze LAST_INSERT_ID() oder PDO::lastInsertId(), um die ID sofort zu bekommen
        $sqlContact = "INSERT INTO contacts (display_name, type, tenant_id, paperless_id) 
                VALUES (:name, :type, :tenant_id, :paperless_id) 
                ON DUPLICATE KEY UPDATE 
                    paperless_id = VALUES(paperless_id),
                    type = VALUES(type);";
        
        $contactId = $this->queryIt(
            $sqlContact, 
            ['name' => $name, 'type' => $type, 'tenant_id' => $tenantId, 'paperless_id' => $paperless_id]
            ,false
            , true
        );

        //error_log("contact ID nach speichern 1:" . print_r($contactId, true));
        if (empty($contactId)) {
            $sqlGetId = "SELECT id FROM contacts WHERE display_name = :name AND tenant_id = :tenant_id LIMIT 1";
            $result = $this->queryIt($sqlGetId, ['name' => $name, 'tenant_id' => $tenantId]);
            
            // Angenommen, queryIt gibt bei Selects ein Array zurück
            $contactId = $result[0]['id'] ?? null;
        
        }

        if (!$contactId) {
            throw new Exception("Kontakt konnte weder angelegt noch gefunden werden.");
        }

        //error_log("SELECT ID nach speichern 2:" . print_r($contactId, true));

        // 2. E-Mail-Adresse speichern
        $emailHash = hash('sha256', $email . $salt);
        
        $sqlEmail = "INSERT INTO contact_emails (contact_id, email, email_hash, tenant_id) 
                    VALUES (:cid, :email, :hash, :tenant_id)  
                    ON DUPLICATE KEY UPDATE 
                    contact_id = VALUES(contact_id),
                    email = VALUES(email);";

        $this->queryIt($sqlEmail, [
            'cid'       => $contactId,
            'email'     => $email,
            'hash'      => $emailHash,
            'tenant_id' => $tenantId
        ]);

        return $contactId;
    }


    /**
     * Kontakte anhand von hashed ID
     * wird benötigt um den Tree in right_sidebar zu erstellen
     */
    public function getEmailsAndContactsByHash($hash)
    {
        $sql ="SELECT 
                c.id AS contact_id,
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
     * Speicher die Kontakte und Mails
     * 1 Konakt n Emails
     */
    public function saveContactEmails($contact_id, $emails)
    {
        
        $contact_ids=[];
        $email_list = [];
        error_log("saveContactEmails");
        error_log(print_r($emails, true));

        foreach ($emails as $_email)
        {
            error_log(__FILE__ . ": " . __LINE__ . " => " . print_r($_email, true));

            if (is_array($_email) && isset($_email['id'])) {
                $contact_ids[] = $_email['id'];
            }
            if (is_array($_email) && isset($_email['email'])) {
                $email_list[] = $_email['email'];
            }

            //Verknüpfung auf sich selbst
            $sqlClear = "UPDATE contact_emails SET contact_id = :ce_id WHERE tenant_id = :tenant_id AND contact_id = :contact_id";
            $this->queryIt(
                $sqlClear, ["contact_id" => $contact_id, 'ce_id' => $_email['ceid']]
            ,true, false);
        };

        $ids_string = implode(',', array_map('intval', $contact_ids));
        

        // --- SCHRITT 2: NEU ZUORDNEN ---
        if (!empty($email_list)) {
            $quoted_emails = array_map(function($email) {
                return "'" . addslashes($email) . "'";
            }, $email_list);
            $mails_string = implode(',', $quoted_emails);

            $sqlCE = "UPDATE contact_emails 
                    SET contact_id = :contact_id 
                    WHERE tenant_id = :tenant_id 
                    AND email IN ($mails_string)";
            
            $this->queryIt($sqlCE, ["contact_id" => $contact_id],true, false);
        }

        //3. contct aktualisieren
        $sqlC = "UPDATE contacts SET is_active =0 WHERE tenant_id=:tenant_id AND id IN ($ids_string);";
        $this->queryIt($sqlC, [],true, false);
        error_log("idstring:" . print_r($ids_string, true));


        $quoted_emails = array_map(function($email) {
            return "'" . addslashes($email) . "'";
        }, $email_list);

        $mails_string = implode(',', $quoted_emails); // Ergebnis: 'a@b.com','c@d.com'

        $sqlCE = "UPDATE contact_emails SET contact_id =:contact_id WHERE tenant_id=:tenant_id AND email IN($mails_string);";
        $ret = $this->queryIt($sqlCE, ["contact_id" => $contact_id], true, false);

        return [
            'success' => true, 
            'details' => $ret
        ];
            
        
    }

    /**
     * Bekommt eine Liste von Kontakten und dessen Verknüpfungen
     */
    public function getContactListByContactID(int $contact_id)
    {
        $sql = "SELECT 
                    c.id AS id,
                    c.is_active AS is_active,
                    c.display_name AS displayname,
                    c.paperless_id AS paperless_id,
                    c.type AS type, 
                    GROUP_CONCAT(DISTINCT ce.email SEPARATOR ', ') AS additional_emails,
                    -- Logik: 1, wenn es Einträge in contact_emails gibt, 
                    -- deren contact_id nicht mit der c.id übereinstimmt 
                    -- (oder eine andere Bedingung deiner Wahl)
                    MAX(CASE WHEN ce.contact_id IS NOT NULL AND ce.id != c.id THEN 1 ELSE 0 END) AS is_connected
                FROM contacts AS c
                LEFT JOIN contact_emails AS ce ON c.id = ce.contact_id
                WHERE c.tenant_id = :tenant_id
                AND c.id = :contact_id
                GROUP BY ce.id, c.is_active, c.display_name, c.paperless_id, c.type";
                            
        $ret = $this->queryIt($sql,
            ["contact_id" => $contact_id]
        );
        return $ret;
    }

    public function getContactList(string $query="", int $contact_id=null)
    {
        $sql = "SELECT 
                    c.id AS id,
                    ce.id AS ceid,
                    c.is_active AS is_active,
                    c.display_name AS displayname,
                    c.paperless_id AS paperless_id,
                    ce.email,
                    c.type AS type, 
                    -- GROUP_CONCAT(DISTINCT ce.email SEPARATOR ', ') AS additional_emails,
                    -- Logik: 1, wenn es Einträge in contact_emails gibt, 
                    -- deren contact_id nicht mit der c.id übereinstimmt 
                    -- (oder eine andere Bedingung deiner Wahl)
                    MAX(CASE WHEN ce.contact_id IS NOT NULL AND ce.id != c.id THEN 1 ELSE 0 END) AS is_connected
            FROM contacts AS c
            -- INNER JOIN mails AS m ON c.id = m.contact_id
            LEFT JOIN contact_emails AS ce ON c.id = ce.contact_id
            WHERE c.tenant_id =:tenant_id 
            -- AND id_hash = UNHEX(:hash)
            AND (ce.email LIKE :search OR c.display_name LIKE :search)
            GROUP BY c.id, ce.id, c.is_active, ce.email, c.display_name, c.paperless_id, c.type;";

        $ret = $this->queryIt($sql,
            ["search" =>"%" . $query . "%"]
        );
        return $ret;
    }

    /**
     * Erzeugt die TimeLine von Kontakten
     */
    public function getTimelineData(mixed $hash, string $salt, int $tenantId)
    {
        if ($salt===null)
        {
            $salt = $this->dashboard->getHashSalt();
        }
        
        // 1. Daten aus allen Quellen sammeln und normalisieren
        $items = [];
        //Holt alle Mails
        $mails = $this->getMailItems($hash, $salt, $tenantId);
        $mailItems = array_merge($items, $mails);
        
        $timeline = [];

        foreach ($mailItems as $row) {
            $timeline[] = [
                'type'      => 'mail',
                'id'        => $row['id'],
                'date'      => date('d.m.Y H:i', strtotime($row['date'])),
                'title'     => $this->dashboard->decrypt($row['title']),
                'detail'    => $row['detail'],
                'is_recent' => true, // oder deine Logik
                'hash'      => $hash,
                'link'      => '#hash-' . $hash
            ];
        }

        //hole die Correspondenten aus der DB contact
        $PaperlessIDs = $this->getPaperlessIdsByMailHash($hash, $salt, $tenantId);

        //prüfe welche Dokumente es zu den Paperless-IDs gibt
        $corres = $this->getDocumentItemsByCorrespondent($PaperlessIDs['korrespondenten']);

        // 2. Prüfung: Ist es ein Array und enthält es Daten?
        if (is_array($corres) && !empty($corres)) {
            // 2. Paperless Dokumente mappen (hier machst du das gleiche für $docs)
            foreach ($corres as $_cor) {
                $datumString = $_cor['created'] ?? $_cor['created_date'] ?? date('Y-m-d');
                $timeline[] = [
                    'type'      => 'document',
                    'id'        => $_cor['id'],
                    'date'      => date('d.m.Y', strtotime($datumString)),
                    'title'     => $_cor['title'],
                    'detail'    => 'Paperless Dokument',
                    'is_recent' => false,
                    'hash'      => '',
                    'link'      => '/dms/document/' . $_cor['id']
                ];
            }
        }else{
            error_log(__FILE__ . ": " . __FUNCTION__ . ": keine Korrespondenten gefunden");
        }

        //prüfe welche Dokumente es zu den Paperless-IDs gibt
        $dokumenteIds = $PaperlessIDs['dokumente'] ?? [];

        // 2. Prüfung: Ist es ein Array und enthält es Daten?
        if (!empty($dokumenteIds)) {
            $cleanIdsD = implode(",", array_map('intval', $dokumenteIds));

            $resDocs = $this->getDocumentsById($cleanIdsD);

            foreach ($resDocs as $_doc) {
                $datumString = $_doc['created'] ?? $_doc['created_date'] ?? '';
                $timeline[] = [
                    'type'      => 'document',
                    'id'        => $_doc['id'],
                    'date'      => date('d.m.Y', strtotime($datumString)),
                    'title'     => $_doc['title'],
                    'detail'    => 'Paperless Dokument',
                    'is_recent' => false,
                    'hash'      => '',
                    'link'      => '/dms/document/' . $_doc['id']
                ];
            }
        }else{
            error_log(__FILE__ . ": " . __FUNCTION__ . ": keine Dokumente gefunden");
        }

        usort($timeline, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });
        
        // 2. Timeline aufbauen
        return $this->buildTimeline($timeline);
    }

    /**
     * Email und Kontakt
     */
    private function getMailItems($hash, $salt, $tenantId)
    {
        
        $sql = "SELECT 
                    'mail' AS type,
                    m.id AS item_id,
                    ref.paperless_id as paperlesscontact,
                    -- ce.paperless_id as paperlesscorrespondent
                    m.paperless_id as paperlessdocument,
                    m.date_received AS event_date,
                    m.subject_hash AS title,
                    m.sender AS detail
                FROM mails AS m
                INNER JOIN (
                    SELECT sender_ceid, recipient_ceid, paperless_id FROM mails WHERE id_hash = UNHEX(:hash) LIMIT 1
                ) AS ref ON m.sender_ceid = ref.sender_ceid AND m.recipient_ceid = ref.recipient_ceid
                WHERE m.tenant_id = :tenant_id AND m.status NOT IN ('deleted')
                ORDER BY m.date_received DESC LIMIT 100;"; // Dein SQL von oben
        $rawMails = $this->queryIt($sql, ["hash" => $hash, "tenant_id" => $tenantId]);
        //error_log(print_r($rawMails,true));

        $items = [];
        foreach ($rawMails as $row) {
            $items[] = $this->formatItem($row, 'mail', $row['event_date'], $row['detail'], $hash);
        }
        return $items;
    }

    private function getPaperlessIdsByMailHash(string $hash, string $salt, int $tenantId): array
    {
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

        $result = $this->queryIt($sql, ['hash' => $hash, 'tenant_id' => $tenantId]);
        // 1. Prüfen, ob überhaupt ein Ergebnis da ist
        if (!empty($result) && is_array($result)) {
            // Da GROUP_CONCAT nur eine Zeile liefert, nehmen wir den ersten Index [0]
            $row = $result[0];

            // Filtert leere Strings (falls nur NULL vorhanden war) und gibt ein sauberes Array
            $resAr['dokumente'] = array_filter(explode(',', $row['dokumente'] ?? ''));
            $resAr['korrespondenten'] = array_filter(explode(',', $row['korrespondenten'] ?? ''));
        } else {
            $resAr['dokumente'] = [];
            $resAr['korrespondenten'] = [];
        }

        error_log(print_r($resAr, true));
        // Wandle das Ergebnis-Array in ein flaches Array von IDs um
        return $resAr;
    }


    public function getContactByMail(string $hash)
    {
        $sql="SELECT 
                c.id AS contact_id,
                c.display_name,
                c.type,
                GROUP_CONCAT(DISTINCT ce.email SEPARATOR ', ') AS additional_emails,
                GROUP_CONCAT(DISTINCT c.paperless_id SEPARATOR ', ') AS paperless_ids
            FROM contacts AS c
            /* 1. Schritt: Finde den Kontakt anhand des Mails-Hash */
            INNER JOIN mails AS m ON c.id = m.sender_ceid
            /* 2. Schritt: Verknüpfe alle E-Mails, die zu DIESEM Kontakt gehören */
            LEFT JOIN contact_emails AS ce ON c.id = ce.contact_id
            WHERE id_hash = UNHEX(:hash)
            AND m.tenant_id = :tenant_id
            GROUP BY c.id, c.display_name, c.type;";

        $ret = $this->queryIt($sql,
            ["hash" => $hash]
        );

        if (is_array($ret) && count($ret)>0){
            return $ret;
        }else{
            return ["return" => $ret];
        }
        
    }
    
    /**
     * Dokumente aus Paperless (TimeLineData)
     * @param int $docIDs (holt alle Dokumente zu einem Korrespondenten)
     * return array of Documents
     */
    private function getDocumentItemsByCorrespondent($docIDs)
    {
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

    private function formatItem($row, $type, $dateString, $detail, $hash)
    {
        $eventDate = new \DateTime($dateString);
        
        // Logik für Farbe (vielleicht in eigene Funktion auslagern)
        $colors = ['green', 'red', 'yellow', 'none'];
        $colorIndex = (isset($row['item_id'])) ? (int)$row['item_id'] % count($colors) : 0;

        return [
            'type'      => $type,
            'id'        => $row['item_id'] ?? null,
            'date'      => $eventDate->format('d.m.Y H:i'),
            'title'     => $row['title'] ?? 'Kein Titel',
            'detail'    => $detail,
            'is_recent' => ($eventDate >= new \DateTime('-30 days')),
            'hash'      => $hash,
            'critical'  => $colors[$colorIndex],
            'link'      => ($type === 'mail') ? '#hash-' . $hash : '/dms/document/' . ($row['item_id'] ?? '')
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