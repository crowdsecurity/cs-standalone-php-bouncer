<?php
/**
 * This script is aimed to be called by a cron task.
 * It will refresh the bouncer cache (if stream mode is enabled).
 */
require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecStandalone\Bouncer;

$settings = @include_once __DIR__ . '/settings.php';
if (isset($settings['cache_system'])) {
    $bouncer = new Bouncer($settings);
    $bouncer->refreshBlocklistCache();
}
