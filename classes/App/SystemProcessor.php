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
        header('Content-Type: application/json');
        $tenantId = $_SESSION['tenant_id'] ?? null; 

        if (!$tenantId) {
            echo json_encode(['success' => false, 'error' => 'Keine Session.']);
            exit;
        }

        try {
            // 1. Alle verwaisten Mails holen
            $sqlGetUnlinked = "SELECT id, sender FROM mails WHERE contact_id IS NULL AND tenant_id = :tenant_id";
            $unlinkedMails = $this->queryIt($sqlGetUnlinked, ['tenant_id' => $tenantId]);

            if (empty($unlinkedMails)) {
                echo json_encode(['success' => true, 'message' => 'Alles bereits zugeordnet.']);
                exit;
            }

            // 2. Suche exakt die ID aus contact_emails (nicht contacts!)
            $sqlFindEmailEntry = "
                SELECT ce.id 
                FROM contact_emails ce
                INNER JOIN contacts c ON ce.contact_id = c.id
                WHERE ce.email = :email 
                AND c.tenant_id = :tenant_id
                LIMIT 1
            ";
            
            // 3. Update auf contact_emails.id (FOREIGN KEY Ziel)
            $sqlUpdateMail = "UPDATE mails SET contact_id = :ce_id WHERE id = :mail_id";

            $repairedCount = 0;
            $createdCount = 0;
            $contact = new ContactProcessor($this->dashboard);

            foreach ($unlinkedMails as $mail) {
                // Regex: Extrahiere E-Mail aus "Name <mail@test.de>" oder nur "mail@test.de"
                $senderRaw = $mail['sender'];
                if (preg_match('/<([^>]+)>/', $senderRaw, $matches)) {
                    $email = trim($matches[1]);
                } else {
                    $email = trim($senderRaw);
                }
                $email = strtolower($email);

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

                // Suche nach der ID in contact_emails
                $result = $this->queryIt($sqlFindEmailEntry, [
                    'email' => $email,
                    'tenant_id' => $tenantId
                ]);

                $ceId = !empty($result) ? $result[0]['id'] : null;

                // Fall: E-Mail noch nicht im System -> Neu anlegen
                if (!$ceId) {
                    $name = preg_replace('/<.*?>/', '', $senderRaw);
                    $newContactId = $contact->insertContact(trim($name), $email, 'company');
                    
                    // Wir müssen die neue ID aus contact_emails holen
                    $newResult = $this->queryIt("SELECT id FROM contact_emails WHERE email = :email LIMIT 1", ['email' => $email]);
                    $ceId = !empty($newResult) ? $newResult[0]['id'] : null;
                    $createdCount++;
                } else {
                    $repairedCount++;
                }

                // Update mit der ID aus contact_emails
                if ($ceId) {
                    $this->queryIt($sqlUpdateMail, [
                        'ce_id' => (int)$ceId,
                        'mail_id' => (int)$mail['id']
                    ]);
                }
            }

            echo json_encode(['success' => true, 'repaired' => $repairedCount, 'created' => $createdCount]);

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}