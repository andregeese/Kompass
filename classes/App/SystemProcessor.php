<?php

namespace App;
use App\ContactProcessor;
use Exception; // Falls du Exceptions nutzt
use Throwable;
use PDO;

class SystemProcessor extends Database
{
    use ServiceTrait;
    protected $dashboard;

    public function __construct($dashboard) {
        parent::__construct($dashboard);
        $this->dashboard = $dashboard;
    }

    /**
     * Prüft, ob die PHP-Installation die benötigten Erweiterungen und Funktionen für das Dashboard unterstützt.
     * @return array Eine Liste von Fehlermeldungen, falls Anforderungen nicht erfüllt sind. Leeres Array bedeutet "alles gut".
     */
    public function checkRequirements(): array
    {
        $errors = [];

        // Überprüfen der PHP-Version
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $errors[] = "PHP 8.0 oder höher wird benötigt. Aktuelle Version: " . PHP_VERSION;
        }

        // Benötigte Erweiterungen
        $requiredExtensions = ['pdo_mysql', 'mbstring', 'openssl'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $errors[] = "Die PHP-Erweiterung '$ext' ist nicht installiert oder aktiviert.";
            }
        }

        return $errors;
    }

    public function getPhpInfo(): string
    {
        ob_start();
        phpinfo();
        return ob_get_clean();
    }

    public function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'extensions' => get_loaded_extensions(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unbekannt',
            'os' => PHP_OS,
        ];
    }

    public function isSystemReady($maxLoad = 2.5): bool
    {
        // sys_getloadavg() ist unter Linux/Ubuntu standardmäßig verfügbar
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            
            // $load[0] ist der Schnitt der letzten 1 Minute
            if (isset($load[0]) && $load[0] > $maxLoad) {
                return false; // Server ist zu beschäftigt!
            }
        }
        return true; // Alles im grünen Bereich
    }

    /**
     * Repariert verwaiste E-Mail-Zuordnungen für den aktuellen Tenant.
     * Nutzt die universelle queryIt()-Methode der Elternklasse.
     */
    public function repairTenantContacts(): void 
    {
        // Header für JSON-Antwort setzen
        header('Content-Type: application/json');

        $tenantId = $_SESSION['tenant_id'] ?? null; 


        if (!$tenantId) {
            echo json_encode([
                'success' => false, 
                'error' => 'Nicht autorisiert oder keine valide Session.'
            ]);
            exit;
        }

        try {
            // 1. Verwaiste Mails des aktuellen Tenants holen
            $sqlGetUnlinked = "
                SELECT id, sender 
                FROM mails 
                WHERE contact_id IS NULL 
                  AND tenant_id = :tenant_id
            ";
            
            $unlinkedMails = $this->queryIt($sqlGetUnlinked, []);

            if (empty($unlinkedMails)) {
                echo json_encode([
                    'success' => true, 
                    'repaired_count' => 0,
                    'created_count' => 0,
                    'message' => 'Alles bereits sauber zugeordnet.'
                ]);
                exit;
            }

            // 2. Query für die Bestandssuche (Haupt- und Zusatz-E-Mails)
            $sqlFindContact = "
                SELECT ce.contact_id 
                FROM contact_emails ce
                INNER JOIN contacts c ON ce.contact_id = c.id
                WHERE ce.email = :email 
                AND c.tenant_id = :tenant_id
                LIMIT 1
            ";
            
            // 3. Update-Query für die mails-Tabelle
            $sqlUpdateMail = "
                UPDATE mails 
                SET contact_id = :contact_id 
                WHERE id = :mail_id 
                  AND tenant_id = :tenant_id
            ";

            $repairedCount = 0;
            $createdCount = 0;

            $contact = new ContactProcessor($this->dashboard);


            // 4. Verwaiste Einträge verarbeiten
            foreach ($unlinkedMails as $mail) {
                // Name und E-Mail sauber aus "Max Mustermann <max@firma.de>" extrahieren
                $senderRaw = $mail['sender'];
                $name = $senderRaw;
                $email = $senderRaw;

                if (preg_match('/^([^<]*)\s*<([^>]+)>/', $senderRaw, $matches)) {
                    $name = trim($matches[1]);
                    $email = trim($matches[2]);
                }

                // Falls vor dem `<...>` kein Name stand (z.B. nur `<info@firma.de>`), nutzen wir die Mail als Name
                if (empty($name)) {
                    $name = $email;
                }

                $email = strtolower(trim($email));

                // Validierung der E-Mail-Adresse
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue; 
                }

                // Prüfen, ob die E-Mail im CRM bekannt ist
                $contactResult = $this->queryIt($sqlFindContact, [
                    'email' => $email
                ]);

                $contactId = null;

                if (!empty($contactResult)) {
                    // Fall A: Kontakt existiert bereits im CRM
                    $contactId = $contactResult[0]['contact_id'];
                    $repairedCount++;
                } else {
                    // Fall B: Kontakt ist komplett neu -> Über vererbte insertContact() anlegen!
                    // Erzeugt den Eintrag in 'contacts' und 'contact_emails'
                    $newContactId = $contact->insertContact($name, $email, 'company');
                    
                    if ($newContactId) {
                        $contactId = $newContactId;
                        $createdCount++;
                    }
                }

                // Wenn wir eine gültige ID haben (egal ob alt oder neu generiert) -> Mail verknüpfen
                if ($contactId) {
                    $this->queryIt($sqlUpdateMail, [
                        'contact_id' => (int)$contactId,
                        'mail_id'    => (int)$mail['id']
                    ]);
                }
            }

            // Statistik ans Frontend zurückgeben
            echo json_encode([
                'success' => true, 
                'repaired_count' => $repairedCount,
                'created_count' => $createdCount
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false, 
                'error' => 'Fehler während der automatischen CRM-Verarbeitung: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}