<?php
namespace App\Api;

abstract class BaseController {
    protected $dashboard;

    // Registry für geladene Services
    private $services = [];

    public function __construct($dashboard) {
        $this->dashboard = $dashboard;
    }

    /**
     * Lazy-Loader für Services
     */
    // Proxy: Controller fragt das Dashboard
    protected function getService($name, $className) {
        return $this->dashboard->getService($name, $className);
    }
    
    /**
     * Proxy-Methode: So kann ich in Controllern einfach $this->xyz->... schreiben
     * @property \App\MailProcessor $mailProcess
     * @property \App\ContactProcessor $contactProcess
     * @property \App\PaperlessProcessor $processorProcess
     */
    public function __get($name) {
        $services = [
            'mailProcess'    => \App\MailProcessor::class,
            'contactProcess' => \App\ContactProcessor::class,
            'paperlessProcess' => \App\PaperlessProcessor::class,
            'systemProcess' => \App\SystemProcessor::class
        ];

        if (array_key_exists($name, $services)) {
            return $this->getService($name, $services[$name]);
        }
    }

    

    protected function cleanBuffer() {
        if (ob_get_length()) {
            ob_clean();
        }
    }
}