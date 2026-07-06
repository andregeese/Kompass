<?php
/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
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
        
        $status = $this->systemProcess->isSystemReady();
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
            $host = $this->request['imap_host'] ?? '';
            $port = $this->request['imap_port'] ?? '993';
            $user = $this->request['imap_user'] ?? '';
            $pass = $this->request['imap_password'] ?? '';

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

    public function globalSearch()
    {
        return $this->process(
            $this->systemProcess,
            'globalSearch',
            'gSearch',
            ''
        );
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

    public function getIMAPFolder()
    {
        return $this->process(
            $this->systemProcess, 'getIMAPFolder','imaps', ''
        );
    }
    /**
     * Synchronisiert im Hintergrund Mails für einen spezifischen Ordner.
     * Aufrufbar via: api.php?action=sync_folder&folder=dms
     */
    public function syncFolder(): void 
    {
        header('Content-Type: application/json');
        try {
            // Folder aus dem Request holen
            $folder = $this->request['folder'];
            
            // Sicherheit: Folder bereinigen (nur erlaubte Zeichen)
            $folder = preg_replace('/[^a-zA-Z0-9]/', '', $folder);

            if (!is_string($folder) || trim($folder) === '') {
                throw new \Exception( "no folder found");
            }
        
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