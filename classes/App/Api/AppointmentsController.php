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
        $this->appointProcess->getAppointments();
    }

}