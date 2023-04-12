<?php
/**
 * This script is aimed to be called by a cron task.
 * It will prune the bouncer cache (if applicable).
 */
require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecStandalone\Bouncer;

try {
    $settings = include_once __DIR__ . '/settings.php';
    $bouncer = new Bouncer($settings);
    $bouncer->pruneCache();
} catch (\Throwable $e) {
    trigger_error('CrowdSec standalone bouncer error while pruning cache: ' . $e->getMessage(), \E_USER_WARNING);
}
