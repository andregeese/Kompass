<?php
namespace App;
use Exception; // Falls du Exceptions nutzt
use Throwable;
use DateTime;
use PDO;

// classes/MailProcessor.php
class ContactProcessor extends Database
{
    use ServiceTrait;
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
        
        // Suche Absender-Hash der Mail aus dem POST-Body
        $idHash = $data['hash'] ?? null;

        // 1. Kontakt suchen oder erstellen (wie gehabt)
        $check = $this->queryIt("SELECT contact_id FROM contact_emails WHERE email_hash = :hash AND tenant_id = :tenant_id", [
            'hash' => $emailHash
        ]);

        $contactId = !empty($check) ? $check[0]['contact_id'] : $this->insertContact($data['name'], $email, 'company');

        // 2. Verknüpfung in mails über den sender_hash
        if ($idHash) {
            $ret = $this->queryIt(
                "UPDATE mails SET contact_id = :cid WHERE id_hash = UNHEX(:hash) AND tenant_id = :tenant_id",
                [
                    'salt' => defined('HASH_SALT') ? HASH_SALT : '',
                    'hash' => $idHash,
                    'cid'  => $contactId
                ]
            );
        }
    
        return ['success' => true, 'contact_id' => $contactId, "details"=> $ret];
    
    }

    /**
    * Legt einen neuen Hauptkontakt und die zugehörige E-Mail-Adresse an.
    * * @param string $name Der Anzeigename des Kontakts
    * @param string $email Die E-Mail-Adresse
    * @param string $type Typ des Kontakts ('company' oder 'person')
    * @return int|bool Die neue Contact-ID bei Erfolg, sonst false
    */
    public function insertContact(string $name, string $email, string $type = 'company') {
        $name = trim($name);
        $email = strtolower(trim($email));
        $type = trim($type);
        $salt = defined('HASH_SALT') ? HASH_SALT : '';

        if (empty($name) || empty($email)) {
            return false;
        }
            
        // 1. Kontakt suchen (falls vorhanden) oder neu anlegen
        // Wir nutzen INSERT oder prüfen vorher, um den Unique-Constraint zu respektieren
        $sqlContact = "INSERT INTO contacts (display_name, type, tenant_id) VALUES (:name, :type, :tenant_id) ON DUPLICATE KEY UPDATE type = VALUES(type), display_name = VALUES(display_name)";
        $this->queryIt($sqlContact, ['name' => $name, 'type' => $type]);
        
        $getNewId = $this->queryIt("SELECT id FROM contacts WHERE display_name = :name AND tenant_id = :tenant_id LIMIT 1", [
            'name' => $name
        ]);

        $contactId = $getNewId[0]['id'] ?? null;

        // 2. E-Mail-Adresse mit Hash speichern
        $emailHash = hash('sha256', $email . $salt);
        
        $sqlEmail = "INSERT INTO contact_emails (contact_id, email, email_hash, tenant_id) VALUES (:cid, :email, :hash, :tenant_id)  ON DUPLICATE KEY UPDATE email =(email)";
        $this->queryIt($sqlEmail, [
            'cid'   => $contactId,
            'email' => $email, // Optional: Nur wenn du Klartext-E-Mails speichern willst
            'hash'  => $emailHash
        ]);

        return $contactId;

    
    }


    public function getContactById($hash, $id) // Name korrigiert
    {
        
        // Hinweis: Prüfe dein SQL! :tenant_id fehlt im Array
        $sql = "SELECT * FROM contacts WHERE tenant_id=:tenant_id AND id=:contactId";
        return $this->queryIt($sql, [
            "tenant_id" => $_SESSION['tenant_id'], // Wichtig: Tenant beachten
            "contactId" => $id
        ]);
    }

    /**
     * Kontakte anhand von hashed ID
     */
    public function getEmailsByContact($contact)
    {
            /*$sql = "SELECT ce.display_name FROM contact_emails as ce
                  LEFT JOIN contacts as c ON c.id = ce.contact_id 
                  where c.tenant_id=:tenant_id AND 
                  (ce.email like :search OR c.display_name like :search);";
                  */
            $sql ="SELECT 
                    c.id AS contact_id,
                    c.display_name,
                    c.type,
                    GROUP_CONCAT(DISTINCT ce.email SEPARATOR ', ') AS additional_emails
                FROM contacts AS c
                INNER JOIN mails AS m ON c.id = m.contact_id
                LEFT JOIN contact_emails AS ce ON c.id = ce.contact_id
                WHERE id_hash = UNHEX(:hash)
                AND m.tenant_id = :tenant_id
                GROUP BY c.id, c.display_name, c.type";

            $ret = $this->queryIt($sql,
                ["search" =>"%" . $query . "%"]
            );
            return $ret;
        
       
        
    }

    public function saveContactEmails($contact_id, $emails)
    {
        
        $contact_ids=[];
        $email_list = [];
        error_log("saveContactEmails");
        error_log(print_r($emails, true));

        foreach ($emails as $_email)
        {
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
            );
        };

        $ids_string = implode(',', array_map('intval', $contact_ids));
        
        // --- SCHRITT 1: ALLES "CLEAREN" ---
        // Setze für diesen Kontakt alle bisherigen E-Mail-Verknüpfungen zurück
        

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
            
            $this->queryIt($sqlCE, ["contact_id" => $contact_id]);
        }

        //3. contct aktualisieren
        $sqlC = "UPDATE contacts SET is_active =0 WHERE tenant_id=:tenant_id AND id IN ($ids_string);";
        $this->queryIt($sqlC, []);
        //error_log("idstring:" . print_r($ids_string, true));


        $quoted_emails = array_map(function($email) {
            return "'" . addslashes($email) . "'";
        }, $email_list);

        $mails_string = implode(',', $quoted_emails); // Ergebnis: 'a@b.com','c@d.com'

        $sqlCE = "UPDATE contact_emails SET contact_id =:contact_id WHERE tenant_id=:tenant_id AND email IN($mails_string);";
        $ret = $this->queryIt($sqlCE, ["contact_id" => $contact_id]);

        return [
            'success' => true, 
            'details' => $ret
        ];
            
        
    }

    public function getContactListByContactID(int $contact_id=null)
    {
        $sql = "SELECT 
                c.id AS id,
                ce.id as ceid,
                c.is_active as is_active,
                ce.email as email,
                c.display_name as displayname,
                c.paperless_id as paperless_id,
                c.type as type, 
                GROUP_CONCAT(DISTINCT ce.email SEPARATOR ', ') AS additional_emails,
                COUNT(ce.id) OVER (PARTITION BY c.id) > 1 AS is_connected
            FROM contacts AS c
            -- INNER JOIN mails AS m ON c.id = m.contact_id
            LEFT JOIN contact_emails AS ce ON c.id = ce.contact_id
            WHERE c.tenant_id =:tenant_id 
            -- AND id_hash = UNHEX(:hash)
            AND contact_id=:contact_id
            GROUP BY c.id, ce.id, c.is_active, ce.email, c.display_name, c.paperless_id, c.type;";

        $ret = $this->queryIt($sql,
            ["contact_id" => $contact_id]
        );
        return $ret;
    }

    public function getContactList(string $query="", int $contact_id=null)
    {
        $sql = "SELECT 
                c.id AS id,
                ce.id as ceid,
                c.is_active as is_active,
                ce.email as email,
                c.display_name as displayname,
                c.paperless_id as paperless_id,
                c.type as type, 
                GROUP_CONCAT(DISTINCT ce.email SEPARATOR ', ') AS additional_emails,
                COUNT(ce.id) OVER (PARTITION BY c.id) > 1 AS is_connected
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
    public function getTimelineData($hash, $salt, $tenantId)
    {
        
        // 1. Daten aus allen Quellen sammeln und normalisieren
        $items = [];
        $mailItems = array_merge($items, $this->getMailItems($hash, $salt, $tenantId));
        
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
        $correspondentIds = $this->getPaperlessIdsByMailHash($hash, $salt, $tenantId);
        //prüfe welche Dokumente es dazu gibt
        $docs = $this->getDocumentItems($correspondentIds);


        // 2. Paperless Dokumente mappen (hier machst du das gleiche für $docs)
        foreach ($docs as $doc) {
            $datumString = $doc['created'] ?? $doc['created_date'] ?? date('Y-m-d');
            $timeline[] = [
                'type'      => 'document',
                'id'        => $doc['id'],
                'date'      => date('d.m.Y', strtotime($datumString)),
                'title'     => $doc['title'],
                'detail'    => 'Paperless Dokument',
                'is_recent' => false,
                'hash'      => '',
                'link'      => '/dms/document/' . $doc['id']
            ];
        }

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
                        SELECT contact_id, paperless_id FROM mails WHERE id_hash = UNHEX(:hash) LIMIT 1
                    ) AS ref ON m.contact_id = ref.contact_id
                    WHERE m.tenant_id = :tenant_id AND m.status NOT IN ('deleted')
                    ORDER BY m.date_received DESC LIMIT 100"; // Dein SQL von oben
        $rawMails = $this->queryIt($sql, ["hash" => $hash, "salt" => $salt, "tenant_id" => $tenantId]);
        
        $items = [];
        foreach ($rawMails as $row) {
            $items[] = $this->formatItem($row, 'mail', $row['event_date'], $row['detail'], $hash);
        }
        return $items;
    }

    private function getPaperlessIdsByMailHash(string $hash, string $salt, int $tenantId): array
    {
        $sql = "SELECT DISTINCT c.paperless_id 
                FROM contacts as c
                INNER JOIN mails m ON c.id = m.contact_id
                WHERE id_hash = UNHEX(:hash)
                AND m.tenant_id = :tenant_id";

        $result = $this->queryIt($sql, ['hash' => $hash, 'salt' => $salt, 'tenant_id' => $tenantId]);
        
        // Wandle das Ergebnis-Array in ein flaches Array von IDs um
        return array_column($result, 'paperless_id');
    }


    public function getContactByMail(string $hash)
    {
        $salt = defined('HASH_SALT') ? HASH_SALT : 'default_fallback_salt';

        $sql="SELECT 
                c.id AS contact_id,
                c.display_name,
                c.type,
                GROUP_CONCAT(DISTINCT ce.email SEPARATOR ', ') AS additional_emails,
                GROUP_CONCAT(DISTINCT c.paperless_id SEPARATOR ', ') AS paperless_ids
            FROM contacts AS c
            /* 1. Schritt: Finde den Kontakt anhand des Mails-Hash */
            INNER JOIN mails AS m ON c.id = m.contact_id
            /* 2. Schritt: Verknüpfe alle E-Mails, die zu DIESEM Kontakt gehören */
            LEFT JOIN contact_emails AS ce ON c.id = ce.contact_id
            WHERE id_hash = UNHEX(:hash)
            AND m.tenant_id = :tenant_id
            GROUP BY c.id, c.display_name, c.type;";

        $ret = $this->queryIt($sql,
            ["hash" => $hash,
            "salt" =>  defined('HASH_SALT') ? HASH_SALT : '']
        );

        if (is_array($ret) && count($ret)>0){
            return $ret;
        }else{
            return ["return" => $ret];
        }
        
    }
    
    /**
     * Dokumente by Korrespondent
     */
    private function getDocumentItems($correspondentID)
    {
        error_log("DEBUG: Typ und inhaltvon correspondentid: " . gettype($correspondentID) . ':' . print_r($correspondentID, true));

        // Hier logik für Paperless API
        $paperless = new PaperlessProcessor($this->dashboard);
        $docs = $paperless->fetchDocumentsByCorrespondentId($correspondentID);
        error_log("DEBUG: Inhalt von docs: " . print_r($docs, true));

        $items = [];
        foreach ($docs as $doc) { 
            $items[] = [
                'type'      => 'document',
                'id'        => $doc['id'],
                // Nutze 'created_date' direkt für das Format
                'date'      => date('d.m.Y', strtotime($doc['created_date'])),
                'title'     => $doc['title'],
                'detail'    => 'DMS: ' . ($doc['original_file_name'] ?? 'Dokument'),
                'is_recent' => false,
                'hash'      => '', // Paperless-Docs haben keinen Mail-Hash
                'critical'  => '', // Hier kannst du bei Bedarf Logik für 'tags' einbauen
                'link'      => '' //$this->getPaperlessUrl() . '/dms/document/' . $doc['id']
            ];
        } 

        error_log("DEBUG: Typ von items: " . gettype($items));
        error_log("DEBUG: Inhalt von items: " . print_r($items, true));

        return $items;
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
            //error_log("FICK DICH: " . print_r($item,true));

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