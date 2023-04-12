<?php
/**
 * This script is aimed to be called by a cron task.
 * It will refresh the bouncer cache (if stream mode is enabled).
 */
require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecStandalone\Bouncer;

try {
    $settings = include_once __DIR__ . '/settings.php';
    $bouncer = new Bouncer($settings);
    $bouncer->refreshBlocklistCache();
} catch (\Throwable $e) {
    trigger_error('CrowdSec standalone bouncer error while refreshing cache: ' . $e->getMessage(), \E_USER_WARNING);
}
