<?php
/**
 * This script is aimed to be called by a cron task.
 * It will clear the bouncer cache.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecStandalone\Bouncer;

$settings = include_once __DIR__ . '/settings.php';
$bouncer = new Bouncer($settings);
$bouncer->clearCache();
