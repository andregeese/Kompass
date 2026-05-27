<?php
namespace App;
use PDO;
use DateTime;

class Dashboard extends Database 
{
    private $instances = [];
    
    // Das Dashboard ist der einzige Ort, der Instanzen wirklich hält
    public function getService($name, $className) {
        if (!isset($this->instances[$name])) {
            $this->instances[$name] = new $className($this);
        }
        return $this->instances[$name];
    }

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

    public function handleLinkRequest($data) {
        // 1. Automatische Umwandlung des Hex-Hashes in Binärdaten
        $binHash = hex2bin($data['hash']); 
        
        // Validierung: Ist der Hash korrekt? (SHA256 sind 32 Bytes)
        if (strlen($binHash) !== 32) {
            return ['error' => 'Ungültiger Hash-Format'];
        }

        // 2. Jetzt kannst du die Funktion aufrufen, die wir vorher optimiert haben
        $this->linkMailToPaperless($binHash, (int)$data['paperless_id']);
        
        return ['success' => true];
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

    /**
     * tbd: Hash in die systems DB schreiben und verschlüssen
     * Liefert den HASH aus defined
     */
    public function getHashSalt()
    {
        return defined('HASH_SALT') ? HASH_SALT : '';
    }


    /**
     * OBSOLET
     * Finde alle Mails zu den Kontakten
     */
    public function getContactMailsBySelMail(string $hash="")
    {
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
            "salt" =>  $this->getHashSalt()]
        );
        return $ret;
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

}