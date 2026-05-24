<?php
namespace App\Api;

use Exception;
use App\Setting; // 🚀 Das Modell aus setting.php importieren

class SettingsController extends BaseController {
    
    public function get() {
        $this->cleanBuffer();
        
        // 🚀 Modell instanziieren, damit wir an die DB kommen
        $settingModel = new Setting($this->dashboard);
        $secretKey = $this->tenantConfig->secret_key ?? null;
        $settings = $settingModel->getTenantSettings($this->tenantId, $secretKey);
        
        return [
            'success' => true,
            'details' => ['settings' => $settings]
        ];
    }

    public function save() {
        $this->cleanBuffer();
        
        // 🚀 Formulardaten (entweder $_POST oder JSON) entgegennehmen
        $formData = $_POST;
        
        // Falls du die Daten als JSON aus dem Frontend schickst:
        if (empty($formData)) {
            $formData = json_decode(file_get_contents('php://input'), true) ?? [];
        }

        $settingModel = new Setting(($this->dashboard));
        
        $secretKey = $this->tenantConfig->secret_key ?? null;

        $success = $settingModel->saveTenantSettings($this->tenantId, $formData, $secretKey);
        
        if (!$success) {
            throw new \Exception("Fehler beim Aktualisieren der Einstellungen.");
        }
        
        return [
            'success' => true,
            'details' => ['message' => 'Einstellungen erfolgreich aktualisiert.']
        ];
    }
}