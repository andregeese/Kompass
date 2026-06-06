<?php
namespace App\Api;
use App\Services\NotesProcessor; // Importiere den Prozessor

use Exception;

class NotesController extends \App\Core\BaseController 
{
    public function __construct($dashboard) {
        parent::__construct($dashboard); // Das ist entscheidend!
    }

    public function saveNote(){
        
        $ret = $this->notesProcess->saveNote();
    }

    public function getNotes() {
        $ret = $this->notesProcess->getNotes();
    }
};