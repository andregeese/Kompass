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
use Override;
use PDO;
use App\Services\SystemProcessor;
/**
 * @property \App\Services\SettingProcessor $settingProcess
 */
class SettingProcessor extends \App\Core\Database 
{
    use \App\Core\ServiceTrait;
    protected $dashboard;

    public function __construct($dashboard)
    {
        $this->dashboard = $dashboard;
        parent::__construct($dashboard);
    }

    public function getTenantSettings(): array
    {
        error_log(__FUNCTION__ . " called");

        $sql = "SELECT config_key, config_value FROM tenant_config WHERE tenant_id = :tenant_id";
        $dbResult = $this->queryIt($sql, [], false, false, false);

        if (!$dbResult->success) {
            throw new \Exception("Einstellungen konnten nicht geladen werden!");
        }

        $rows = $dbResult->data->fetched;
        $encryptionKey = $this->systemProcess->getRequiredEnv('APP_KEY');
        
        $settings = [];

        if (empty($encryptionKey)) {
            throw new \Exception("APP_KEY ist leer oder nicht definiert!");
        }

        foreach ($rows as $row) {
            $key = $row['config_key'];
            $val = $row['config_value'];

            try {
                // Sensible Werte für das Frontend entschlüsseln
                if (in_array($key, ['PAPERLESS_TOKEN', 'IMAP_PASSWORD', 'IMAP_USER'])) {
                    if (empty($val)) {
                        $settings[$key] = '';
                        continue;
                    }
                    $settings[$key] = \App\Core\Crypto::decrypt($val, $encryptionKey);
                } else if ($key === "SYNC_FOLDERS") {
                    $settings[$key] = json_decode($val, true) ?? [];
                } else {
                    $settings[$key] = $val;
                }
            } catch (\Throwable $e) {
                error_log("CRASH in Loop bei Key {$key}: " . $e->getMessage());
                $settings[$key] = null; 
            }
        }
    
        return $settings;
    }

    /**
     * Aktualisiert oder erstellt die Einstellungen eines Mandanten
     */
    public function saveTenantSettings($data)
    {
        $encryptionKey = $this->systemProcess->getRequiredEnv('APP_KEY');
    
        // 1. PAPERLESS_NAME zu den erlaubten Keys hinzugefügt
        $allowedKeys = [
            'IMAP_HOST', 'IMAP_PORT', 'IMAP_USER', 'IMAP_PASSWORD', 
            'PAPERLESS_URL', 'PAPERLESS_NAME', 'PAPERLESS_TOKEN', 
            'SYNC_FOLDERS', 'DMS_FOLDER', 'SENT_FOLDER'
        ];
        
        $valuesToInsert = [];
        $params = [];
        $i = 0; // 2. Variable $i ordnungsgemäß initialisiert

        foreach ($data as $key => $val) {
            $key = strtoupper($key); 
            if (!in_array($key, $allowedKeys)) continue;
            if (in_array($key, ['IMAP_PASSWORD', 'PAPERLESS_TOKEN']) && empty($val)) continue;

            // 3. PAPERLESS_NAME aus der Verschlüsselung entfernt (bleibt Klartext)
            if (in_array($key, ['PAPERLESS_TOKEN', 'IMAP_PASSWORD', 'IMAP_USER'])) {
                $finalValue = \App\Core\Crypto::encrypt($val, $encryptionKey);
            } else {
                $finalValue = ($key === 'SYNC_FOLDERS') ? json_encode($val, JSON_UNESCAPED_UNICODE) : $val;
            }

            $valuesToInsert[] = "(:tenant_id, :config_key_$i, :config_value_$i)";
            $params["config_key_$i"]   = $key;
            $params["config_value_$i"] = $finalValue;
            
            $i++;
        }

        if (empty($valuesToInsert)) {
            return (object)['success' => true, 'message' => 'Keine Änderungen zu speichern.'];
        }

        $sql = "INSERT INTO tenant_config (tenant_id, config_key, config_value) 
                VALUES " . implode(', ', $valuesToInsert) . "
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)";
        
        $dbResult = $this->queryIt($sql, $params, false, false, true);

        return ($dbResult->success && !empty($dbResult->data)) ? $dbResult : null;
    }
}