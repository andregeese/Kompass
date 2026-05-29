<?php
namespace App;

class Tenant extends Database {
    private static $cipher = 'aes-256-gcm';
    public ?array $settings = []; // Direkt als leeres Array initialisieren, um Notice-Fehler zu vermeiden

    private string $tenantId;
    private string $masterKey;


    public function __construct($tenantId, $masterKey) 
    {
        error_log("Tenant construct für ID: " . $tenantId);
        $this->tenantId = $tenantId;
        $this->masterKey = $masterKey;

        try {
            $db = \App\Database::getConnection();
            
            $stmt = $db->prepare("SELECT config_key, config_value FROM tenant_config WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                error_log(print_r($row, true));

                if (str_contains($row['config_key'], 'password') || str_contains($row['config_key'], 'token')) {
                    // 🚀 WICHTIG: self::decrypt nutzen (oder Tenant::decrypt), nicht Crypto::
                    $decrypted = self::decrypt($row['config_value'], $masterKey);
                    
                    if ($decrypted === false) {
                        error_log("WARNUNG: Key '{$row['config_key']}' konnte für Tenant '{$tenantId}' nicht entschlüsselt werden. Falscher Master-Key?");
                        $this->settings[$row['config_key']] = null;
                    } else {
                        $this->settings[$row['config_key']] = $decrypted;
                    }
                } else {
                    $this->settings[$row['config_key']] = $row['row_value'] ?? $row['config_value']; // Typo-Schutz
                }
            }
        } catch (\PDOException $e) {
            error_log("Tenant-Datenbankfehler bei ID {$tenantId}: " . $e->getMessage());
            // Optional: Exception weiterwerfen, wenn die API ohne DB gar nicht erst antworten soll:
            throw new \Exception("Mandanten-Konfiguration konnte wegen eines DB-Fehlers nicht geladen werden.");
        } catch (\Throwable $e) {
            error_log("Allgemeiner Fehler im Tenant-Konstruktor bei ID {$tenantId}: " . $e->getMessage());
        }
    }

    public static function encrypt($pureText, $key) {
        try {
            $iv_length = openssl_cipher_iv_length(self::$cipher);
            $iv = openssl_random_pseudo_bytes($iv_length);
            $ciphertext = openssl_encrypt($pureText, self::$cipher, $key, $options=0, $iv, $tag);
            
            return base64_encode(json_encode([
                'c' => $ciphertext,
                'i' => base64_encode($iv),
                't' => base64_encode($tag)
            ]));
        } catch (\Throwable $e) {
            error_log("Tenant::encrypt Fehler: " . $e->getMessage());
            return false;
        }
    }

    public static function decrypt($encryptedData, $key) {
        try {
            $data = json_decode(base64_decode($encryptedData), true);
            if (!$data || !isset($data['i'], $data['t'], $data['c'])) return false;
            
            $iv = base64_decode($data['i']);
            $tag = base64_decode($data['t']);
            
            return openssl_decrypt($data['c'], self::$cipher, $key, $options=0, $iv, $tag);
        } catch (\Throwable $e) {
            error_log("Tenant::decrypt Fehler: " . $e->getMessage());
            return false;
        }
    }
    

    public function get($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    // 💡 Tipp: Nutze den Konstruktor in getTenant, um Code-Duplizierung zu vermeiden!
    public function getTenant($tenantId, $masterKey) {
        $this->__construct($tenantId, $masterKey);
    }
}