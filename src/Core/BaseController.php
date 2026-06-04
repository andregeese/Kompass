<?php
namespace App\Core;

abstract class BaseController {
    use \App\Core\ServiceTrait; // Trait liefert jetzt alles via __get

    protected $dashboard;
    protected $request;

    public function __construct($dashboard) 
    {
        $this->dashboard = $dashboard;
        $this->request = $this->parseRequest();

        if (get_class($this) !== 'App\Api\AuthController') {
            $this->authorize();
        }
    }

    /**
     * Normalisiert alle eingehenden Daten in ein Array
     * @property \App\Core\BaseController $parseRequest
     */
    private function parseRequest(): array 
    {
        error_log(__FUNCTION__ . ": " . $_SERVER['REQUEST_METHOD']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }
        return $_GET;
    }

    protected function getUiResponse($notify, $title, $message, $type = 'success') {
        return [
            'ui' => [
                'notify' => $notify,
                'title'  => $title,
                'message'=> $message,
                'type'   => $type
            ]
        ];
    }

    protected function jsonResponse($data, $success = true) {
        $this->cleanBuffer();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'data' => $data
        ]);
        exit;
    }


    /**
     * Zentrales Bereinigen der Ausgabe
     */
    protected function cleanBuffer() {
        if (ob_get_length()) {
            ob_clean();
        }
    }
}