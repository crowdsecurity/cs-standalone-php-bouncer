<?php

declare(strict_types=1);

namespace CrowdSecStandalone\Tests\Integration;

use CrowdSecBouncer\Constants;
use CrowdSecStandalone\Bouncer;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \CrowdSecStandalone\Bouncer::__construct
 *
 * @uses \CrowdSecStandalone\Bouncer::handleTrustedIpsConfig
 */
final class GeolocationTest extends TestCase
{
    /** @var WatcherClient */
    private $watcherClient;

    /** @var LoggerInterface */
    private $logger;
    /** @var bool */
    private $useCurl;
    /** @var bool */
    private $useTls;
    /**
     * @var array
     */
    private $configs;

    /**
     * @var vfsStreamDirectory
     */
    private $root;

    private function addTlsConfig(&$bouncerConfigs, $tlsPath)
    {
        $bouncerConfigs['tls_cert_path'] = $tlsPath . '/bouncer.pem';
        $bouncerConfigs['tls_key_path'] = $tlsPath . '/bouncer-key.pem';
        $bouncerConfigs['tls_ca_cert_path'] = $tlsPath . '/ca-chain.pem';
        $bouncerConfigs['tls_verify_peer'] = true;
    }

    protected function setUp(): void
    {
        $this->useTls = (string) getenv('BOUNCER_TLS_PATH');
        $this->useCurl = (bool) getenv('USE_CURL');
        $this->logger = TestHelpers::createLogger();
        $this->root = vfsStream::setup('/tmp');

        $bouncerConfigs = [
            'auth_type' => $this->useTls ? \CrowdSec\LapiClient\Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => getenv('BOUNCER_KEY'),
            'api_url' => getenv('LAPI_URL'),
            'use_curl' => $this->useCurl,
            'user_agent_suffix' => 'testphpbouncer',
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $this->configs = $bouncerConfigs;
        $this->watcherClient = new WatcherClient($this->configs);
        // Delete all decisions
        $this->watcherClient->deleteAllDecisions();
    }

    public function maxmindConfigProvider(): array
    {
        return TestHelpers::maxmindConfigProvider();
    }

    private function handleMaxMindConfig(array $maxmindConfig): array
    {
        // Check if MaxMind database exist
        if (!file_exists($maxmindConfig['database_path'])) {
            $this->fail('There must be a MaxMind Database here: ' . $maxmindConfig['database_path']);
        }

        return [
            'cache_duration' => 0,
            'enabled' => true,
            'type' => 'maxmind',
            'maxmind' => [
                'database_type' => $maxmindConfig['database_type'],
                'database_path' => $maxmindConfig['database_path'],
            ],
        ];
    }

    /**
     * @dataProvider maxmindConfigProvider
     *
     * @throws \Symfony\Component\Cache\Exception\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testCanVerifyIpAndCountryWithMaxmindInLiveMode(array $maxmindConfig): void
    {
        // Init context
        $this->watcherClient->setInitialState();

        // Init bouncer
        $geolocationConfig = $this->handleMaxMindConfig($maxmindConfig);
        $bouncerConfigs = [
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'geolocation' => $geolocationConfig,
            'use_curl' => $this->useCurl,
            'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
            'fs_cache_path' => $this->root->url(),
            'stream_mode' => false,
        ];

        $bouncer = new Bouncer($bouncerConfigs, $this->logger);

        $bouncer->clearCache();

        $this->assertEquals(
            'captcha',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN)['remediation'],
            'Get decisions for a clean IP but bad country (captcha)'
        );

        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::IP_FRANCE)['remediation'],
            'Get decisions for a clean IP and clean country'
        );

        // Disable Geolocation feature
        $geolocationConfig['enabled'] = false;
        $bouncerConfigs['geolocation'] = $geolocationConfig;
        $bouncer = new Bouncer($bouncerConfigs, $this->logger);
        $bouncer->clearCache();

        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN)['remediation'],
            'Get decisions for a clean IP and bad country but with geolocation disabled'
        );

        // Enable again geolocation and change testing conditions
        $this->watcherClient->setSecondState();
        $geolocationConfig['enabled'] = true;
        $bouncerConfigs['geolocation'] = $geolocationConfig;
        $bouncer = new Bouncer($bouncerConfigs, $this->logger);
        $bouncer->clearCache();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN)['remediation'],
            'Get decisions for a bad IP (ban) and bad country (captcha)'
        );

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::IP_FRANCE)['remediation'],
            'Get decisions for a bad IP (ban) and clean country'
        );
    }

    /**
     * @group integration
     *
     * @dataProvider maxmindConfigProvider
     *
     * @throws \Symfony\Component\Cache\Exception\CacheException|\Psr\Cache\InvalidArgumentException
     */
    public function testCanVerifyIpAndCountryWithMaxmindInStreamMode(array $maxmindConfig): void
    {
        // Init context
        $this->watcherClient->setInitialState();
        // Init bouncer
        $geolocationConfig = $this->handleMaxMindConfig($maxmindConfig);
        $bouncerConfigs = [
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => true,
            'geolocation' => $geolocationConfig,
            'use_curl' => $this->useCurl,
            'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
            'fs_cache_path' => $this->root->url(),
        ];

        $bouncer = new Bouncer($bouncerConfigs, $this->logger);
        $cacheAdapter = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheAdapter->clear();

        // Warm BlockList cache up
        $bouncer->refreshBlocklistCache();

        $this->logger->debug('', ['message' => 'Refresh the cache just after the warm up. Nothing should append.']);
        $bouncer->refreshBlocklistCache();

        $this->assertEquals(
            'captcha',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN)['remediation'],
            'Should captcha a clean IP coming from a bad country (captcha)'
        );

        // Add and remove decision
        $this->watcherClient->setSecondState();

        $this->assertEquals(
            'captcha',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN)['remediation'],
            'Should still captcha a bad IP (ban) coming from a bad country (captcha) as cache has not been refreshed'
        );

        // Pull updates
        $bouncer->refreshBlocklistCache();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN)['remediation'],
            'The new decision should now be added, so the previously captcha IP should now be ban'
        );
    }
}
