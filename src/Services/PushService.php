<?php
/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */
namespace App\Services;

// src/Services/PushService.php
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushService extends \App\Core\Database 
{
    public function sendPushNotification(int $userId, string $title, string $body) {
        
        // 1. Hole die Subscription aus der DB für diese User-ID
        $sql = "SELECT subscription_json FROM push_subscriptions WHERE user_id = :user_id";
        
        // WICHTIG: Passe das hier so an, wie deine queryIt-Methode Werte zurückgibt (z.B. ein fetch() oder array)
        // Das hier ist symbolisch gemeint, falls queryIt ein Array von Ergebnissen zurückgibt:
        $result = $this->queryIt($sql, ['user_id' => $userId]); 
        
        if (empty($result) || empty($result[0]['subscription_json'])) {
            // User hat keinen Push abonniert, einfach abbrechen.
            return; 
        }
        $subscriptionJson = $result[0]['subscription_json'];
        $auth = [
            'VAPID' => [
                'subject' => 'mailto:andregeese@me.com', // KEINE spitzen Klammern!
                'publicKey' => 'BHPDfcBzsQ5-csRk7GePqcvO0QtZ7yejB-D2bRyNQbW7eilsVrPaVAUQuKtFreNog6SmD0FfmOTBoSw-XhKHW_I', 
                'privateKey' => 'GJYkpGb_zJTeU69eh-LnO-EYHVmJBfhSSl3-KcB8EPQ'     
            ],
        ];
        $webPush = new WebPush($auth);
        // Das JSON in ein Array dekodieren und an create() übergeben
        $subscriptionArray = json_decode($subscriptionJson, true);
        if ($subscriptionArray === null) return; // Wenn JSON kaputt ist, abbrechen
        $subscription = Subscription::create($subscriptionArray);
        $payload = json_encode([
            'title' => $title,
            'body' => $body
        ]);
        $report = $webPush->sendOneNotification($subscription, $payload);
        if (!$report->isSuccess()) {
            // Wenn der Browser dem User die Berechtigung entzogen hat:
            // error_log("Push fehlgeschlagen: " . $report->getReason());
        }
    }

    public function saveSubscription(int $userId, string $subscriptionJson)
    {
        // 1. Hole deine Datenbankverbindung (Passe das an dein System an,
        // je nachdem wie deine Database.php funktioniert)
        
        // 2. Der SQL-Befehl
        // Wir nutzen ON DUPLICATE KEY UPDATE. Wenn der Browser seine Keys
        // erneuert und erneut an den Server schickt, überschreiben wir den alten Eintrag einfach.
        $sql = "INSERT INTO push_subscriptions (user_id, subscription_json, created_at) 
                VALUES (:user_id, :json, NOW())
                ON DUPLICATE KEY UPDATE subscription_json = :json, created_at = NOW()";
        
        $params = [
            'user_id' => $userId,
            'json'    => $subscriptionJson
        ];
        // 3. In die Datenbank eintragen
        
        $this->queryIt($sql, $params, false, false, false);

        
    }
}
