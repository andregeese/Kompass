<?php
// src/Services/BaseProcessor.php
namespace App\Services;

abstract class BaseProcessor extends \App\Core\Database {
    use \App\Core\ServiceTrait; // Hier ist der Trait!

    protected $dashboard;

    public function __construct($dashboard) {
        parent::__construct();
        $this->dashboard = $dashboard;
    }
}