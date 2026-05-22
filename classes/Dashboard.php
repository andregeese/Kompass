<?php
namespace App;
use PDO;
use DateTime;

class Dashboard extends Database {

    public function __construct() {
        parent::__construct($this);
    }

    public static function hashId($id) {
        return hash('sha256', $id . HASH_SALT);
    }

    public function decrypt($data) {
        $iv = substr(ENC_IV, 0, 16);
        return openssl_decrypt($data, 'aes-256-cbc', ENC_KEY, 0, $iv);
    }
    
    public function encrypt($data) {
        $iv = substr(ENC_IV, 0, 16);
        return openssl_encrypt($data, 'aes-256-cbc', ENC_KEY, 0, $iv);
    }

    public function BREAKIT($array){
        header('Content-Type: application/json');
        echo $array;
        exit;
    }

    public function getTenantSettings()
    {
        return "";
    }

    /**
     * Prüft ob der Benutzer bereits in der Datenbank ist
     */
    public function checkUserInDB($user,$pw):bool
    {
        try {
            // Prüfen, ob E-Mail existiert
            $res = $this->queryIt(
                "SELECT id FROM users WHERE username = :email", 
                ["email" => $user]
            );
            
            // Sicherer als count() == 1: Wenn das Array nicht leer ist, existiert der User!
            return !empty($res);

        } catch (\Throwable $t) {
            throw new \Exception($t->getMessage());
        }
    }

    public function registerUser($mail, $pwHash, $configParam)
    {
        try {
            $pdo = parent::getConnection();

            $sqlUser = "INSERT INTO users (username, password_hash) VALUES (:mail, :pwHash)";
            $this->queryIt($sqlUser, [
                "mail"   => $mail,
                "pwHash" => $pwHash
            ]);

            $tenantId = $pdo->lastInsertId();

            if (!$tenantId) {
                throw new \Exception("User ID konnte nicht ermittelt werden.");
            }

            $configs = $configParam;

            $sqlConfig = "INSERT INTO tenant_config (tenant_id, config_key, config_value) 
                        VALUES (:tenant_id, :config_key, :config_value)";

            foreach ($configs as $key => $val) {
                $this->queryIt($sqlConfig, [
                    "tenant_id"    => $tenantId,
                    "config_key"   => $key,
                    "config_value" => $val
                ]);
            }

            // RETTER: Gib die ID zurück, damit die api.php die Session setzen kann!
            return $tenantId; 

        } catch (\Throwable $t) {
            throw new \Exception ($t->getMessage());
            error_log("Fehler bei registerUser: " . $t->getMessage());
            return false;
        }
    }

    public function loginUser($email, $password)
    {
        return $this->queryIt($sql = "SELECT id, password_hash FROM users WHERE username = :email", [
            'email' => $email
        ]);

    }

    /* 

    public function autoLinkContactOLD($data)
    {
        $email = strtolower(trim($data['email']));
        $name  = trim($data['name']?? 'Unbekannter Kontakt');
        $emailHash = hash('sha256', $email . HASH_SALT);
        $subjectHash = $data['hash'];

        $mailSenderHash = $data['sender_hash'] ?? null;

        // 1. Gibt es diese E-Mail schon in contact_emails?
        $check = $this->queryIt("SELECT contact_id FROM contact_emails WHERE email_hash = :hash AND tenant_id = :tenant_id", [
            'hash' => $emailHash
        ]);

        if (!empty($check)) {
        // Fall A: E-Mail existiert bereits
            $contactId = $check[0]['contact_id'];
        } else {
            // Fall B: E-Mail ist neu, Kontakt neu anlegen
            $contactId = $this->insertContact($name, $email, 'company');
        }
        error_log("AUTO LINK:" . $contactId);

        // 2. Update der mails-Tabelle, falls noch keine Verknüpfung besteht
        if ($contactId) {
            $this->queryIt(
                "UPDATE mails 
                SET contact_id = :cid 
                WHERE subject_hash = :subjectHash  -- Hier suchen wir nach dem Absender der Mail
                AND tenant_id = :tenant_id 
                AND (contact_id IS NULL OR contact_id = 0)",
                [
                    'cid'        => $contactId,
                    'senderHash' => $mailSenderHash,
                    'subjectHash' => $this->dashboard->encrypt($subject)
                ]
            );
        }
        
        // 2. Wenn nein: Lege einen neuen Kontakt an (via deiner insertContact-Logik)
        // Damit hast du die Verknüpfung automatisch hergestellt
        //$newId = $this->insertContact($name, $email, 'company');
        
        return ['success' => true, 'contact_id' => $contactId];
    } */


    /**
     * Finde alle Kontakt zu dem Kontakt (1:n)
     */
    public function getSenderFromContacts(string $hash='') :array
    {
        $sql = "
            SELECT 
                c.id AS contact_id, 
                c.display_name,
                GROUP_CONCAT(ce.email) AS all_known_emails,
                GROUP_CONCAT(DISTINCT pcm.paperless_id) AS paperless_ids
            FROM contact_emails ce
            -- 1. Wir finden das Unternehmen über den Hash
            JOIN contacts c ON ce.contact_id = c.id
            -- 2. Wir sammeln ALLE E-Mail-Adressen dieses Unternehmens
            LEFT JOIN contact_emails ce_all ON c.id = ce_all.contact_id
            -- 3. Wir sammeln ALLE Paperless-Mappings dieses Unternehmens
            LEFT JOIN contact_paperless_mappings pcm ON c.id = pcm.contact_id
            WHERE ce.email_hash = :email_hash
            AND c.tenant_id = :tenant_id
            GROUP BY c.id;";


        $ret =  $this->queryIt($sql, [
            'salt' => defined('HASH_SALT') ? HASH_SALT : '',
            'email_hash' => $hash
            ]);
        
        return $ret;
            
    }

    /**
     * Holt den bereinigten Absender (reine E-Mail-Adresse) einer E-Mail anhand des Mail-Hashes.
     * * @param string $hash Der SHA-256 Hash der Mail-ID + Salt
     * @return string|null Die isolierte E-Mail-Adresse (z.B. 'hans@mueller.de') oder null
     */
    public function getSenderByHash(string $hash): ?string {
        $sql = "SELECT sender FROM mails WHERE tenant_id=:tenant_id AND SHA2(CONCAT(id, :salt), 256) = :hash LIMIT 1";
        
        $rows = $this->queryIt($sql, [
            'salt' => defined('HASH_SALT') ? HASH_SALT : '',
            'hash' => $hash
        ]);
        
        if (empty($rows) || !isset($rows[0]['sender'])) {
            return null;
        }

        $rawSender = $rows[0]['sender'];

        // Regex: Filtert die E-Mail-Adresse heraus, falls Name + <mail@domain.de> drin steht
        if (preg_match('/<([^>]+)>/', $rawSender, $matches)) {
            return strtolower(trim($matches[1]));
        }

        // Falls nur die reine E-Mail-Adresse im Feld stand
        return strtolower(trim($rawSender));
    }

    

    public function getContactByMail(string $hash)
    {
        $salt = defined('HASH_SALT') ? HASH_SALT : 'default_fallback_salt';

        $sql="SELECT 
            c.id AS contact_id,
            c.display_name,
            c.type,
            GROUP_CONCAT(DISTINCT ce.email SEPARATOR ', ') AS additional_emails
        FROM contacts AS c
        INNER JOIN mails AS m ON c.id = m.contact_id
        LEFT JOIN contact_emails AS ce ON c.id = ce.contact_id
        WHERE SHA2(CONCAT(m.id, :salt), 256) = :hash 
        AND m.tenant_id = :tenant_id
        GROUP BY c.id, c.display_name, c.type
        LIMIT 1;";

        $ret = $this->queryIt($sql,
            ["hash" => $hash,
            "salt" =>  defined('HASH_SALT') ? HASH_SALT : '']
        );

        return $ret[0];

    }

    /**
     * Finde alle Mails zu den Kontakten
     */
    public function getContactMailsBySelMail(string $hash="")
    {
        $salt = defined('HASH_SALT') ? HASH_SALT : 'default_fallback_salt';

        $sqlExact = "SELECT 
                        m.id AS mail_id, 
                        m.contact_id, 
                        m.sender, 
                        GROUP_CONCAT(ce.id SEPARATOR ', ') AS ce_ids,
                        GROUP_CONCAT(ce.email SEPARATOR ', ') AS additional_emails
                    FROM mails AS m
                    INNER JOIN (
                        SELECT contact_id 
                        FROM mails 
                        WHERE SHA2(CONCAT(id, :salt), 256) = :hash 
                        LIMIT 1
                    ) AS ref ON m.contact_id = ref.contact_id
                    LEFT JOIN contact_emails AS ce ON m.contact_id = ce.contact_id
                    WHERE m.tenant_id = :tenant_id
                    GROUP BY m.id, m.contact_id, m.sender LIMIT 50;";

        $ret = $this->queryIt($sqlExact,
            ["hash" => $hash,
            "salt" =>  defined('HASH_SALT') ? HASH_SALT : '']
        );
        return $ret;
    }

    /**
     * Versucht eine E-Mail-Adresse einem bestehenden CRM-Kontakt zuzuordnen
     * oder findet ein passendes DMS-Mapping über die Domain.
     * * @param string $email Die zu prüfende E-Mail-Adresse
     * @return array Status und Kontaktdaten oder Vorschlag
     */
    public function findContactByEmail(string $email): array {
        $email = strtolower(trim($email));
        
        // Validierung, falls mal Datenmüll ankommt
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'invalid', 'message' => 'Ungültige E-Mail-Adresse'];
        }

        // --- STUFE 1: Exakter E-Mail Match in den CRM-Kontakten ---
        $sqlExact = "
            SELECT c.id, c.display_name, c.type 
            FROM contacts c 
            JOIN contact_emails ce ON c.id = ce.contact_id 
            WHERE 
            tenant_id=:tenant_id
            AND ce.email = :email 
            LIMIT 1
        ";
        
        // Nutzt deine bestehende queryIt-Methode der Engine
        $exactResult = $this->queryIt($sqlExact, ['email' => $email]);

        if (!empty($exactResult)) {
            return [
                'status' => 'matched',
                'contact' => $exactResult[0]
            ];
        }

        // --- STUFE 2: Domain- & Paperless-Match (z.B. mueller.de oder mueller) ---
        $domain = substr(strrchr($email, "@"), 1); // Extrahiert z.B. "mueller.de"
        $domainName = explode('.', $domain)[0];     // Extrahiert z.B. "mueller"

        $sqlSuggest = "
            SELECT c.id, c.display_name, c.type 
            FROM contacts c
            JOIN contact_paperless_mappings cpm ON c.id = cpm.contact_id
            WHERE 
            tenant_id=:tenant_id
            AND cpm.paperless_correspondent_name = :domain 
            OR cpm.paperless_correspondent_name LIKE :name
            LIMIT 1
        ";
        
        $suggestResult = $this->queryIt($sqlSuggest, [
            'domain' => $domain,
            'name'   => "%{$domainName}%"
        ]);

        if (!empty($suggestResult)) {
            return [
                'status' => 'suggestion',
                'reason' => "Gefunden via DMS-Mapping für '{$domain}'",
                'contact' => $suggestResult[0]
            ];
        }

        // --- STUFE 3: Komplett unbekannter Absender ---
        return [
            'status' => 'unknown',
            'suggested_name' => ucfirst($domainName), // Erster Buchstabe groß als Namensvorschlag
            'email' => $email
        ];
    }

    /**
     * Sendet eine standardisierte JSON-Antwort an das Frontend.
     * Unterstützt reguläre Daten, weiche Statusmeldungen und harte Fehler.
     * @param $success boolean 
     * @
     */
    public static function sendResponse(bool $success, $data = null, ?string $error = null, string $status = 'success') {
        header('Content-Type: application/json');
        
        // Sicherstellen, dass leere Verläufe/Objekte als {} statt [] im JSON ankommen
        if ($data instanceof \stdClass || (is_array($data) && empty($data))) {
            $details = empty($data) ? new \stdClass() : $data;
        } elseif (is_array($data) && isset($data['tree'])) {
            $details = $data;
        } else {
            $details = $data;
        }

        echo json_encode([
            'success' => $success,
            'status'  => $status,
            'details' => $details,
            'error'   => $error
        ]);
        exit;
    }



    /*** PAPERLESS */
    
}