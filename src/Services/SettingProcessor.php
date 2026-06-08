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
/**
 * @property \App\Services\SettingProcessor $settingProcess
 */
class SettingProcessor extends \App\Core\Database 
{
    use \App\Core\ServiceTrait;
    public function __construct($dashboard)
    {
        $this->dashboard = $dashboard;
        parent::__construct($dashboard);
    }
    public function getTenantSettings($tenantId, $encryptionKey=null): array
        {
            $sql = "SELECT config_key, config_value FROM tenant_config WHERE tenant_id = :tenant_id";
            $rows = $this->queryIt($sql, ['tenant_id' => $tenantId]);
            if (!$encryptionKey) {
                $encryptionKey = $this->systemProcess->getRequiredEnv('APP_KEY');
            }
            $settings = [];
            foreach ($rows as $row) {
                $key = $row['config_key'];
                $val = $row['config_value'];
                // Sensible Werte für das Frontend entschlüsseln
                if (in_array($key, ['PAPERLESS_NAME', 'PAPERLESS_TOKEN', 'IMAP_PASSWORD', 'IMAP_USER'])) {
                    $settings[$key] = \App\Crypto::decrypt($val, $encryptionKey);
                } else {
                    $settings[$key] = $val;
                }
            }
            return $settings;
        }
    /**
     * Aktualisiert oder erstellt die Einstellungen eines Mandanten
     */
    public function saveTenantSettings($tenantId, $data, $encryptionKey=null): bool
    {
        error_log(__FILE__ . ": " . "aufgerufen");
        // Falls kein Key übergeben wurde, greifen wir auf die globale Konstante zurück
        if (!$encryptionKey || $encryptionKey == null || trim($encryptionKey) =="") {
            $encryptionKey = $this->systemProcess->getRequiredEnv('APP_KEY');
        }
        $allowedKeys = ['IMAP_HOST', 'IMAP_PORT', 'IMAP_USER', 'IMAP_PASSWORD', 'PAPERLESS_URL', 'PAPERLESS_NAME', 'PAPERLESS_TOKEN'];
        
        $sql = "INSERT INTO tenant_config (tenant_id, config_key, config_value) 
                VALUES (:tenant_id, :config_key, :config_value)
                ON DUPLICATE KEY UPDATE config_value = :config_value_update";
        error_log(print_r($sql, true));
        foreach ($data as $key => $val) {
            // 1. Key normieren: Alles groß
            $key = strtoupper($key); 
            
            if (!in_array($key, $allowedKeys)) continue;
            // 2. Jetzt funktionieren alle in_array Checks sicher
            if (in_array($key, ['IMAP_PASSWORD', 'PAPERLESS_TOKEN']) && empty($val)) {
                continue;
            }
            if (in_array($key, ['PAPERLESS_NAME', 'PAPERLESS_TOKEN', 'IMAP_PASSWORD', 'IMAP_USER'])) {
                error_log($key . ": VOR DER VERSCHLÜSSELUNG");
                $finalValue = \App\Core\Crypto::encrypt($val, $encryptionKey);
            } else {
                error_log($key . ": IM KLARTEXT");
                $finalValue = $val;
            }
            
            $this->queryIt($sql, [
                'tenant_id'           => $tenantId,
                'config_key'          => $key,
                'config_value'        => $finalValue,
                'config_value_update' => $finalValue
            ]);
        }
        return true;
    }
}