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
                    if (in_array($key, ['PAPERLESS_TOKEN', 'IMAP_PASSWORD', 'IMAP_USER'])) {
                        
                        // Falls der Wert in der DB leer ist, macht Entschlüsseln keinen Sinn
                        if (empty($val)) {
                            $settings[$key] = '';
                            continue;
                        }

                        $settings[$key] = \App\Core\Crypto::decrypt($val, $encryptionKey);
                        error_log("Decrypted Value for {$key}: " . $settings[$key]);
                    } else if ($key == "SYNC_FOLDERS") {
                        // Falls val kein valides JSON ist, fängt json_decode das ab (gibt null aus)
                        $settings[$key] = json_decode($val, true) ?? [];
                        error_log("Plain Value for {$key}: " . $settings[$key]);
                        
                    } else {
                        $settings[$key] = $val;
                        error_log("Plain Value for {$key}: " . $settings[$key]);
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
        
        // :tenant_id bleibt im SQL, da injectTenantId() es befüllt
        $sql = "INSERT INTO tenant_config (tenant_id, config_key, config_value) 
                VALUES (:tenant_id, :config_key, :config_value)
                ON DUPLICATE KEY UPDATE config_value = :config_value_update";
    
        foreach ($data as $key => $val) {
            $key = strtoupper($key); 
            if (!in_array($key, $allowedKeys)) continue;
            if (in_array($key, ['IMAP_PASSWORD', 'PAPERLESS_TOKEN']) && empty($val)) continue;

            if (in_array($key, ['PAPERLESS_NAME', 'PAPERLESS_TOKEN', 'IMAP_PASSWORD', 'IMAP_USER'])) {
                $finalValue = \App\Core\Crypto::encrypt($val, $encryptionKey);
            } else {
                $finalValue = ($key === 'SYNC_FOLDERS') ? json_encode($val, JSON_UNESCAPED_UNICODE) : $val;
            }

            // Trick: Wir nutzen ':tenant_id' statisch in JEDER Zeile. 
            // injectTenantId() injiziert den Wert einmalig in $params['tenant_id'], 
            // und PDO verteilt ihn beim Execute auf alle Zeilen.
            $valuesToInsert[] = "(:tenant_id, :config_key_$i, :config_value_$i)";
            
            $params["config_key_$i"]   = $key;
            $params["config_value_$i"] = $finalValue;
            
            $i++;
        }

        if (empty($valuesToInsert)) {
            return (object)['success' => true, 'message' => 'Keine Daten zu speichern.'];
        }

        error_log("Prepared Values: " . print_r($valuesToInsert, true));

        // Falls keine validen Daten übrig geblieben sind, direkt abbrechen
        if (empty($valuesToInsert)) {
            return ['success' => true, 'message' => 'Keine Änderungen zu speichern.'];
        }

        // Das SQL-Statement dynamisch zusammenbauen
        // Nutzt VALUES(spaltenname), um sich auf den jeweils reingereichten Wert zu beziehen
        // Aufbau des finalen Statements
        $sql = "INSERT INTO tenant_config (tenant_id, config_key, config_value) 
                VALUES " . implode(', ', $valuesToInsert) . "
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)";
        
        error_log("SQL: " . $sql, true);

        $dbResult = $this->queryIt($sql, $params, false, false, true);


        return ($dbResult->success && !empty($dbResult->data) && !empty($dbResult->data->fetched))
            ?  $dbResult
            : null;
    }
}