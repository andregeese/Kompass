<?php
namespace App\Api;

abstract class BaseController {
    use \App\ServiceTrait; // Trait liefert jetzt alles via __get

    protected $dashboard;
    protected $tenantConfig;

    public function __construct($dashboard) {
        $this->dashboard = $dashboard;
        
        // Tenant Logik hier nur, wenn unbedingt nötig
        if ($tid = $_SESSION['tenant_id'] ?? null) {
            $masterKey = $this->systemProcess->getRequiredEnv('APP_KEY');
            $this->tenantConfig = new \App\Tenant($tid, $masterKey);
        }
    }

    /**
     * Zentrales Bereinigen der Ausgabe
     */
    protected function cleanBuffer() {
        if (ob_get_length()) {
            ob_clean();
        }
    }

    // Zentrale API-Hilfsmethoden
    protected function jsonResponse($data) {
        $this->cleanBuffer();
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}