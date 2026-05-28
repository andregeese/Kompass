<?php
namespace App;

use Sabre\DAV\Client;

class ICloudConnector {
    private $tenantId;
    private $settings;

    public function __construct($tenantId) {
        $this->tenantId = $tenantId;
        $settingManager = new \App\Setting();
        $this->settings = $settingManager->getTenantSettings($tenantId);
    }

    public function getAppointments() {
        // Daten aus der DB holen
        $user = $this->settings['icloud_user'] ?? null;
        $pass = $this->settings['icloud_password'] ?? null;

        if (!$user || !$pass) {
            throw new \Exception("iCloud Zugangsdaten fehlen.");
        }

        $settings = [
            'baseUri' => 'https://caldav.icloud.com/',
            'userName' => $user,
            'password' => $pass,
        ];

        $client = new Client($settings);

        // Hier müsstest du die URL deines speziellen Kalenders abrufen.
        // Vereinfacht: Anfrage an den Server zur Synchronisation
        try {
            // Beispiel: Abruf der Events (SabreDAV Report Request)
            $response = $client->request('REPORT', 'calendars/' . $user . '/calendar/', '
                <c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
                    <d:prop>
                        <c:calendar-data/>
                    </d:prop>
                    <c:filter>
                        <c:comp-filter name="VCALENDAR"/>
                    </c:filter>
                </c:calendar-query>');
            
            return $response['body']; // Dies enthält das XML/iCal Format zum Parsen
        } catch (\Exception $e) {
            error_log("iCloud Fehler: " . $e->getMessage());
            return [];
        }
    }
}