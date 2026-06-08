<?php
namespace App\Services;
use Exception; // Falls du Exceptions nutzt
use App\Services\PaperlessProcessor;
use App\Services\ContactProcessor;

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
        $sql = "SELECT * FROM notes WHERE tenant_id=:tenant_id ORDER BY updated_at DESC;";
        $dbResult = $this->queryIt(
            $sql
        );

        $fetched = $dbResult->data->fetched;

        return ['notes' => $fetched];
        
    }

    public function saveNote(array $memoData)
    {
        
        $sql = "INSERT INTO notes (tenant_id, mail_uid, mail_folder, category, content, status, lokal_id, email) 
                VALUES (:tenant_id, :mailUid, :mailFolder, :category, :content, :status, :lokal_id, :email)";

        $params = [
            'mailUid'     => !empty($memoData['uid']) ? $memoData['uid'] : null,
            'mailFolder'  => !empty($memoData['folder']) ? $memoData['folder'] : null,
            'category'    => $memoData['category'] ?? 'general',
            'content'     => $memoData['content'],
            'status'      => $memoData['status'] ?? 'active', // 💡 Mappt jetzt sauber auf :status
            'lokal_id'    => $memoData['id'],
            'email'       => !empty($memoData['mail']) ? $memoData['mail'] : null
        ];

        $dbResult = $this->queryIt($sql, $params, true, true, false);

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

};