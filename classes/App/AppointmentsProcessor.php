<?php
namespace App;
use Exception; // Falls du Exceptions nutzt
use Throwable;


/**
 * @property \App\AppointmentsProcessor $appointProcess
 */
class AppointmentsProcessor extends Database
{

    use ServiceTrait;
    protected $dashboard;
    private $imapStream = null;

    public function __construct($dashboard) {
        parent::__construct($dashboard);
        $this->dashboard = $dashboard;
    }
}