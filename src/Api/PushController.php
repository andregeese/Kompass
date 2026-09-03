<?php

namespace App\Api;

use App\Services\PushService; // Wir legen aus Prinzip einen Processor dafür an
use Exception;

class PushController extends \App\Core\BaseController
{
    //private PushProcessor $pushProcessor;

    public function __construct($dashboard)
    {
        parent::__construct($dashboard); // Das ist entscheidend!
    }

    /**
     * Nimmt das Subscription-Objekt vom JavaScript (Frontend) an.
     * Endpunkt z. B.: /api/push/subscribe (je nach deinem Routing)
     */
    public function subscribe()
    {
        try {
            // 1. Raw-JSON Body aus dem Frontend-Fetch lesen
            $json = file_get_contents('php://input');
            $subscriptionData = json_decode($json, true);

            // Grundlegende Validierung
            if (!$subscriptionData || !isset($subscriptionData['endpoint'])) {
                throw new Exception('Ungültige Subscription-Daten empfangen.', 400);
            }

            // 2. Aktuellen User ermitteln (Du hast Auth.php im Core, passe das hier logisch an)
            // Hier ein Platzhalter - setze hier deine Methode ein, um den eingeloggten User zu bekommen:
            $userId = $_SESSION['user_id'] ?? null; 

            if (!$userId) {
                throw new Exception('Nicht authentifiziert.', 401);
            }

            // 3. Abo in der Datenbank speichern
            // Wir übergeben das pure JSON, da WebPush-Bibliotheken genau dieses Format erwarten
            $this->pushService->saveSubscription($userId, $json);

            // 4. Response senden
            return [
                'success' => true,
                'message' => 'Subscription erfolgreich gespeichert.'
            ];

        } catch (Exception $e) {
            $code = $e->getCode() ?: 500;
            http_response_code($code);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Hilfsfunktion für JSON-Responses (falls du so etwas nicht schon im BaseController hast)
     */
    private function sendJsonResponse(array $data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
