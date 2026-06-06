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
        $sql = "SELECT * FROM notes WHERE tenant_id=:tenant_id";
        $dbResult = $this->queryIt(
            $sql
        );

        $fetched = $dbResult->data->fetched;

        return ['notes' => $fetched];
        
    }

    public function saveNote() 
    {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        $memo = $data['memo'] ?? $data;

        error_log("NOTE: " . print_r($memo, true));
        
        $mailUid    = (empty($memo['uid']) || $memo['uid'] == '') ? null : $memo['uid'] ;
        $mailFolder = $memo['folder'] ??  null;
        $category   = $memo['category'] ??  'general';
        $content   = $memo['content'] ??  null;

        $sql = "INSERT INTO notes (tenant_id, mail_uid, mail_folder, category, content, status) 
                VALUES (:tenant_id, :mailUid, :mailFolder, :category, :content, 'active')";
        $params = [
            "mailUid"       => $mailUid,
            "mailFolder"    => $mailFolder,
            "category"      => $category,
            "content"       => $content
        ];
        $dbResult = $this->queryIt($sql,$params,true, true, true);

        if (!$dbResult->success){
            throw new \Exception ("Notiz nicht gespeichert");
        }

        return $dbResult->data->lastId;


    }
};