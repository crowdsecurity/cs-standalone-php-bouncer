<?php
/**
 * This script is aimed to be called directly in a browser
 * It will act on the LAPI cache depending on the auto-prepend settings file and on the passed parameter.
 */
require_once __DIR__ . '/../../../vendor/autoload.php';

use CrowdSecStandalone\Bouncer;
use CrowdSecStandalone\Constants;
/**
 * @var $crowdSecStandaloneBouncerConfig
 */
if (isset($_GET['action']) && in_array($_GET['action'],
        [
            'refresh',
            'clear',
            'prune',
            'captcha-phrase',
            'push-usage-metrics',
            'show-origins-count'
        ])) {
    $action = $_GET['action'];
    $settings = include_once __DIR__ . '/../../../scripts/settings.php';
    $bouncer = new Bouncer($settings);
    $result = "<h1>Cache action has been done: $action</h1>";

    switch ($action) {
        case 'refresh':
            $bouncer->refreshBlocklistCache();
            break;
        case 'clear':
            $bouncer->clearCache();
            break;
        case 'prune':
            $bouncer->pruneCache();
            break;
        case 'push-usage-metrics':
            $bouncer->pushUsageMetrics('test-'. Constants::BOUNCER_NAME. '-e2e', Constants::VERSION, 'test-crowdsec-php-bouncer');
            break;
        case 'captcha-phrase':
            if(!isset($_GET['ip'])){
                exit('You must pass an "ip" param to get the associated captcha phrase' . \PHP_EOL);
            }
            $ip = $_GET['ip'];
            $cache = $bouncer->getRemediationEngine()->getCacheStorage();
            $cacheKey = $cache->getCacheKey(Constants::CACHE_TAG_CAPTCHA, $ip);
            $item = $cache->getItem($cacheKey);
            $result = "<h1>No captcha for this IP: $ip</h1>";
            if($item->isHit()){
                $cached = $item->get();
                $phrase = $cached['phrase_to_guess']??"No phrase to guess for this captcha (already resolved ?)";
                $result = "<h1>$phrase</h1>";
            }
            break;
        case 'show-origins-count':
            $result = "<h1>Origins count</h1>";
            $origins = $bouncer->getRemediationEngine()->getOriginsCount();
            $result .= "<div id='origins-count'>".json_encode($origins)."</div>";
            break;
        default:
            throw new Exception("Unknown cache action type:$action");
    }

    echo "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'/>
    <title>Cache action: $action</title>
</head>

<body>
    $result
</body>
</html>
";
} else {
    exit('You must pass an "action" param (refresh, clear or prune)' . \PHP_EOL);
}
