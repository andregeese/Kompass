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
            true
        );

        $fetched = $dbResult->data->fetched;

        return ['notes' => $fetched];
        
    }

    public function saveNote(array $memoData)
    {
        error_log(print_r($memoData, true));

        $sql = "INSERT INTO notes (tenant_id, mail_uid, mail_folder, category, content, status, lokal_id, ceid) 
                VALUES (:tenant_id, :mailUid, :mailFolder, :category, :content, :status, :lokal_id, :ceid)";

        $params = [
            'mailUid'     => !empty($memoData['uid']) ? $memoData['uid'] : null,
            'mailFolder'  => !empty($memoData['folder']) ? $memoData['folder'] : null,
            'category'    => $memoData['category'] ?? 'general',
            'content'     => $memoData['content'],
            'status'      => $memoData['status'] ?? 'active', // 💡 Mappt jetzt sauber auf :status
            'lokal_id'    => $memoData['id'],
            'ceid'       => !empty($memoData['ceid']) ? $memoData['ceid'] : null
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