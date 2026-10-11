<?php
/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */


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