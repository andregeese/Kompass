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
            error_log("GET FROM DB");
            $sql = "SELECT config_key, config_value FROM tenant_config WHERE tenant_id = :tenant_id";
            $dbResult = $this->queryIt(
                $sql, 
                [], 
                false, 
                false,
                false
            );

            if (!$dbResult->success)
            {
                throw new \Exception("Einstellungen konnten nicht geladen werden!");
            }

            $rows = $dbResult->data->fetched;

            
            $encryptionKey = $this->systemProcess->getRequiredEnv('APP_KEY');
            
            $settings = [];

            // Temporärer Check: Ist der Key überhaupt da?
            if (!isset($encryptionKey) || empty($encryptionKey)) {
                throw new \Exception("KREISCH: \$encryptionKey ist leer oder nicht definiert!");
            }

            foreach ($rows as $row) {
                $key = $row['config_key'];
                $val = $row['config_value'];
                error_log("Processing Key: " . $key);

                try {
                    // Sensible Werte für das Frontend entschlüsseln
                    if (in_array($key, ['PAPERLESS_URL', 'PAPERLESS_TOKEN', 'IMAP_PASSWORD', 'IMAP_USER'])) {
                        
                        // Falls der Wert in der DB leer ist, macht Entschlüsseln keinen Sinn
                        if (empty($val)) {
                            $settings[$key] = '';
                            continue;
                        }

                        $settings[$key] = \App\Core\Crypto::decrypt($val, $encryptionKey);
                        
                    } else if ($key == "SYNC_FOLDERS") {
                        // Falls val kein valides JSON ist, fängt json_decode das ab (gibt null aus)
                        $settings[$key] = json_decode($val, true) ?? [];
                    } else {
                        $settings[$key] = $val;
                    }
                } catch (\Throwable $e) {
                    // JEDER Fehler (auch Fatal/TypeError) im Loop wird jetzt hier geloggt!
                    error_log("CRASH in Loop bei Key {$key}: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
                    
                    // Optional: Setze einen leeren Fallback, damit das Skript nicht stirbt
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
        // Falls kein Key übergeben wurde, greifen wir auf die globale Konstante zurück
        $encryptionKey = $this->systemProcess->getRequiredEnv('APP_KEY');
    
        $allowedKeys = ['IMAP_HOST', 'IMAP_PORT', 'IMAP_USER', 'IMAP_PASSWORD', 'PAPERLESS_URL', 'PAPERLESS_TOKEN','SYNC_FOLDERS', 'DMS_FOLDER', 'SENT_FOLDER'];
        
        $sql = "INSERT INTO tenant_config (tenant_id, config_key, config_value) 
                VALUES (:tenant_id, :config_key, :config_value)
                ON DUPLICATE KEY UPDATE config_value = :config_value_update";
        
        $dbResult = [];
        foreach ($data as $key => $val) 
        {
            // 1. Key normieren: Alles groß
            $key = strtoupper($key); 
            
            if (!in_array($key, $allowedKeys)) continue;
            // 2. Jetzt funktionieren alle in_array Checks sicher
            if (in_array($key, ['IMAP_PASSWORD', 'PAPERLESS_TOKEN']) && empty($val)) {
                continue;
            }

            if (in_array($key, ['PAPERLESS_NAME', 'PAPERLESS_TOKEN', 'IMAP_PASSWORD', 'IMAP_USER'])) {
                $finalValue = \App\Core\Crypto::encrypt($val, $encryptionKey);
            } else {
                if ($key== 'SYNC_FOLDERS'){
                    $syncFolders = $val;
                    $finalValue = json_encode($syncFolders, JSON_UNESCAPED_UNICODE);
                    
                }else{
                    $finalValue = $val;

                }
            }
            
            $dbResult[] = $this->queryIt(
                $sql, 
                [
                    'config_key'          => $key,
                    'config_value'        => $finalValue,
                    'config_value_update' => $finalValue
                ],
                false,
                false,
                false
            );
        }

        return ['settings' => $dbResult];
    }
}