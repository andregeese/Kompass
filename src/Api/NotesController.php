<?php
namespace App\Api;
use App\Services\NotesProcessor; // Importiere den Prozessor

use Exception;

class NotesController extends \App\Core\BaseController 
{
    public function __construct($dashboard) {
        parent::__construct($dashboard); // Das ist entscheidend!
    }

    public function saveNote()
    {
        // 1. Prüfung direkt im Controller (Sicherstellen, dass das Request-Objekt da ist)
        if (!isset($this->request)) {
            throw new \Exception("Request-Objekt im Controller nicht initialisiert.");
        }
        
        // 2. Der Controller holt sich gezielt den 'memo'-Block aus dem Request
        $memoData = $this->request->get('memo');

        if (empty($memoData)) {
            // Falls dein Request-Objekt eine Methode wie all() oder getParams() hat, nimm diese.
            // Alternativ bauen wir das Array manuell aus den Root-Werten auf:
            $memoData = [
                'id'       => $this->request->get('id'),
                'content'  => $this->request->get('content'),
                'uid'      => $this->request->get('uid'),
                'folder'   => $this->request->get('folder'),
                'category' => $this->request->get('category'),
                'status'   => $this->request->get('status'),
                'ceid'     => $this->request->get('ceid')
            ];
        }

        error_log("MEMODATA:" . print_r($memoData, true));

        // Validierung: Haben wir jetzt (egal auf welchem Weg) gültige Daten?
        // Wir prüfen explizit auf 'content', da das die Mindestanforderung für eine Notiz ist.
        if (empty($memoData) || empty($memoData['content'])) {
            throw new \Exception("Keine gültigen Notiz-Daten im Request gefunden.");
        }

        // 3. Verarbeitung im Service
        $pResult = $this->notesProcess->saveNote($memoData);
        
        // 4. JSON-Response zurückgeben (Der tote Code am Ende ist entfernt)
        return [
            "note" => $pResult,
            'ui' => [
                'notify'  => true,
                'title'   => 'Notiz',
                'message' => 'Notiz erfolgreich erstellt.',
                'type'    => 'success'
            ]
        ];
        
    }

    public function getNotes() {
        $pResult = $this->notesProcess->getNotes();
        error_log(print_r($pResult, true ));
        return $pResult;
    }

    public function toogleNote()
    {
        return $this->notesProcess->toogleNote();
    }

};