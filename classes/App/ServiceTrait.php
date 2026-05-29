<?php
namespace App;

trait ServiceTrait {
    // Zentrales Service-Mapping
    protected static $serviceMap = [
        'mailProcess'      => \App\MailProcessor::class,
        'contactProcess'   => \App\ContactProcessor::class,
        'paperlessProcess' => \App\PaperlessProcessor::class,
        'systemProcess'    => \App\SystemProcessor::class,
        'settingProcess'   => \App\SettingProcessor::class,
        'appointProcess'   => \App\AppointmentsProcessor::class,
    ];

    // Greift auf die Eigenschaft $this->dashboard zu
    public function getService($name, $className) {
        return $this->dashboard->getService($name, $className);
    }

    public function __get($name) {
        
        if (array_key_exists($name, self::$serviceMap)) {
            if (isset($this->dashboard)) {
                return $this->dashboard->getService($name, self::$serviceMap[$name]);
            }
        }

        // 2. WICHTIG: Wenn es kein Service ist (z.B. $this->tenantConfig), 
        // dann gib null zurück, anstatt einen Fehler zu werfen!
        return null;
    }
}