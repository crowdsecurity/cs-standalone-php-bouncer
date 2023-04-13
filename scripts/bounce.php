<?php
/**
 * This script is aimed to be called by an auto_prepend_file directive.
 * It will apply CrowdSec remediation for the current IP.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecBouncer\BouncerException;
use CrowdSecBouncer\Constants;
use CrowdSecStandalone\Bouncer;

try {
    $settings = @include_once __DIR__ . '/settings.php';
    if (isset($settings['bouncing_level']) && Constants::BOUNCING_LEVEL_DISABLED !== $settings['bouncing_level']) {
        $bouncer = new Bouncer($settings);
        $bouncer->run();
    }
} catch (\Throwable $e) {
    $displayErrors = !empty($settings['display_errors']);
    if ($displayErrors) {
        throw new BouncerException($e->getMessage(), (int) $e->getCode(), $e);
    }
}
