<?php
/**
 * This script is aimed to be called by an auto_prepend_file directive.
 * It will apply CrowdSec remediation for the current IP.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecStandalone\Bouncer;

try {
    $settings = include_once __DIR__ . '/settings.php';
    $bouncer = new Bouncer($settings);
    $bouncer->run();
} catch (\Throwable $e) {
    trigger_error('CrowdSec standalone bouncer error while bouncing: ' . $e->getMessage(), \E_USER_WARNING);
}
