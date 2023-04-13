<?php
/**
 * This script is aimed to be called by a cron task.
 * It will prune the bouncer cache (if applicable).
 */
require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecStandalone\Bouncer;

$settings = @include_once __DIR__ . '/settings.php';
if (isset($settings['cache_system'])) {
    $bouncer = new Bouncer($settings);
    $bouncer->pruneCache();
}
