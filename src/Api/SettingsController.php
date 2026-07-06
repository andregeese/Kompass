<?php
/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
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
        $this->cleanBuffer();
    
        $secretKey = $this->systemProcess->tenantConfig->secret_key ?? null;
        $settings = $this->settingProcess->getTenantSettings();
        
        return $this->process($this->settingProcess, 'getTenantSettings', 'settings', '');
        
    }

    public function save() 
    {
        return $this->process(
            $this->settingProcess, 
            'saveTenantSettings', 
            'save_result', 
            '' //dumm wenn man nach jedem Sync etwas ausgibt, nervt den User
        );
    }
}