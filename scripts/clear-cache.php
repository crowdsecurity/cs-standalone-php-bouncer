<?php
/**
 * This script is aimed to be called by a cron task.
 * It will clear the bouncer cache.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecStandalone\Bouncer;

try {
    $settings = include_once __DIR__ . '/settings.php';
    $bouncer = new Bouncer($settings);
    $bouncer->clearCache();
} catch (\Throwable $e) {
    trigger_error('CrowdSec standalone bouncer error while clearing cache: ' . $e->getMessage(), \E_USER_WARNING);
}
