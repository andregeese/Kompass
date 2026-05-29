<?php
namespace App;

use Override;
use PDO;

/**
 * @property \App\SettingProcessor $settingProcess
 */
class SettingProcessor extends Database 
{
    use ServiceTrait;

    public function __construct($dashboard)
    {
        parent::__construct($dashboard);
        $this->dashboard = $dashboard;
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
                if (in_array($key, ['paperless_name', 'paperless_token', 'imap_password', 'imap_user'])) {
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
        // Falls kein Key übergeben wurde, greifen wir auf die globale Konstante zurück
        if (!$encryptionKey || $encryptionKey == null || trim($encryptionKey) =="") {
            $encryptionKey = $this->systemProcess->getRequiredEnv('APP_KEY');
        }

        $allowedKeys = ['IMAP_HOST', 'IMAP_PORT', 'IMAP_USER', 'IMAP_PASSWORD', 'PAPERLESS_URL', 'PAPERLESS_NAME', 'PAPERLESS_TOKEN'];
        
        $sql = "INSERT INTO tenant_config (tenant_id, config_key, config_value) 
                VALUES (:tenant_id, :config_key, :config_value)
                ON DUPLICATE KEY UPDATE config_value = :config_value_update";

        //error_log(print_r($data, true));

        foreach ($data as $key => $val) {
            if (!in_array($key, $allowedKeys)) continue;

            // Wenn Passwörter leer übertragen werden, überspringen (nicht überschreiben)
            if (in_array($key, ['imap_password', 'paperless_token']) && empty($val)) {
                continue;
            }

            // Verschlüsselungs-Weiche
            if (in_array($key, ['paperless_name', 'paperless_token', 'imap_password', 'imap_user'])) {
                $finalValue = \App\Crypto::encrypt($val, $encryptionKey);
            } else {
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