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
        
        return $this->process($this->appointProcess, 'getAppointment','appointment','');
        return ['appointment' => $appointment];
    }

    public function getAppointments()
    {
        return $this->process($this->appointProcess, 'getAppointments', 'appointments','');
        
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
    
    public function updateAppointment()
    {
        return $this->process($this->appointProcess, 'updateAppointment', 'updateAppoint','');
    }
    public function saveAppointment()
    {
        return $this->appointProcess->saveAppointment($data);
    }

    public function getUpcomingEvents()
    {
        return $this->process($this->appointProcess, 'getUpcomingEvents', 'upcoming_events', '');
    }
}