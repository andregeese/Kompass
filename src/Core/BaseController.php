<?php
namespace App\Core;

abstract class BaseController {
    use \App\Core\ServiceTrait; // Trait liefert jetzt alles via __get

    protected $dashboard;

    public function __construct($dashboard) {
        $this->dashboard = $dashboard;

        if (get_class($this) !== 'App\Api\AuthController') {
            $this->authorize();
        }
    }

    protected function getJsonInput(): array {
        return json_decode(file_get_contents('php://input'), true) ?? [];
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