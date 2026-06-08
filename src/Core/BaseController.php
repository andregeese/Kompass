<?php
/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

namespace App\Core;
abstract class BaseController {
    use \App\Core\ServiceTrait; // Kann jetzt sein eigenes __get behalten, wir stören uns nicht mehr
    protected $dashboard;
    protected object $request; // 💡 Fest als Objekt deklariert
    public function __construct($dashboard) 
    {
        error_log("BASECONTROLLER: Geladen von Klasse: " . get_class($this));
        $this->dashboard = $dashboard;
        
        $this->request = $this->buildRequestObject();
        error_log("BASECONTROLLER: Request Payload ist: " . print_r($this->request->all(), true));
        if (is_object($this->dashboard)) {
            $this->dashboard->request = $this->request;
            error_log("BASECONTROLLER: Request an Dashboard übergeben.");
        }
        
        if (get_class($this) !== 'App\Api\AuthController') {
            $this->authorize();
        }
    }
    /**
     * Normalisiert alle eingehenden Daten in ein Array
     * @property \App\Core\BaseController $parseRequest
     */
    private function buildRequestObject(): object 
    {
        $data = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($data) && !empty($_POST)) {
                $data = $_POST;
            }
        } else {
            $data = $_GET ?? [];
        }
        return new class($data) implements \ArrayAccess {
            private array $payload;
            public function __construct(array $data) { $this->payload = $data; }
            
            /**
             * 💡 Die ultimative Formular-Strategie:
             * Holt Daten flach oder per Punkt-Notation ("mail.uid") und erlaubt Default-Werte!
             */
            public function get(string $key, $default = null) 
            {
                // 1. Wenn der Key direkt existiert (z.B. flaches JSON)
                if (array_key_exists($key, $this->payload)) {
                    return $this->payload[$key] !== '' ? $this->payload[$key] : $default;
                }
                
                // 2. Punkt-Notation auflösen (z.B. "mail.uid" wenn verschachtelt)
                $target = $this->payload;
                foreach (explode('.', $key) as $segment) {
                    if (is_array($target) && array_key_exists($segment, $target)) {
                        $target = $target[$segment];
                    } else {
                        return $default;
                    }
                }
                
                return $target !== '' ? $target : $default;
            }
            // Magische Methoden leiten alle auf get() um
            public function __get(string $name) { return $this->get($name); }
            public function offsetGet($offset) { return $this->get($offset); }
            
            public function offsetExists($offset): bool { return $this->get($offset) !== null; }
            public function offsetSet($offset, $value): void { $this->payload[$offset] = $value; }
            public function offsetUnset($offset): void { unset($this->payload[$offset]); }
            public function all(): array { return $this->payload; }
        };
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