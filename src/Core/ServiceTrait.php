<?php
/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

namespace App\Core;
trait ServiceTrait 
{
    // Diese Methode wird in Controllern aufgerufen
    protected function authorize() {
        Auth::init();
        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }
    // Zentrales Service-Mapping
    protected static $serviceMap = [
        'mailProcess'      => \App\Services\MailProcessor::class,
        'contactProcess'   => \App\Services\ContactProcessor::class,
        'paperlessProcess' => \App\Services\PaperlessProcessor::class,
        'systemProcess'    => \App\Services\SystemProcessor::class,
        'settingProcess'   => \App\Services\SettingProcessor::class,
        'appointProcess'   => \App\Services\AppointmentsProcessor::class,
        'notesProcess'     => \App\Services\NotesProcessor::class,
        'pushService'      => \App\Services\PushService::class,
    ];
    // Greift auf die Eigenschaft $this->dashboard zu
    public function getService($name, $className) {
        return $this->dashboard->getService($name, $className);
    }
    public function __get($name) {
        if (array_key_exists($name, self::$serviceMap)) {
            // Prüfung: Dashboard vorhanden?
            if (!isset($this->dashboard)) {
                throw new \RuntimeException("Dashboard nicht initialisiert für Service: $name");
            }
            
            return $this->dashboard->getService($name, self::$serviceMap[$name]);
        }
        // 2. WICHTIG: Wenn es kein Service ist (z.B. $this->tenantConfig), 
        // dann gib null zurück, anstatt einen Fehler zu werfen!
        return null;
    }
}