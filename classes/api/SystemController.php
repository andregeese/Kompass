<?php
namespace App\Api;

class SystemController extends \App\Database
{
    // Wir speichern die zusätzlichen Abhängigkeiten als Eigenschaften
    protected $mailProc;
    protected $tenantId;
    protected $tenantConfig;

    public function __construct($dashboard, $mailProc, $tenantId, $tenantConfig) 
    {
        // Übergabe des Dashboards an die Database-Klasse
        parent::__construct($dashboard);
        
        // Eigene Eigenschaften setzen
        $this->mailProc = $mailProc;
        $this->tenantId = $tenantId;
        $this->tenantConfig = $tenantConfig;
    }

    public function status() {
        $system = new \App\System();
        $status = $system->isSystemReady();

        return [
            'success' => true,
            'details' => ['status' => $status]
        ];
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
                    $newContactId = $this->insertContact($name, $email, 'company');
                    
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


    /**
     * Synchronisiert im Hintergrund Mails für einen spezifischen Ordner.
     * Aufrufbar via: api.php?action=sync_folder&folder=dms
     */
    public function syncFolder(): void 
    {
        header('Content-Type: application/json');
        
        // Folder aus dem Request holen, Standard ist INBOX
        $folder = $_GET['folder'] ?? 'INBOX';
        
        // Sicherheit: Folder bereinigen (nur erlaubte Zeichen)
        $folder = preg_replace('/[^a-zA-Z0-9]/', '', $folder);
        if (empty($folder)) $folder = 'INBOX';

        try {
            // Aufruf der Sync-Methode
            $result = $this->mailProc->syncWithICloud(true, 1, $folder);
            echo json_encode($result);

        } catch (\Throwable $e) {
            error_log(__FUNCTION__.": Sync Fehler: " . $e->getMessage()); // WICHTIG!
            echo json_encode([
                'success' => false, 
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }


}