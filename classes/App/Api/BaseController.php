<?php
namespace App\Api;

abstract class BaseController {
    use \App\ServiceTrait; // Trait liefert jetzt alles via __get

    protected $dashboard;

    public function __construct($dashboard) {
        $this->dashboard = $dashboard;
        
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