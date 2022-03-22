<?php

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/settings.php';

use CrowdSecBouncer\StandaloneBounce;

$bounce = new StandaloneBounce();

// Retro compatibility with crowdsec php lib < 0.14.0
if(isset($crowdSecStandaloneBouncerConfig['bouncing_level']) && $crowdSecStandaloneBouncerConfig['bouncing_level'] === 'normal_boucing'){
    $crowdSecStandaloneBouncerConfig['bouncing_level'] = 'normal_bouncing';
}elseif($crowdSecStandaloneBouncerConfig['bouncing_level'] === 'flex_boucing'){
    $crowdSecStandaloneBouncerConfig['bouncing_level'] = 'flex_bouncing';
}
$bounce->setDebug($crowdSecStandaloneBouncerConfig['debug_mode']??false);
$bounce->setDisplayErrors($crowdSecStandaloneBouncerConfig['display_errors']??false);
$bounce->init($crowdSecStandaloneBouncerConfig);

/** @var $crowdSecStandaloneBouncerConfig */
$bounce->safelyBounce($crowdSecStandaloneBouncerConfig);

?>
