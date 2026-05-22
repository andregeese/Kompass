<?php
namespace App;
use Exception; // Falls du Exceptions nutzt
use Throwable;
use PDO;

// classes/MailProcessor.php
class ContactProcessor extends Database
{
    private $dashboard;
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
                "UPDATE mails SET contact_id = :cid WHERE SHA2(CONCAT(id, :salt), 256) = :hash AND tenant_id = :tenant_id",
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
    private function insertContact(string $name, string $email, string $type = 'company') {
        $name = trim($name);
        $email = strtolower(trim($email));
        $type = trim($type);
        $salt = defined('HASH_SALT') ? HASH_SALT : '';

        if (empty($name) || empty($email)) {
            return false;
        }

        try {
            
            // 1. Kontakt suchen (falls vorhanden) oder neu anlegen
            // Wir nutzen INSERT oder prüfen vorher, um den Unique-Constraint zu respektieren
            $sqlContact = "INSERT INTO contacts (display_name, type, tenant_id) VALUES (:name, :type, :tenant_id) ON DUPLICATE KEY UPDATE type =(type)";
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

        } catch (Exception $e) {
            error_log("Error in insertContact: " . $e->getMessage());
            $this->db->rollBack();
            return false;
        }
    }
    public function getEmailsByContact($contact)
    {
        $sql = "SELECT ce.display_name FROM contact_emails as ce
                  LEFT JOIN contacts as c ON c.id = ce.contact_id 
                  where c.tenant_id=:tenant_id AND 
                  (ce.email like :search OR c.display_name like :search);";

        $ret = $this->queryIt($sql,
            ["search" =>"%" . $query . "%"]
        );
        return $ret;
    }

    public function getContactList(string $query="")
    {

        $sql = "SELECT c.id as id, ce.email as email, c.display_name as displayname, c.paperless_id as paperless_id FROM contact_emails as ce
                  LEFT JOIN contacts as c ON c.id = ce.contact_id 
                  where c.tenant_id=:tenant_id AND 
                  (ce.email like :search OR c.display_name like :search);";

        $ret = $this->queryIt($sql,
            ["search" =>"%" . $query . "%"]
        );
        return $ret;
    }

    /**
     * Erzeuge eine Contact TimeLine
     */
    public function getContactTimeline($jsonData)
    {
        error_log("getContactTimeline");
        error_log(print_r($jsonData, true));

        $salt = defined('HASH_SALT') ? HASH_SALT : '';

        // 🛠️ FIX 1: Den Hash sicher aus dem übergebenen JSON/Array extrahieren
        // Falls es noch ein roher JSON-String ist, decodieren wir ihn kurz
        if (is_string($jsonData)) {
            $jsonData = json_decode($jsonData, true);
        }
        
        $hash = $jsonData['hash'] ?? '';

        // 1. Hole alle Mails des gleichen Kontakts
        $sqlMails = "SELECT 
                        'mail' AS type,
                        m.id AS item_id,
                        ref.paperless_id as paperlesscontact,
                        -- ce.paperless_id as paperlesscorrespondent
                        m.date_received AS event_date,
                        m.subject_hash AS title,
                        m.sender AS detail
                    FROM mails AS m
                    INNER JOIN (
                        SELECT contact_id, paperless_id FROM mails WHERE SHA2(CONCAT(id, :salt), 256) = :hash LIMIT 1
                    ) AS ref ON m.contact_id = ref.contact_id
                    WHERE m.tenant_id = :tenant_id AND m.status NOT IN ('deleted')
                    ORDER BY m.date_received DESC LIMIT 100";

        $rawMails = $this->queryIt($sqlMails, ["hash" => $hash, "salt" => $salt]);

        $rawDocs= [];        

        // HINWEIS: Hier kannst du später per UNION oder separater Query 
        // Termine ($appointments) und Notizen ($notes) mit in das Array pushen!

        // 2. Im PHP nach Jahr gruppieren und 30-Tage-Regel berechnen
        $timeline = [];
        $thirtyDaysAgo = new \DateTime('-30 days');
        
        // Deutsche Monatsnamen als Mapping
        $monthsDe = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
        ];

        foreach ($rawMails as $row) {
            $eventDate = new \DateTime($row['event_date']);
            $year = $eventDate->format('Y');
            $monthNum = (int)$eventDate->format('n');
            $monthName = $monthsDe[$monthNum];
            
            // 30-Tage-Regel
            $isOpenByDefault = ($eventDate >= $thirtyDaysAgo);

            if (!isset($timeline[$year])) {
                $timeline[$year] = [
                    'year' => $year,
                    'is_open' => $isOpenByDefault,
                    'months' => []
                ];
            }

            if ($isOpenByDefault) {
                $timeline[$year]['is_open'] = true;
            }

            if (!isset($timeline[$year]['months'][$monthName])) {
                $timeline[$year]['months'][$monthName] = [
                    'name' => $monthName,
                    'is_open' => $isOpenByDefault, // 🚀 Steuert das Monats-Akkordeon!
                    'items' => []
                ];
            }

            // Falls ein neueres Item im Monat liegt, Monat offen halten
            if ($isOpenByDefault) {
                $timeline[$year]['months'][$monthName]['is_open'] = true;
            }
            
            //steuert die Farbe im Fronted
            $colors = ['green', 'red', 'yellow', 'none'];
            $colorIndex = (int)$row['item_id'] % count($colors);
            $criticalColor = $colors[$colorIndex];

            $timeline[$year]['months'][$monthName]['items'][] = [
                'type' => $row['type'],
                'id' => $row['item_id'],
                'date' => $eventDate->format('d.m.Y H:i'),
                'title' => 'der Titel',
                'detail' => $row['detail'],
                'is_recent' => $isOpenByDefault,
                'hash' => $hash,
                'critical' => $criticalColor,
                'link' => ($row['type'] === 'mail') ? '#hash-' . $hash : '/dms/document/' . $row['item_id']
            ];
        }

        krsort($timeline);

        foreach ($timeline as &$yearData) {
            $yearData['months'] = array_values($yearData['months']);
        }

        return array_values($timeline);

        // Jahre absteigend sortieren
        krsort($timeline);

        // Die assoziativen Monate in numerische Arrays umwandeln, damit Alpine.js sauber damit arbeitet
        foreach ($timeline as &$yearData) {
            $yearData['months'] = array_values($yearData['months']);
        }

        return array_values($timeline);
    }
}
?>