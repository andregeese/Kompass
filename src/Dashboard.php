<?php
/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

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
    public mixed $request = null;
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

    /**
     * Generiert einen isolierten, 32-Byte starken Kryptoschlüssel für den aktuellen Tenant
     */
    public function getTenantCryptoKey(): string 
    {
        if ($this->tenant_id === null) {
            if (!empty($_SESSION['tenant_id'])) {
                $this->tenant_id = (int)$_SESSION['tenant_id'];
            }
        }
        
        if ($this->tenant_id === null || $this->tenant_id <= 0) {
            throw new Exception("Kryptografie fehlgeschlagen: Keine gültige Tenant-ID im Dashboard-Kontext gesetzt.");
        }

        // Dein ENC_KEY aus der .env dient als globales Master-Geheimnis
        $globalSecret = $this->getService('systemProcess', \App\Services\SystemProcessor::class)->getRequiredEnv('ENC_KEY');
        
        // Das 'true' am Ende liefert 32 Byte Raw Output statt eines 64-Zeichen Hex-Strings
        return hash_hmac('sha256', (string)$this->tenant_id, $globalSecret, true);
    }

    public function decrypt(string $data): ?string 
    {
        $tenantKey = $this->getTenantCryptoKey();
        $ENC_IV = $this->getService('systemProcess', \App\Services\SystemProcessor::class)->getRequiredEnv('ENC_IV');
        //$ENC_KEY= $this->getService('systemProcess', \App\Services\SystemProcessor::class)->getRequiredEnv('ENC_KEY');
        $iv = substr($ENC_IV, 0, 16);

        return openssl_decrypt($data, 'aes-256-cbc', $tenantKey, 0, $iv) ?: null;
    }
    
    public function encrypt(string $data): ?string 
    {
        $tenantKey = $this->getTenantCryptoKey();
        $ENC_IV = $this->getService('systemProcess', \App\Services\SystemProcessor::class)->getRequiredEnv('ENC_IV');
        //$ENC_KEY= $this->getService('systemProcess', \App\Services\SystemProcessor::class)->getRequiredEnv('ENC_KEY');
        $iv = substr($ENC_IV, 0, 16);

        return openssl_encrypt($data, 'aes-256-cbc', $tenantKey, 0, $iv) ?: null;
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
            false
        );

        if ($dbResult->success)
        {
            $userList = $dbResult->data->fetched;
        }else{
            $userList = null;
        }
        $user = $userList[0] ?? null; // Nimm das erste Element aus der Liste
        
        if ($user && password_verify($password, $user['password_hash'])) {
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
        error_log(__function__ . " > Überprüfe Tenant-ID: $tenantId");

        $dbResult = $this->db->queryIt(
            "SELECT id FROM tenants WHERE id = :tid LIMIT 1; --AND type = 'company' LIMIT 1", 
            ['tid' => $tenantId],
            false,
            false,
            true
        );

        $fetched = ($dbResult && $dbResult->success) ? ($dbResult->data->fetched ?? []) : [];
        self::$tenantValidated = !empty($fetched);

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
            //throw new Exception("Registrierung fehlgeschlagen: " . $t->getMessage());
        }
    }

    public function loginUser(string $email, string $password): array
    {
        // 1. Lass den Parameter-Namen exakt so wie im SQL
        $sql = "SELECT id, tenant_id,password_hash FROM users WHERE username = :email LIMIT 1";
        
        // 2. Rufe queryIt auf
        $dbResult = $this->db->queryIt(
            $sql, 
            [
                'email' => $email
            ]
        );

        // Validierung des DB-Ergebnisses (Verhindert Abstürze bei leeren Daten)
        if (!$dbResult || !$dbResult->success || empty($dbResult->data->fetched)) {
            throw new \Exception("LOGIN FEHLGESCHLAGEN: Benutzer nicht gefunden.");
        }

        $user = $dbResult->data->fetched[0];   

        // 2. Passwort prüfen (Solltest du wieder einkommentieren, sobald Hashes aktiv sind)
        if (!password_verify($password, $user['password_hash'])) {
            throw new \Exception("LOGIN FEHLGESCHLAGEN: Passwort falsch.");
        }
        
        // 3. JETZT DIE TENANT-ID REAKTIV SETZEN
        $tenantId = (int)$user['tenant_id'];
        $this->setTenantId($tenantId);

        // 4. Statt manuell $_SESSION zu befüllen -> sauber über Auth::login verarbeiten!
        $csrfToken = \App\Core\Auth::login((int)$user['id'], $tenantId);

        // 5. Gib das User-Array INKLUSIVE des neuen CSRF-Tokens an die API zurück
        return [
            'user_id'    => $user['id'],
            'tenant_id'  => $user['tenant_id'],
            'csrf_token' => $csrfToken
        ];
        
        return $user;
    }

    public function getHashSalt(): string
    {
        return defined('HASH_SALT') ? HASH_SALT : '';
    }

    /**
     * Standard-Antwort an JSON
     */
    public static function sendResponse(bool $success, $data = null, ?string $error = null, ?string $status = 'success'): void 
    {
        header('Content-Type: application/json');
        $status = $status ?? 'success';
        
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