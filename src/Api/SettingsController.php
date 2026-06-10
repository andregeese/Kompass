<?php
/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */
namespace App\Api;
use Exception;
#use App\Tenant;
use App\Services\SettingProcessor; // 🚀 Das Modell aus setting.php importieren
class SettingsController extends \App\Core\BaseController {
    
    public function __construct($dashboard) {
        parent::__construct($dashboard); // Das ist entscheidend!
    }
    public function get() 
    {
        try{
            $this->cleanBuffer();
        
            $secretKey = $this->systemProcess->tenantConfig->secret_key ?? null;
            $settings = $this->settingProcess->getTenantSettings($this->tenantId, $secretKey);
            
            return [
                'success' => true,
                'details' => ['settings' => $settings]
            ];
            
        }catch(\Throwable $e){
            return [
                'success' => false,
                'details' => null,
                'error' => $e->getMessage()
            ];
        }
        
    }
    public function save() 
    {
        try 
        {
            $this->cleanBuffer();
            
            // 🚀 Formulardaten (entweder $_POST oder JSON) entgegennehmen
            $formData = $_POST;
            
            // Falls du die Daten als JSON aus dem Frontend schickst:
            if (empty($formData)) {
                $formData = json_decode(file_get_contents('php://input'), true) ?? [];
            }

            $formData = !empty($_POST) 
                ? $_POST 
                : (json_decode(file_get_contents('php://input'), true) ?? []);
            
            $secretKey = $this->tenantConfig->secret_key ?? null;
            
            $success = $this->settingProcess->saveTenantSettings($_SESSION['tenant_id'], $formData, $secretKey);
            
            if (!$success) {
                throw new \Exception("Fehler beim Aktualisieren der Einstellungen.");
            }
            
            return [
                'success' => true,
                'details' => ['message' => 'Einstellungen erfolgreich aktualisiert.']
            ];
         }catch(\Throwable $e){
            return [
                'success' => false,
                'details' => null,
                'error' => $e->getMessage()
            ];
        }
    }
}