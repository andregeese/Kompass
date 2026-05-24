<?php
namespace App;

trait ServiceTrait {
    // Greift auf die Eigenschaft $this->dashboard zu
    public function getService($name, $className) {
        return $this->dashboard->getService($name, $className);
    }
}