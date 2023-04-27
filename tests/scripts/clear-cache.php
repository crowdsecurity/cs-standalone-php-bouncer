<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use CrowdSecStandalone\Bouncer;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Parse arguments
$bouncerApiKey = $argv[1]??null; // required
$apiUrl = $argv[2] ?? 'https://crowdsec:8080';

if (!$bouncerApiKey) {
    exit('Usage: php clear-cache.php <api_key>');
}
echo "\nClear the cache...\n";

// Instantiate the Stream logger
$logger = new Logger('example');

// Display logs with DEBUG verbosity
$streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
$streamHandler->setFormatter(new LineFormatter("[%datetime%] %message% %context%\n"));
$logger->pushHandler($streamHandler);


// Instantiate the bouncer
$configs = [
    'api_key' => $bouncerApiKey,
    'api_url' => 'https://crowdsec:8080',
    'fs_cache_path' => __DIR__ . '/.cache',
];
$bouncer = new Bouncer($configs, $logger);

// Clear the cache.
$bouncer->clearCache();
echo "Cache successfully cleared.\n";
