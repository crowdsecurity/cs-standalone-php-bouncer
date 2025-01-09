<?php
/**
 * This script is aimed to be called by a cron task.
 * It will push usage metrics to LAPI.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecStandalone\Bouncer;
use CrowdSecStandalone\Constants;

$settings = @include_once __DIR__ . '/settings.php';
if (isset($settings['cache_system'])) {
    $bouncer = new Bouncer($settings);
    $bouncer->pushUsageMetrics(Constants::BOUNCER_NAME, Constants::VERSION);
}
