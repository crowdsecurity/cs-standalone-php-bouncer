<?php

declare(strict_types=1);

namespace CrowdSecStandalone\Tests\Integration;

use CrowdSec\Common\Logger\FileLog;
use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;
use CrowdSec\RemediationEngine\CacheStorage\CacheStorageException;
use CrowdSecBouncer\BouncerException;
use CrowdSecBouncer\Constants;
use CrowdSecStandalone\Bouncer;
use CrowdSecStandalone\Tests\PHPUnitUtil;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @uses   \CrowdSecStandalone\Bouncer::__construct
 * @uses   \CrowdSecStandalone\Bouncer::handleTrustedIpsConfig
 *
 * @covers \CrowdSecStandalone\Bouncer::getHttpRequestHeader
 * @covers \CrowdSecStandalone\Bouncer::getRemoteIp
 * @covers \CrowdSecStandalone\Bouncer::run
 * @covers \CrowdSecStandalone\Bouncer::shouldBounceCurrentIp
 * @covers \CrowdSecStandalone\Bouncer::getHttpMethod
 * @covers \CrowdSecStandalone\Bouncer::getPostedVariable
 * @covers \CrowdSecStandalone\Bouncer::getRequestUri
 * @covers \CrowdSecStandalone\Bouncer::getRequestHeaders
 * @covers \CrowdSecStandalone\Bouncer::getRequestHost
 * @covers \CrowdSecStandalone\Bouncer::getRequestRawBody
 * @covers \CrowdSecStandalone\Bouncer::getRequestUserAgent
 *
 */
final class IpVerificationTest extends TestCase
{
    private const EXCLUDED_URI = '/favicon.ico';
    /** @var WatcherClient */
    private $watcherClient;

    /** @var bool */
    private $useCurl;

    /** @var bool */
    private $useTls;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $debugFile;
    /**
     * @var string
     */
    private $prodFile;
    /**
     * @var vfsStreamDirectory
     */
    private $root;
    /**
     * @var array
     */
    private $configs;

    protected function setUp(): void
    {
        $this->useTls = (string) getenv('BOUNCER_TLS_PATH');
        $this->useCurl = (bool) getenv('USE_CURL');

        $this->root = vfsStream::setup('/tmp');
        $this->configs['log_directory_path'] = $this->root->url();

        $currentDate = date('Y-m-d');
        $this->debugFile = 'debug-' . $currentDate . '.log';
        $this->prodFile = 'prod-' . $currentDate . '.log';
        $this->logger = new FileLog(['log_directory_path' => $this->root->url(), 'debug_mode' => true, 'log_rotator' => true], 'php_standalone_bouncer');

        $bouncerConfigs = [
            'auth_type' => $this->useTls ? \CrowdSec\LapiClient\Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => getenv('BOUNCER_KEY'),
            'api_url' => getenv('LAPI_URL'),
            'use_curl' => $this->useCurl,
            'user_agent_suffix' => 'testphpbouncer',
            'fs_cache_path' => $this->root->url() . '/.cache',
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'excluded_uris' => [self::EXCLUDED_URI],
            'stream_mode' => false,
            'trust_ip_forward_array' => ['5.6.7.8'],
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $this->configs = $bouncerConfigs;
        $this->watcherClient = new WatcherClient($this->configs);
        // Delete all decisions
        $this->watcherClient->deleteAllDecisions();
    }

    public function cacheAdapterConfigProvider(): array
    {
        return TestHelpers::cacheAdapterConfigProvider();
    }

    private function cacheAdapterCheck($cacheAdapter, $origCacheName)
    {
        switch ($origCacheName) {
            case 'PhpFilesAdapter':
                $this->assertEquals(
                    'CrowdSec\RemediationEngine\CacheStorage\PhpFiles',
                    get_class($cacheAdapter),
                    'Tested adapter should be correct'
                );
                break;
            case 'MemcachedAdapter':
                $this->assertEquals(
                    'CrowdSec\RemediationEngine\CacheStorage\Memcached',
                    get_class($cacheAdapter),
                    'Tested adapter should be correct'
                );
                break;
            case 'RedisAdapter':
                $this->assertEquals(
                    'CrowdSec\RemediationEngine\CacheStorage\Redis',
                    get_class($cacheAdapter),
                    'Tested adapter should be correct'
                );
                break;
            default:
                break;
        }
    }

    private function addTlsConfig(&$bouncerConfigs, $tlsPath)
    {
        $bouncerConfigs['tls_cert_path'] = $tlsPath . '/bouncer.pem';
        $bouncerConfigs['tls_key_path'] = $tlsPath . '/bouncer-key.pem';
        $bouncerConfigs['tls_ca_cert_path'] = $tlsPath . '/ca-chain.pem';
        $bouncerConfigs['tls_verify_peer'] = true;
    }

    /**
     * @group integration
     *
     * @dataProvider cacheAdapterConfigProvider
     */
    public function testTestCacheConnexion($cacheAdapterName, $origCacheName)
    {
        $bouncer = new Bouncer(array_merge($this->configs,
            ['cache_system' => $cacheAdapterName]));
        $error = '';
        try {
            $bouncer->testCacheConnection();
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        $this->assertEquals('', $error);

        // Test custom error handler for Memcached
        if ('memcached' === $cacheAdapterName) {
            $configs = array_merge($this->configs,
                [
                    'cache_system' => $cacheAdapterName,
                    'memcached_dsn' => 'memcached://memcached:21',
                ]);
            $bouncer2 = new Bouncer($configs);

            $error = '';
            try {
                $bouncer2->testCacheConnection();
            } catch (BouncerException $e) {
                $error = $e->getMessage();
            }
            PHPUnitUtil::assertRegExp(
                $this,
                '/Error while testing cache connection/',
                $error,
                'Should have throw an error'
            );
        }
        // Test bad dsn for redis
        if ('redis' === $cacheAdapterName) {
            $error = '';
            try {
                $bouncer3 = new Bouncer(array_merge($this->configs,
                    [
                        'cache_system' => $cacheAdapterName,
                        'redis_dsn' => 'redis://redis:21',
                    ]));
            } catch (CacheStorageException $e) {
                $error = $e->getMessage();
            }
            PHPUnitUtil::assertRegExp(
                $this,
                '/Error when creating/',
                $error,
                'Should have throw an error'
            );
        }
    }

    public function testConstructAndSomeMethods()
    {
        unset($_SERVER['REMOTE_ADDR']);
        $bouncer = new Bouncer(array_merge($this->configs, ['unexpected_config' => 'test']));
        $this->assertEquals('', $bouncer->getRemoteIp(), 'Should return empty string');
        $_SERVER['REMOTE_ADDR'] = '5.6.7.8';
        $this->assertEquals('5.6.7.8', $bouncer->getRemoteIp(), 'Should remote ip');

        $this->assertEquals(false, $bouncer->getConfig('stream_mode'), 'Stream mode config');
        $this->assertEquals(FileLog::class, \get_class($bouncer->getLogger()), 'Logger Init');

        $this->assertEquals([['005.006.007.008', '005.006.007.008']], $bouncer->getConfig('trust_ip_forward_array'), 'Forwarded array config');

        $remediation = $bouncer->getRemediationEngine();
        $this->assertEquals('CrowdSec\RemediationEngine\LapiRemediation', \get_class($remediation), 'Remediation Init');
        $this->assertEquals('CrowdSec\RemediationEngine\CacheStorage\PhpFiles', \get_class($remediation->getCacheStorage()), 'Remediation cache Init');

        $error = '';

        try {
            $bouncer =
                new Bouncer(array_merge($this->configs, ['trust_ip_forward_array' => [['001.002.003.004', '001.002.003.004']]]));
        } catch (BouncerException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/\'trust_ip_forward_array\' config must be an array of string/',
            $error,
            'Should have throw an error'
        );

        $this->assertEquals([['005.006.007.008', '005.006.007.008']], $bouncer->getConfig('trust_ip_forward_array'), 'Forwarded array config');

        $this->assertEquals(Constants::BOUNCING_LEVEL_NORMAL, $bouncer->getRemediationEngine()->getConfig('bouncing_level'), 'Bouncing level config');

        $this->assertEquals(null, $bouncer->getConfig('unexpected_config'), 'Should clean config');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertEquals('POST', $bouncer->getHttpMethod(), 'Should get HTTP method');

        $this->assertEquals(null, $bouncer->getHttpRequestHeader('X-Forwarded-For'), 'Should get HTTP header');
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.5';
        $this->assertEquals('1.2.3.5', $bouncer->getHttpRequestHeader('X-Forwarded-For'), 'Should get HTTP header');

        $_POST['test'] = 'test-post';
        $this->assertEquals(null, $bouncer->getPostedVariable('hello'), 'Should return null for non Posted variable');
        $this->assertEquals('test-post', $bouncer->getPostedVariable('test'), 'Should get Posted variable');

        $configs = $bouncer->getConfigs();
        $this->assertArrayHasKey('text', $configs, 'Config should have text key');
        $this->assertArrayHasKey('color', $configs, 'Config should have color key');

        $this->configs['cache_system'] = Constants::CACHE_SYSTEM_REDIS;
        $bouncer = new Bouncer($this->configs);

        $remediation = $bouncer->getRemediationEngine();
        $this->assertEquals('CrowdSec\RemediationEngine\CacheStorage\Redis', \get_class($remediation->getCacheStorage()), 'Remediation cache Init');

        $this->configs['cache_system'] = Constants::CACHE_SYSTEM_MEMCACHED;
        $bouncer = new Bouncer($this->configs);

        $remediation = $bouncer->getRemediationEngine();
        $this->assertEquals('CrowdSec\RemediationEngine\CacheStorage\Memcached', \get_class($remediation->getCacheStorage()), 'Remediation cache Init');
    }

    /**
     * @group integration
     *
     * @dataProvider cacheAdapterConfigProvider
     */
    public function testCanVerifyIpInLiveModeWithCacheSystem($cacheAdapterName, $origCacheName): void
    {
        // Init context
        $this->watcherClient->setInitialState();

        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'use_curl' => $this->useCurl,
            'cache_system' => $cacheAdapterName,
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'fs_cache_path' => $this->root->url() . '/.cache',
            'stream_mode' => false,
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $bouncer = new Bouncer($bouncerConfigs, $this->logger);

        // Test cache adapter
        $cacheAdapter = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheAdapter->clear();
        $this->cacheAdapterCheck($cacheAdapter, $origCacheName);

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'],
            'Get decisions for a bad IP (for the first time, it should be a cache miss)'
        );

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'],
            'Call the same thing for the second time (now it should be a cache hit)'
        );

        $cleanRemediation1stCall = $bouncer->getRemediationForIp(TestHelpers::CLEAN_IP)['remediation'];
        $this->assertEquals(
            'bypass',
            $cleanRemediation1stCall,
            'Get decisions for a clean IP for the first time (it should be a cache miss)'
        );

        // Call the same thing for the second time (now it should be a cache hit)
        $cleanRemediation2ndCall = $bouncer->getRemediationForIp(TestHelpers::CLEAN_IP)['remediation'];
        $this->assertEquals('bypass', $cleanRemediation2ndCall);

        // Prune cache
        if ('PhpFilesAdapter' === $origCacheName) {
            $this->assertTrue($bouncer->pruneCache(), 'The cache should be prunable');
        }

        // Clear cache
        $this->assertTrue($bouncer->clearCache(), 'The cache should be clearable');

        // Call one more time (should miss as the cache has been cleared)

        $remediation3rdCall = $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'];
        $this->assertEquals('ban', $remediation3rdCall);

        // Reconfigure the bouncer to set maximum remediation level to "captcha"
        $bouncerConfigs['bouncing_level'] = Constants::BOUNCING_LEVEL_FLEX;
        $bouncer = new Bouncer($bouncerConfigs, $this->logger);
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'];
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        // Reset the max remediation level to its origin state
        $bouncerConfigs['bouncing_level'] = Constants::BOUNCING_LEVEL_NORMAL;
        $bouncer = new Bouncer($bouncerConfigs, $this->logger);

        $this->logger->info('', ['message' => 'set "Large IPV4 range banned" state']);
        $this->watcherClient->deleteAllDecisions();
        $this->watcherClient->addDecision(
            new \DateTime(),
            '24h',
            WatcherClient::HOURS24,
            TestHelpers::BAD_IP . '/' . TestHelpers::LARGE_IPV4_RANGE,
            'ban'
        );
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'];
        $this->assertEquals(
            'ban',
            $cappedRemediation,
            'The remediation for the banned IPv4 range should be ban'
        );

        $this->logger->info('', ['message' => 'set "IPV6 range banned" state']);
        $this->watcherClient->deleteAllDecisions();
        $this->watcherClient->addDecision(
            new \DateTime(),
            '24h',
            WatcherClient::HOURS24,
            TestHelpers::BAD_IPV6 . '/' . TestHelpers::IPV6_RANGE,
            'ban'
        );
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IPV6)['remediation'];
        $this->assertEquals(
            'ban',
            $cappedRemediation,
            'The remediation for a banned IPv6 range should be ban in live mode'
        );
        $this->watcherClient->deleteAllDecisions();
        $this->watcherClient->addDecision(
            new \DateTime(),
            '24h',
            WatcherClient::HOURS24,
            TestHelpers::BAD_IPV6,
            'ban'
        );
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IPV6)['remediation'];
        $this->assertEquals(
            'ban',
            $cappedRemediation,
            'The remediation for a banned IPv6 should be ban'
        );
    }

    /**
     * @group integration
     *
     * @dataProvider cacheAdapterConfigProvider
     */
    public function testCanVerifyIpInStreamModeWithCacheSystem($cacheAdapterName, $origCacheName): void
    {
        // Init context
        $this->watcherClient->setInitialState();
        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => true,
            'use_curl' => $this->useCurl,
            'cache_system' => $cacheAdapterName,
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'fs_cache_path' => $this->root->url() . '/.cache',
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $bouncer = new Bouncer($bouncerConfigs, $this->logger);
        // Test cache adapter
        $cacheAdapter = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheAdapter->clear();
        $this->cacheAdapterCheck($cacheAdapter, $origCacheName);
        // As we are in stream mode, no live call should be done to the API.
        // Warm BlockList cache up

        $bouncer->refreshBlocklistCache();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'],
            'Get decisions for a bad IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        // Reconfigure the bouncer to set maximum remediation level to "captcha"
        $bouncerConfigs['bouncing_level'] = Constants::BOUNCING_LEVEL_FLEX;
        $bouncer = new Bouncer($bouncerConfigs, $this->logger);
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'];
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        $bouncerConfigs['bouncing_level'] = Constants::BOUNCING_LEVEL_NORMAL;
        $bouncer = new Bouncer($bouncerConfigs, $this->logger);
        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::CLEAN_IP)['remediation'],
            'Get decisions for a clean IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        // Preload the remediation to prepare the next tests.
        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::NEWLY_BAD_IP)['remediation'],
            'Preload the bypass remediation to prepare the next tests'
        );

        // Add and remove decision
        $this->watcherClient->setSecondState();

        // Pull updates
        $bouncer->refreshBlocklistCache();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::NEWLY_BAD_IP)['remediation'],
            'The new decision should now be added, so the previously clean IP should now be bad'
        );

        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'],
            'The old decisions should now be removed, so the previously bad IP should now be clean'
        );

        // Set up a new instance.
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => true,
            'use_curl' => $this->useCurl,
            'cache_system' => $cacheAdapterName,
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'fs_cache_path' => $this->root->url() . '/.cache',
        ];
        if ($this->useTls) {
            $bouncerConfigs['tls_cert_path'] = $this->useTls . '/bouncer.pem';
            $bouncerConfigs['tls_key_path'] = $this->useTls . '/bouncer-key.pem';
            $bouncerConfigs['tls_ca_cert_path'] = $this->useTls . '/ca-chain.pem';
            $bouncerConfigs['tls_verify_peer'] = true;
        }

        $bouncer = new Bouncer($bouncerConfigs, $this->logger);

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::NEWLY_BAD_IP)['remediation'],
            'The cache warm up should be stored across each instantiation'
        );

        $this->logger->info('', ['message' => 'set "Large IPV4 range banned" + "IPV6 range banned" state']);
        $this->watcherClient->deleteAllDecisions();
        $this->watcherClient->addDecision(
            new \DateTime(),
            '24h',
            WatcherClient::HOURS24,
            TestHelpers::BAD_IP . '/' . TestHelpers::LARGE_IPV4_RANGE,
            'ban'
        );
        $this->watcherClient->addDecision(
            new \DateTime(),
            '24h',
            WatcherClient::HOURS24,
            TestHelpers::BAD_IPV6 . '/' . TestHelpers::IPV6_RANGE,
            'ban'
        );
        // Pull updates
        $bouncer->refreshBlocklistCache();

        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'];
        $this->assertEquals(
            'ban',
            $cappedRemediation,
            'The remediation for the banned IP with a large range should be "ban" even in stream mode'
        );
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IPV6)['remediation'];
        $this->assertEquals(
            'bypass',
            $cappedRemediation,
            'The remediation for the banned IPV6 with a too large range should now be "bypass" as we are in stream mode'
        );

        // Test cache connection
        $bouncer->testCacheConnection();
    }

    /**
     * @group ban
     *
     * @return void
     *
     * @throws BouncerException
     * @throws CacheStorageException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testBanFlow()
    {
        $this->watcherClient->setSimpleDecision('ban');
        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => false,
            'use_curl' => $this->useCurl,
            'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
            'fs_cache_path' => $this->root->url() . '/.cache',
            'forced_test_ip' => TestHelpers::BAD_IP,
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $bouncer = new StandaloneBouncerNoResponse($bouncerConfigs, $this->logger);
        $bouncer->clearCache();

        $cache = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheKey = $cache->getCacheKey(Constants::SCOPE_IP, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            false,
            $item->isHit(),
            'The remediation should not be cached'
        );

        $bouncer->bounceCurrentIp();

        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            true,
            $item->isHit(),
            'The remediation should be cached'
        );
    }

    /**
     * @group appsec
     *
     * @return void
     *
     * @throws BouncerException
     * @throws CacheStorageException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testAppSecFlow()
    {
        $this->watcherClient->setSimpleDecision('ban');
        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'appsec_url' => TestHelpers::getAppSecUrl(),
            'use_appsec' => true,
            'stream_mode' => false,
            'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
            'fs_cache_path' => $this->root->url() . '/.cache',
            'forced_test_ip' => TestHelpers::BAD_IP,
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }
        unset($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_EXPOSE'], $_SERVER['HTTP_X_FORWARDED_FOR']);

        $bouncer = new StandaloneBouncerNoResponse($bouncerConfigs, $this->logger);
        $bouncer->clearCache();

        // TEST 1 : ban from LAPI

        $cache = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheKey = $cache->getCacheKey(Constants::SCOPE_IP, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            false,
            $item->isHit(),
            'The remediation should not be cached'
        );

        $bouncer->bounceCurrentIp();

        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            true,
            $item->isHit(),
            'The remediation should be cached'
        );
        $cachedItem = $item->get();
        $this->assertEquals(
            'ban',
            $cachedItem[0][0],
            'The remediation should be ban'
        );

        // Test 2: ban from APP SEC
        $this->watcherClient->deleteAllDecisions();
        $bouncer->clearCache();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/.env';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $bouncer->bounceCurrentIp();
        $this->assertEquals(
            'Mozilla/5.0',
            $bouncer->getRequestUserAgent(),
            'User Agent should be ok'
        );
        $this->assertEquals(
            'example.com',
            $bouncer->getRequestHost(),
            'HTTP Host should be ok'
        );
        $this->assertEquals(
            '/.env',
            $bouncer->getRequestUri(),
            'Request URI should be ok'
        );
        $this->assertEquals(
            'GET',
            $bouncer->getHttpMethod(),
            'HTTP Method should be ok'
        );
        $this->assertEquals(
            ['Host' => 'example.com', 'User-Agent' => 'Mozilla/5.0', 'Content-Type' => 'application/json'],
            $bouncer->getRequestHeaders(),
            'Request headers should be ok'
        );
        $this->assertEquals(
            '',
            $bouncer->getRequestRawBody(),
            'Request raw body should be ok'
        );

        $cache = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheKey = $cache->getCacheKey(Constants::SCOPE_IP, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKey);
        $cachedItem = $item->get();
        $this->assertEquals(
            'bypass',
            $cachedItem[0][0],
            'The LAPI remediation should be bypass and has been stored'
        );

        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        if ($this->useTls) {
            $this->assertArrayNotHasKey('appsec', $originCountItem, 'The origin count for appsec should not be set');
        } else {
            $this->assertEquals(
                ['ban' => 1],
                $originCountItem['appsec'],
                'The origin count for appsec should be 1'
            );
        }

        // Test 3: clean IP and clean request
        $bouncer->clearCache();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/home.php';
        $bouncer->bounceCurrentIp();
        $cache = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheKey = $cache->getCacheKey(Constants::SCOPE_IP, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKey);
        $cachedItem = $item->get();
        $this->assertEquals(
            'bypass',
            $cachedItem[0][0],
            'The LAPI remediation should be bypass and has been stored'
        );

        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        if ($this->useTls) {
            $this->assertArrayNotHasKey('clean_appsec', $originCountItem, 'The origin count for clean appsec should not be set');
        } else {
            $this->assertEquals(
                ['bypass' => 1],
                $originCountItem['clean_appsec'],
                'The origin count for clean appsec should be 1'
            );
        }
    }

    /**
     * @group captcha
     *
     * @return void
     *
     * @throws BouncerException
     * @throws CacheStorageException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testCaptchaFlow()
    {
        $this->watcherClient->setSimpleDecision('captcha');
        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => false,
            'use_curl' => $this->useCurl,
            'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
            'fs_cache_path' => $this->root->url() . '/.cache',
            'forced_test_ip' => TestHelpers::BAD_IP,
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $bouncer = new StandaloneBouncerNoResponse($bouncerConfigs, $this->logger);
        $bouncer->clearCache();

        $cache = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheKey = $cache->getCacheKey(Constants::SCOPE_IP, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            false,
            $item->isHit(),
            'The remediation should not be cached'
        );

        $cacheKeyCaptcha = $cache->getCacheKey(Constants::CACHE_TAG_CAPTCHA, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKeyCaptcha);
        $this->assertEquals(
            false,
            $item->isHit(),
            'The captcha variables should not be cached'
        );

        // Step 1 : access a page should display a captcha wall
        $_SERVER['HTTP_REFERER'] = 'UNIT-TEST';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $bouncer->bounceCurrentIp();

        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            true,
            $item->isHit(),
            'The remediation should be cached'
        );

        $cacheKeyCaptcha = $cache->getCacheKey(Constants::CACHE_TAG_CAPTCHA, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKeyCaptcha);
        $this->assertEquals(
            true,
            $item->isHit(),
            'The captcha variables should be cached'
        );

        $this->assertEquals(
            true,
            $item->isHit(),
            'The captcha variables should be cached'
        );

        $cached = $item->get();
        $this->assertEquals(
            true,
            $cached['has_to_be_resolved'],
            'The captcha variables should be cached'
        );
        $phraseToGuess = $cached['phrase_to_guess'];
        $this->assertEquals(
            5,
            strlen($phraseToGuess),
            'The captcha variables should be cached'
        );
        $this->assertEquals(
            '/',
            $cached['resolution_redirect'],
            'The captcha variables should be cached'
        );
        $this->assertNotEmpty($cached['inline_image'],
            'The captcha variables should be cached');

        $this->assertEquals(
            false,
            $cached['resolution_failed'],
            'The captcha variables should be cached'
        );

        // Step 2 :refresh
        $_SERVER['HTTP_REFERER'] = 'UNIT-TEST';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['refresh'] = '1';
        $_POST['crowdsec_captcha'] = '1';
        $bouncer->bounceCurrentIp();
        $cacheKeyCaptcha = $cache->getCacheKey(Constants::CACHE_TAG_CAPTCHA, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKeyCaptcha);
        $cached = $item->get();
        $phraseToGuess2 = $cached['phrase_to_guess'];
        $this->assertNotEquals(
            $phraseToGuess2,
            $phraseToGuess,
            'Phrase should have been refresh'
        );
        $this->assertEquals(
            '/',
            $cached['resolution_redirect'],
            'Referer is only for the first step if post'
        );

        // STEP 3 : resolve captcha but failed
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_POST['refresh']);
        $_POST['phrase'] = 'bad-phrase';
        $_POST['crowdsec_captcha'] = '1';
        $bouncer->bounceCurrentIp();

        $cacheKeyCaptcha = $cache->getCacheKey(Constants::CACHE_TAG_CAPTCHA, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKeyCaptcha);
        $cached = $item->get();

        $this->assertEquals(
            true,
            $cached['resolution_failed'],
            'Failed should be cached'
        );

        // STEP 4 : resolve captcha success
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['phrase'] = $phraseToGuess2;
        $_POST['crowdsec_captcha'] = '1';

        $bouncer->bounceCurrentIp();

        $cacheKeyCaptcha = $cache->getCacheKey(Constants::CACHE_TAG_CAPTCHA, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKeyCaptcha);
        $cached = $item->get();

        $this->assertEquals(
            false,
            $cached['has_to_be_resolved'],
            'Resolved should be cached'
        );
    }

    public function testRun()
    {
        $this->assertEquals(
            false,
            file_exists($this->root->url() . '/' . $this->prodFile),
            'Prod File should not exist'
        );
        $this->assertEquals(
            false,
            file_exists($this->root->url() . '/' . $this->debugFile),
            'Debug File should not exist'
        );
        // Test 1:  remote ip is as expected
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // We have set 'trust_ip_forward_array' => ['5.6.7.8'] in $configs
        $bouncer = new Bouncer($this->configs, $this->logger);
        $this->assertEquals('127.0.0.1', $bouncer->getRemoteIp(), 'Get remote IP');
        // Test 2: not bouncing exclude URI
        $_SERVER['REMOTE_ADDR'] = '127.0.0.2';
        $_SERVER['REQUEST_URI'] = self::EXCLUDED_URI;
        $this->assertEquals(false, $bouncer->run(), 'Should not bounce excluded uri');
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*This URI is excluded from bouncing/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );

        // Test 3: bouncing URI
        $_SERVER['REMOTE_ADDR'] = '127.0.0.3';
        $_SERVER['REQUEST_URI'] = '/home';
        $this->assertEquals(true, $bouncer->run(), 'Should bounce uri');
        // Test 4:  not bouncing URI if disabled
        $_SERVER['REMOTE_ADDR'] = '127.0.0.4';
        $bouncer = new Bouncer(array_merge($this->configs, ['bouncing_level' => Constants::BOUNCING_LEVEL_DISABLED]), $this->logger);
        $this->assertEquals(false, $bouncer->run(), 'Should not bounce if disabled');

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*Bouncing is disabled by bouncing_level configuration/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );

        // Test 5: throw error if config says so
        $_SERVER['REMOTE_ADDR'] = '127.0.0.5';
        $bouncer = new Bouncer(
            array_merge(
                $this->configs,
                [
                    'display_errors' => true,
                    'api_url' => 'bad-url',
                ]
            ), $this->logger
        );

        $error = '';

        try {
            $bouncer->run();
        } catch (BouncerException $e) {
            $error = $e->getMessage();
        }

        $errorExpected = $this->useCurl ? '/Could not resolve host/' : '/ailed to open stream/';
        PHPUnitUtil::assertRegExp(
            $this,
            $errorExpected,
            $error,
            'Should have throw an error'
        );
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*400.*EXCEPTION_WHILE_BOUNCING/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 6: NOT throw error if config says so
        $_SERVER['REMOTE_ADDR'] = '127.0.0.6';
        $bouncer = new Bouncer(
            array_merge(
                $this->configs,
                [
                    'display_errors' => false,
                    'api_url' => 'bad-url',
                ]
            ), $this->logger);

        $error = '';

        try {
            $bouncer->run();
        } catch (BouncerException $e) {
            $error = $e->getMessage();
        }

        $this->assertEquals('', $error, 'Should not throw error');
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*400.*EXCEPTION_WHILE_BOUNCING/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 7 : no-forward
        $_SERVER['REMOTE_ADDR'] = '127.0.0.7';
        $bouncer = new Bouncer(
            array_merge(
                $this->configs,
                [
                    'forced_test_forwarded_ip' => Constants::X_FORWARDED_DISABLED,
                ]
            ),
            $this->logger
        );
        $bouncer->run();
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*X-Forwarded-for usage is disabled/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );
        // Test 8 : forced X-Forwarded-for usage
        $_SERVER['REMOTE_ADDR'] = '127.0.0.8';
        $bouncer = new Bouncer(
            array_merge(
                $this->configs,
                [
                    'forced_test_forwarded_ip' => '1.2.3.5',
                ]
            ), $this->logger
        );
        $bouncer->run();
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*X-Forwarded-for usage is forced.*"x_forwarded_for_ip":"1.2.3.5"/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );
        // Test 9 non-authorized
        $_SERVER['REMOTE_ADDR'] = '127.0.0.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.5';
        $bouncer = new Bouncer(
            array_merge(
                $this->configs
            ), $this->logger
        );
        $bouncer->run();
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*300.*Detected IP is not allowed for X-Forwarded-for usage.*"x_forwarded_for_ip":"1.2.3.5"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 10 authorized
        $_SERVER['REMOTE_ADDR'] = '5.6.7.8';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.10';
        $bouncer = new Bouncer(
            array_merge(
                $this->configs
            ), $this->logger
        );
        $bouncer->run();
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*Detected IP is allowed for X-Forwarded-for usage.*"original_ip":"5.6.7.8","x_forwarded_for_ip":"127.0.0.10"/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );
    }
}
