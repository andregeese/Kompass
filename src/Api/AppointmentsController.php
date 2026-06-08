<?php
namespace App\Api;
use App\AppointmentsProcessor; // Importiere den Prozessor

use Exception;

class AppointmentsController extends \App\Core\BaseController 
{
    public function __construct($dashboard) {
        parent::__construct($dashboard); // Das ist entscheidend!
    }

    public function getAppointment()
    {
        $id = $this->request->get('id');
        $appointment = $this->appointProcess->getAppointment($id);
        
        return ['appointment' => $appointment];
    }
    public function getAppointments()
    {
        $appointments = $this->appointProcess->getAppointmentsFromDB();
        error_log(print_r($appointments, true));
        
        return ['appointments' => $appointments];
        
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

    public function saveAppointment()
    {
        $data = [
            'id'        => $this->request->get('id'),
            'title'   => $this->request->get('summary'),
            'ceid'      => $this->request->get('ceid'),
        ];
        
        if (empty($data['id'])) {
            return ['success' => false, 'error' => 'Keine ID angegeben'];
        }

        return $this->appointProcess->saveAppointment($data);
    }

}