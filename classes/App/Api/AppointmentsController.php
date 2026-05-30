<?php
namespace App\Api;
use App\AppointmentsProcessor; // Importiere den Prozessor

use Exception;

class AppointmentsController extends BaseController 
{
    public function __construct($dashboard) {
        parent::__construct($dashboard); // Das ist entscheidend!
    }

    public function getAppointments()
    {
        try{

            $appointments = $this->appointProcess->getAppointmentsFromDB();
            return [
                'success' => true,
                'details' => [
                    'appointment' => $appointments
                ]
            ];
        } catch (\Throwable $e) {

            $this->dashboard->sendResponse(false, "", $e->getMessage());
            return [
                'success' => false,
                'error'   => 'Fehler beim Laden der E-Mails: ' . $e->getMessage()
            ];
            throw new \Exception($e->getMessage());

        }
        
    }

    public function getEventsParsed() 
    {
        $url = '/228168594/calendars/80E56597-9429-4383-A5F4-5180DC81144B/';
        return $this->appointProcess->getEventsParsed($url);
    }

    public function fullSync()
    {
        return $this->appointProcess->runFullSync();
    }

    public function getPartition()  
    {
        return $this->appointProcess->getPartition();
    }

}