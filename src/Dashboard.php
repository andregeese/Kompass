<?php
namespace App;

use PDO;
use Exception;
use Throwable;

/**
 * @method void dashboard() 
 */
class Dashboard
{
    protected \App\Core\Database $db;

    private array $instances = [];
    public ?int $tenant_id = null;
    private array $services = [];
    
    // Cache für Tenant-Validierung
    private static ?bool $tenantValidated = null;

    public function __construct(\App\Core\Database $db) {
        $this->db = $db;
    }

    public static function hashId($id): string {
        return hash('sha256', $id . HASH_SALT);
    }

    public function decrypt(string $data): ?string 
    {
        $ENC_IV = $this->getService('systemProcess', \App\Services\SystemProcessor::class)->getRequiredEnv('ENC_IV');
        $ENC_KEY= $this->getService('systemProcess', \App\Services\SystemProcessor::class)->getRequiredEnv('ENC_KEY');
        $iv = substr($ENC_IV, 0, 16);
        return openssl_decrypt($data, 'aes-256-cbc', $ENC_KEY, 0, $iv) ?: null;
    }
    
    public function encrypt(string $data): ?string 
    {
        $ENC_IV = $this->getService('systemProcess', \App\Services\SystemProcessor::class)->getRequiredEnv('ENC_IV');
        $ENC_KEY= $this->getService('systemProcess', \App\Services\SystemProcessor::class)->getRequiredEnv('ENC_KEY');
        $iv = substr($ENC_IV, 0, 16);
        return openssl_encrypt($data, 'aes-256-cbc', $ENC_KEY, 0, $iv) ?: null;
    }

    public function setTenantId(int $id): void {
        if ($id <= 0) {
            throw new \InvalidArgumentException("Ungültige tenant_id: $id");
        }
        $this->tenant_id = $id;
    }

    public function getService(string $name, string $className) {
        if (!isset($this->services[$name])) {
            $this->services[$name] = new $className($this);
        }
        return $this->services[$name];
    }


    public function verifyCredentials(string $email, string $password): ?array 
    {

        $sql = "SELECT id, tenant_id, password_hash FROM users WHERE username = :email LIMIT 1";
        
        $dbResult = $this->db->queryIt(
            $sql,
            ['email' => $email],
            false,
            false,
            true
        );

        if ($dbResult->success)
        {
            $userList = $dbResult->data->fetched;
        }else{
            $userList = null;
        }

        $user = $userList[0] ?? null; // Nimm das erste Element aus der Liste
        
        //error_log("LOGIN RESULT FÜR user:" . print_r($dbResult->data->fetched[0], true));
        //if ($user && password_verify($password, $user['password_hash'])) {
        if ($user) {
            //error_log("LOGIN RESULT FÜR " . $email . ": " . $user['id'] . ", tenant_id: " . $user['tenant_id']);
            return [
                'id'        => (int)$user['id'],
                'tenant_id' => (int)$user['tenant_id']
            ];
        }

        return null; // Login fehlgeschlagen
    }


    /**
     * Prüft, ob der Tenant in der Datenbank existiert.
     */
    public function isValidTenant(int $tenantId): bool 
    {
        return true;
        /*
        benötigen wir später
        */
        if (self::$tenantValidated !== null) {
            return self::$tenantValidated;
        }

        $dbResult = $this->db->queryIt(
            "SELECT id FROM contacts WHERE id = :tid AND type = 'company' LIMIT 1", 
            ['tid' => $tenantId]
        );
        if ($dbResult->success){
            return $dbResult->data["fetched"];
        }else{

        }

        self::$tenantValidated = !empty($result);
        return self::$tenantValidated;
    }

    public function checkUserInDB(string $user): bool
    {
        try {
            $dbResult = $this->db->queryIt(
                "SELECT id FROM users WHERE username = :email", 
                ["email" => $user]
            );

            return !empty($dbResult->fetched);
        } catch (Throwable $t) {
            error_log("Fehler bei checkUserInDB: " . $t->getMessage());
            return false;
        }
    }

    public function registerUser(string $mail, string $pwHash)
    {
        try {
            $sqlUser = "INSERT INTO users (username, password_hash) VALUES (:mail, :pwHash)";
            $dbResult = $this->db->queryIt($sqlUser, [
                "mail"   => $mail,
                "pwHash" => $pwHash
            ],false, true);

            $tenantId = (int)$dbResult->data->lastId;// (int)$this->db->getConnection()->lastInsertId();


            return $tenantId; 

        } catch (Throwable $t) {
            error_log("Fehler bei registerUser: " . $t->getMessage());
            throw new Exception("Registrierung fehlgeschlagen: " . $t->getMessage());
        }
    }

    public function loginUser(string $email, string $password): array
    {
        // 1. Lass den Parameter-Namen exakt so wie im SQL
        $sql = "SELECT id, password_hash FROM users WHERE username = :email LIMIT 1";

        // 2. Rufe queryIt auf
        $result = $this->db->queryIt($sql, [
            'email' => $email
        ]);

        // 3. Debug-Ausgabe: Was kommt wirklich an?
        //error_log("LOGIN RESULT FÜR " . $email . ": " . json_encode($result));

        return !empty($result) ? $result : [];
    }

    public function getHashSalt(): string
    {
        return defined('HASH_SALT') ? HASH_SALT : '';
    }

    /**
     * Standard-Antwort an JSON
     */
    public static function sendResponse(bool $success, $data = null, ?string $error = null, string $status = 'success'): void 
    {
        header('Content-Type: application/json');
        // HTTP-Statuscode setzen
        if (!$success) {
            http_response_code(400); 
        }
        
        $details = $data;
        if (is_array($data) && empty($data)) {
            $details = new \stdClass();
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