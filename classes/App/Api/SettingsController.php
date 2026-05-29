<?php
namespace App\Api;

use Exception;
use App\Tenant;
use App\SettingProcessor; // 🚀 Das Modell aus setting.php importieren

class SettingsController extends BaseController {
    
    public function __construct($dashboard) {
        parent::__construct($dashboard); // Das ist entscheidend!
    }

    public function get() 
    {
        $this->cleanBuffer();
        
        $secretKey = $this->systemProcess->tenantConfig->secret_key ?? null;
        $settings = $this->settingProcess->getTenantSettings($this->tenantId, $secretKey);
        
        return [
            'success' => true,
            'details' => ['settings' => $settings]
        ];
    }

    public function save() 
    {
        $this->cleanBuffer();
        
        // 🚀 Formulardaten (entweder $_POST oder JSON) entgegennehmen
        $formData = $_POST;
        
        // Falls du die Daten als JSON aus dem Frontend schickst:
        if (empty($formData)) {
            $formData = json_decode(file_get_contents('php://input'), true) ?? [];
        }

        
        $secretKey = $this->tenantConfig->secret_key ?? null;

        $success = $this->settingProcess->saveTenantSettings($_SESSION['tenant_id'], $formData, $secretKey);
        
        if (!$success) {
            throw new \Exception("Fehler beim Aktualisieren der Einstellungen.");
        }
        
        return [
            'success' => true,
            'details' => ['message' => 'Einstellungen erfolgreich aktualisiert.']
        ];
    }
}