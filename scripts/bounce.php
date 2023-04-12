<?php
/**
 * This script is aimed to be called by an auto_prepend_file directive.
 * It will apply CrowdSec remediation for the current IP.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecStandalone\Bouncer;
use CrowdSecBouncer\BouncerException;

try {
    $settings = include_once __DIR__ . '/settings.php';
    $bouncer = new Bouncer($settings);
    $bouncer->run();
} catch (\Throwable $e) {
    $displayErrors = $settings['display_errors'] ?? false;
    if (true === $displayErrors) {
        throw new BouncerException($e->getMessage(), (int) $e->getCode(), $e);
    }
}
