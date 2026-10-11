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

abstract class BaseController
{
    use \App\Core\ServiceTrait; // Kann jetzt sein eigenes __get behalten, wir stören uns nicht mehr
    protected $dashboard;
    protected object $request; // 💡 Fest als Objekt deklariert


    public function __construct($dashboard)
    {
        // Debug-Lines zum Lösen des Rätsels:
        //error_log("BASECONTROLLER: Geladen von Klasse: " . get_class($this));
        $this->dashboard = $dashboard;

        $this->request = $this->buildRequestObject();
        //error_log("BASECONTROLLER: Request Payload ist: " . print_r($this->request->all(), true));
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
            public function __construct(array $data)
            {
                $this->payload = $data;
            }

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
            public function __get(string $name)
            {
                return $this->get($name);
            }

            public function offsetGet($offset)
            {
                return $this->get($offset);
            }

            public function offsetExists($offset): bool
            {
                return $this->get($offset) !== null;
            }
            public function offsetSet($offset, $value): void
            {
                $this->payload[$offset] = $value;
            }
            public function offsetUnset($offset): void
            {
                unset($this->payload[$offset]);
            }
            public function all(): array
            {
                return $this->payload;
            }
        };
    }

    /**
     * kümmert sich um die Api
     */
    protected function process($processor, string $method, string $key, string $successMessage = ''):array
    {
        $reflector = new \ReflectionMethod($processor, $method);
        $params = $reflector->getParameters();
        $args = [];

        foreach ($params as $param) {
            $name = $param->getName();
            $value = $this->request[$name] ?? null;

            // Prüfen auf Fehlendes oder leeres String-Argument
            if ($value === null || ($param->getType() == 'string' && trim((string)$value) === '')) {
                if ($param->isOptional()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    throw new \Exception("Parameter '$name' darf nicht leer sein.");
                }
            } else {
                $args[] = $value;
            }
        }

        $result = call_user_func_array([$processor, $method], $args);

        // Prüfen, ob das Ergebnis das "queryIt"-Format hat (Objekt mit ->success und ->data)
        if (is_object($result) && isset($result->success)) {
            if (!$result->success) {
                // Datenbankfehler aus dem Objekt extrahieren
                throw new \Exception($result->error ?? "Unbekannter Datenbankfehler");
            }
            // NUR die Daten extrahieren, Debug-Kram wegschmeißen
            $dataToReturn = $result->data->fetched ?? $result->data->lastId ?? true;
        } else {
            $dataToReturn = $result;
        }

        $details = [$key => $dataToReturn];

        // 2. KORREKTUR: Wenn eine echte Nachricht übergeben wurde, füttern wir deinen Steuerknoten!
        if (trim($successMessage) !== '' && $successMessage !== 'success') {
            $details['ui'] = [
                'notify'  => true,
                'title'   => 'Erfolg',
                'message' => $successMessage,
                'type'    => 'success' // "success", "info", "warning", "danger"
            ];
        }

        //Das vollständige Paket für api.php packen
        return [
            'success' => true,
            'status'  => trim($successMessage) !== '' ? $successMessage : 'success',
            'details' => $details,
            'error'   => null
        ];

    }

    protected function jsonResponse($data, $success = true)
    {
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
    protected function cleanBuffer()
    {
        if (ob_get_length()) {
            ob_clean();
        }
    }

    /**
     * @tbd: Authentifiziert einen Benutzer und gibt die Benutzerinformationen zurück
     * Speichert einen Push-Abonnement-Eintrag in der Datenbank
     * deprectated: Diese Methode ist veraltet und sollte nicht mehr verwendet werden. Verwenden Sie stattdessen die dedizierte Authentifizierungs- und Push-Abonnement-Logik.
     * @param string $username Der Benutzername des Benutzers
     * @param string $password Das Passwort des Benutzers
     * @return array Die Benutzerinformationen, wenn die Authentifizierung erfolgreich ist
    
     */
    public function savePushSubscription($data) {
        // Speichere $data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth']
        // in deiner Datenbank, verknüpft mit der User-ID.
        
        //$this->db->execute("INSERT INTO push_subscriptions (...) VALUES (...)");
        return ['success' => true];
    }
}
