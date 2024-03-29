<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use CrowdSecStandalone\Bouncer;

use CrowdSec\RemediationEngine\Geolocation;
/**
 * @var $crowdSecStandaloneBouncerConfig
 */
if (isset($_GET['ip'])) {
    $settings = include_once __DIR__ . '/../../../scripts/settings.php';
    $requestedIp = $_GET['ip'];
    $dbName = $_GET['db-name'] ?? 'GeoLite2-Country.mmdb';
    $dbType = $_GET['db-type'] ?? 'country';
    $cacheDuration = isset($_GET['cache-duration']) ? (int) $_GET['cache-duration'] : 0;
    $fakeBrokenDb = isset($_GET['broken-db']);

    $geolocConfig = [
        'enabled' => true,
        'cache_duration' => $cacheDuration,
        'type' => 'maxmind',
        'maxmind' => [
            'database_type' => $dbType,
            'database_path' => '/var/www/html/my-code/standalone-bouncer/tests/' . $dbName,
        ],
    ];

    if ($fakeBrokenDb) {
        $geolocConfig['maxmind']['database_path'] = '/var/www/html/my-code/standalone-bouncer/tests/broken.mmdb';
    }

    $finalConfig = array_merge($settings, ['geolocation' => $geolocConfig]);
    $bouncer = new Bouncer($finalConfig);

    $cache = $bouncer->getRemediationEngine()->getCacheStorage();

    $geolocation = new Geolocation($geolocConfig, $cache, $bouncer->getLogger());
    if ($cacheDuration <= 0) {
        $geolocation->clearGeolocationCache($requestedIp);
    }

    $countryResult = $geolocation->handleCountryResultForIp($requestedIp);
    $country = $countryResult['country'];
    $notFound = $countryResult['not_found'];
    $error = $countryResult['error'];

    echo "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'/>
    <title>Geolocation for IP: $requestedIp</title>
</head>

<body>
    <h1>For IP $requestedIp:</h1>
    <ul>
        <li>Country: $country</li>
        <li>Not Found message: $notFound</li>
        <li>Error message: $error</li>
        <li>Cache duration: $cacheDuration</li>
    </ul>
</body>
</html>
";
} else {
    exit('You must pass an "ip" param' . \PHP_EOL);
}
