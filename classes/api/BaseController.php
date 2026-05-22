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
     * Proxy-Methode: So kann ich in Controllern einfach $this->xyz->... schreiben
     * @property \App\MailProcessor $mail
     * @property \App\ContactProcessor $contact
     * @property \App\PaperlessProcessor $processor
     */
    public function __get($name) {
        $services = [
            'mail'    => \App\MailProcessor::class,
            'contact' => \App\ContactProcessor::class,
            'paperless' => \App\PaperlessProcessor::class
        ];

        if (array_key_exists($name, $services)) {
            return $this->getService($name, $services[$name]);
        }
    }

    /**
     * Lazy-Loader für Services
     */
    protected function getService($name, $className) {
        if (!isset($this->services[$name])) {
            // Instanziiert den Service nur bei Bedarf
            $this->services[$name] = new $className($this->dashboard);
        }
        return $this->services[$name];
    }

    protected function cleanBuffer() {
        if (ob_get_length()) {
            ob_clean();
        }
    }
}