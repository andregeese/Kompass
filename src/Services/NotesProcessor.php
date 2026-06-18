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
use Exception; // Falls du Exceptions nutzt
use App\Services\PaperlessProcessor;
use App\Services\ContactProcessor;
use SebastianBergmann\Type\FalseType;

/**
 * @property \App\Services\NotesProcessor $notesProcess
 */
class NotesProcessor extends \App\Core\Database
{
    use \App\Core\ServiceTrait;
    protected $dashboard;
    
    //private $imapStream = null;
    public function __construct($dashboard) {
        parent::__construct($dashboard);
        $this->dashboard = $dashboard;
    }
    public function getNotes()
    {
        $sqlNew = "SELECT 
                n.id as id,
                n.content as content,
                n.mail_uid as uid,
                n.mail_folder as folder,
                n.created_at as created_at,
                n.category as category,
                n.status as status,
                ce.email as email,
                c.display_name as display_name
                FROM notes as n 
                LEFT JOIN contact_emails as ce ON ce.id = n.ceid
                LEFT JOIN contacts as c on c.id = ce.contact_id
                WHERE n.tenant_id=:tenant_id ORDER BY n.updated_at DESC";
        
        $dbResult = $this->queryIt(
            $sqlNew,
            [],
            false,
            false,
            false
        );
        $fetched = $dbResult->data->fetched;
        return ['notes' => $fetched];
        
    }

    /** alle Notizen zu einer Mail (TIMELINE) */
    public function getNotesByMailId($id)
    {
        // alle Notizen zu einer Mail
        $sql = "SELECT DISTINCT n.*
                FROM `notes` n
                -- 1. Finde alle E-Mail-Adressen/IDs, die zum selben Kontakt gehören
                JOIN `contact_emails` ce_all 
                ON (n.ceid = ce_all.id) -- OR LOWER(TRIM(n.email)) = LOWER(TRIM(ce_all.email)))
                -- 2. Schlage die Brücke zum gemeinsamen Hauptkontakt (contacts)
                JOIN `contact_emails` ce_current_mail 
                ON ce_all.contact_id = ce_current_mail.contact_id
                -- 3. Knüpfe an die aktuell in der Sidebar geöffnete Mail an (über sender oder recipient)
                JOIN `mails` m 
                ON (m.sender_ceid = ce_current_mail.id OR m.recipient_ceid = ce_current_mail.id)
                -- 4. Filter für die Sidebar
                WHERE m.id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED)
                AND m.tenant_id = :tenant_id
                AND n.tenant_id = :tenant_id
                -- Neueste Notizen immer zuerst anzeigen
                ORDER BY n.created_at DESC;
        );";

        $params = ['id' => $id];
        $dbResult = $this->queryIt($sql, $params, false, false, false);


        if (!$dbResult->success) {
            throw new \Exception("Fehler bei Notiz!");
        }
        return $dbResult->data->fetched;
    }


    public function saveNote(array $memoData)
    {

        $sql = "INSERT INTO notes (tenant_id, mail_uid, mail_folder, category, content, status, lokal_id, ceid, due_date) 
                VALUES (:tenant_id, :mailUid, :mailFolder, :category, :content, :status, :lokal_id, CAST(AES_DECRYPT(UNHEX(:ceid), :crypto_key) AS UNSIGNED), :duedate)";
        $params = [
            'mailUid'     => !empty($memoData['uid']) ? $memoData['uid'] : null,
            'mailFolder'  => !empty($memoData['folder']) ? $memoData['folder'] : null,
            'category'    => $memoData['category'] ?? 'general',
            'content'     => $memoData['content'],
            'status'      => $memoData['status'] ?? 'active', // 💡 Mappt jetzt sauber auf :status
            'lokal_id'    => $memoData['id'],
            'ceid'        => !empty($memoData['ceid']) ? $memoData['ceid'] : null,
            'duedate'     => !empty($memoData['due_date']) ? date('Y-m-d H:i:s', strtotime($memoData['due_date'])) : null
        ];

        return $this->queryIt($sql, $params, false, false, false);
       
        if (!$dbResult->success){
            throw new \Exception("Notiz nicht gespeichert");
        }

        return $dbResult->data;
    }
    /**
     * Setze Status auf completed oder active
     */
    public function toogleNote()
    {
        $id = $this->request->id;
        $status = $this->request->status;
        $sql = "UPDATE notes 
                   SET status = :status  
                WHERE tenant_id = :tenant_id AND id = :id;";
        $params = [
            "id"       => $id,
            "status"   => $status
        ];
        $dbResult = $this->queryIt($sql,$params, true, false, true);
        if (!$dbResult->success){
            throw new \Exception ("Notiz nicht geändert");
        }
        return $dbResult->data;
    }

    /**
     * Erstellt eine Liste aller offenen kritischen Memos / Aufgaben
     */
    public function getCriticalMemos()
    {
        $sql = "SELECT ceid, mail_uid, mail_folder, category, content, status, due_date 
                  FROM notes 
                 WHERE tenant_id=:tenant_id 
                   AND due_date<=now()
                   AND status ='active';";

        return $this->queryIt($sql, [], false, false, false);
        

    }
};