<?php
/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

namespace App\Api;
use App\Services\ContactProcessor;
use App\Services\SystemProcessor;
class SystemController extends \App\Core\BaseController
{
    public function __construct($dashboard) {
        parent::__construct($dashboard); // Das ist entscheidend!
    }
    public function status() {
        $system = new \App\Services\SystemProcessor();
        $status = $system->isSystemReady();
        return [
            'success' => true,
            'details' => ['status' => $status]
        ];
    }
    /**
     * Verbiindungsprüfung und FOLDER im IMAP
     */
    public function testImap()
    {
       try {
            $host = $_POST['imap_host'] ?? '';
            $port = $_POST['imap_port'] ?? '993';
            $user = $_POST['imap_user'] ?? '';
            $pass = $_POST['imap_password'] ?? '';
            if (empty($host) || empty($user) || empty($pass)) {
                throw new \Exception("Bitte alle Felder ausfüllen.");
            }
            // Aufruf des Prozessors
            $folders = $this->systemProcess->testImap($host, $port, $user, $pass);
            // Erfolgreiche Antwort
            echo json_encode(['success' => true, 'folders' => $folders]);
        } catch (\Exception $e) {
            // Fehlerhafte Antwort
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
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
        $this->systemProcess->repairTenantContacts();
        if (!$tenantId) {
            echo json_encode([
                'success' => false, 
                'error' => 'Nicht autorisiert oder keine valide Session.'
            ]);
            exit;
        }
        try {
            $this->systemProc->repairTenantContacts();
        } catch (\Exception $e) {
            $this->dashboard->http_response_code();
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
            $result = $this->mailProcessProc->syncWithICloud(1, $folder);
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