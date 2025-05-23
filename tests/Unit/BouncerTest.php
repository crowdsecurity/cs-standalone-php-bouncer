<?php

declare(strict_types=1);

namespace CrowdSecStandalone\Tests\Unit;

/**
 * Test for templating.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */

use CrowdSec\Common\Logger\FileLog;
use CrowdSec\LapiClient\Constants as LapiConstants;
use CrowdSecBouncer\BouncerException;
use CrowdSecStandalone\Bouncer;
use CrowdSecStandalone\Constants;
use CrowdSecStandalone\Tests\PHPUnitUtil;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CrowdSecStandalone\Bouncer::handleTrustedIpsConfig
 * @covers \CrowdSecStandalone\Bouncer::getHttpRequestHeader
 * @covers \CrowdSecStandalone\Bouncer::getRemoteIp
 * @covers \CrowdSecStandalone\Bouncer::run
 * @covers \CrowdSecStandalone\Bouncer::shouldBounceCurrentIp
 * @covers \CrowdSecStandalone\Bouncer::__construct
 * @covers \CrowdSecStandalone\Bouncer::getHttpMethod
 * @covers \CrowdSecStandalone\Bouncer::getPostedVariable
 */
final class BouncerTest extends TestCase
{
    private const EXCLUDED_URI = '/favicon.ico';
    /**
     * @var string
     */
    private $debugFile;
    /**
     * @var FileLog
     */
    private $logger;
    /**
     * @var string
     */
    private $prodFile;
    /**
     * @var vfsStreamDirectory
     */
    private $root;

    protected $configs = [
        // ============================================================================#
        // Bouncer configs
        // ============================================================================#
        'use_curl' => false,
        'debug_mode' => true,
        'disable_prod_log' => false,
        'log_directory_path' => __DIR__ . '/.logs',
        'display_errors' => true,
        'forced_test_ip' => '',
        'forced_test_forwarded_ip' => '',
        'bouncing_level' => Constants::BOUNCING_LEVEL_NORMAL,
        'trust_ip_forward_array' => ['5.6.7.8'],
        'excluded_uris' => [self::EXCLUDED_URI],
        'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
        'captcha_cache_duration' => Constants::CACHE_EXPIRATION_FOR_CAPTCHA,
        'custom_css' => '',
        'hide_mentions' => false,
        'color' => [
            'text' => [
                'primary' => 'black',
                'secondary' => '#AAA',
                'button' => 'white',
                'error_message' => '#b90000',
            ],
            'background' => [
                'page' => '#eee',
                'container' => 'white',
                'button' => '#626365',
                'button_hover' => '#333',
            ],
        ],
        'text' => [
            'captcha_wall' => [
                'tab_title' => 'Oops..',
                'title' => 'Hmm, sorry but...',
                'subtitle' => 'Please complete the security check.',
                'refresh_image_link' => 'refresh image',
                'captcha_placeholder' => 'Type here...',
                'send_button' => 'CONTINUE',
                'error_message' => 'Please try again.',
                'footer' => '',
            ],
            'ban_wall' => [
                'tab_title' => 'Oops..',
                'title' => '🤭 Oh!',
                'subtitle' => 'This page is protected against cyber attacks and your IP has been banned by our system.',
                'footer' => '',
            ],
        ],
        // ============================================================================#
        // Client configs
        // ============================================================================#
        'auth_type' => Constants::AUTH_KEY,
        'tls_cert_path' => '',
        'tls_key_path' => '',
        'tls_verify_peer' => true,
        'tls_ca_cert_path' => '',
        'api_key' => 'unit-test',
        'api_url' => LapiConstants::DEFAULT_LAPI_URL,
        'appsec_url' => LapiConstants::DEFAULT_APPSEC_URL,
        'use_appsec' => false,
        'api_timeout' => 1,
        // ============================================================================#
        // Remediation engine configs
        // ============================================================================#
        'fallback_remediation' => Constants::REMEDIATION_CAPTCHA,
        'ordered_remediations' => [Constants::REMEDIATION_BAN, Constants::REMEDIATION_CAPTCHA],
        'fs_cache_path' => __DIR__ . '/.cache',
        'redis_dsn' => 'redis://localhost:6379',
        'memcached_dsn' => 'memcached://localhost:11211',
        'clean_ip_cache_duration' => 1,
        'bad_ip_cache_duration' => 1,
        'stream_mode' => false,
        'geolocation' => [
            'enabled' => false,
            'type' => Constants::GEOLOCATION_TYPE_MAXMIND,
            'cache_duration' => Constants::CACHE_EXPIRATION_FOR_GEO,
            'maxmind' => [
                'database_type' => Constants::MAXMIND_COUNTRY,
                'database_path' => '/some/path/GeoLite2-Country.mmdb',
            ],
        ],
    ];

    protected function setUp(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $this->root = vfsStream::setup('/tmp');
        $this->configs['log_directory_path'] = $this->root->url();
        $this->configs['fs_cache_path'] = $this->root->url() . '/.cache';
        $currentDate = date('Y-m-d');
        $this->debugFile = 'debug-' . $currentDate . '.log';
        $this->prodFile = 'prod-' . $currentDate . '.log';
        $this->logger = new FileLog(['log_directory_path' => $this->root->url(), 'debug_mode' => true]);
    }

    public function testPrivateAndProtectedMethods()
    {
        if (PHP_VERSION_ID >= 80400) {
            // Retrieve the current error reporting level
            $originalErrorReporting = error_reporting();
            // Suppress deprecated warnings temporarily
            // We do this because of
            // Deprecated: Gregwar\Captcha\CaptchaBuilder::__construct(): Implicitly marking parameter $builder as nullable
            // is deprecated, the explicit nullable type must be used instead
            error_reporting($originalErrorReporting & ~E_DEPRECATED);
        }
        $bouncer = new Bouncer($this->configs);

        // checkCaptcha
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'checkCaptcha',
            ['test1', 'test2', '5.6.7.8']
        );
        $this->assertEquals(false, $result, 'Captcha should be marked as not resolved');

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'checkCaptcha',
            ['test1', 'test1', '5.6.7.8']
        );
        $this->assertEquals(true, $result, 'Captcha should be marked as resolved');

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'checkCaptcha',
            ['test1', 'TEST1', '5.6.7.8']
        );
        $this->assertEquals(true, $result, 'Captcha should be marked as resolved even for case non matching');

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'checkCaptcha',
            ['001', 'ool', '5.6.7.8']
        );
        $this->assertEquals(true, $result, 'Captcha should be marked as resolved even for some similar chars');

        // buildCaptchaCouple
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'buildCaptchaCouple',
            []
        );

        $this->assertArrayHasKey('phrase', $result, 'Captcha couple should have a phrase');
        $this->assertArrayHasKey('inlineImage', $result, 'Captcha couple should have a inlineImage');

        $this->assertIsString($result['phrase'], 'Captcha phrase should be ok');
        $this->assertEquals(5, strlen($result['phrase']), 'Captcha phrase should be of length 5');

        $this->assertStringStartsWith('data:image/jpeg;base64', $result['inlineImage'], 'Captcha image should be ok');

        // getCache
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'getCache',
            []
        );

        $this->assertEquals('CrowdSec\RemediationEngine\CacheStorage\PhpFiles', \get_class($result), 'Get cache should return remediation cache');
        // getBanHtml
        $this->configs = array_merge($this->configs, [
            'text' => [
                'ban_wall' => [
                    'title' => 'BAN TEST TITLE',
                ],
            ],
        ]);
        $bouncer = new Bouncer($this->configs);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'getBanHtml',
            []
        );
        $this->assertStringContainsString('<h1>BAN TEST TITLE</h1>', $result, 'Ban rendering should be as expected');

        // getCaptchaHtml
        $this->configs = array_merge($this->configs, [
            'text' => [
                'captcha_wall' => [
                    'title' => 'CAPTCHA TEST TITLE',
                ],
            ],
        ]);
        $bouncer = new Bouncer($this->configs);
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'getCaptchaHtml',
            [false, 'fake-inline-image', 'fake-url']
        );
        $this->assertStringContainsString('CAPTCHA TEST TITLE', $result, 'Captcha rendering should be as expected');
        $this->assertStringNotContainsString('<p class="error">', $result, 'Should be no error message');

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'getCaptchaHtml',
            [true, 'fake-inline-image', 'fake-url']
        );
        $this->assertStringContainsString('<p class="error">', $result, 'Should be no error message');

        // shouldNotCheckResolution
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldNotCheckResolution',
            [['has_to_be_resolved' => false]]
        );

        $this->assertEquals(true, $result, 'No check if no flagged has_to_be_resolved');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldNotCheckResolution',
            [['has_to_be_resolved' => true]]
        );

        $this->assertEquals(true, $result, 'No check if method is not POST');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['crowdsec_captcha'] = 'test';
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldNotCheckResolution',
            [['has_to_be_resolved' => true]]
        );

        $this->assertEquals(false, $result, 'Check if method is POST and posted crowdsec_captcha');
        unset($_POST['crowdsec_captcha']);
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldNotCheckResolution',
            [['has_to_be_resolved' => true]]
        );

        $this->assertEquals(true, $result, 'No check if method is POST and no posted crowdsec_captcha');

        // shouldTrustXforwardedFor
        unset($_POST['crowdsec_captcha']);
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldTrustXforwardedFor',
            ['not-an-ip']
        );
        $this->assertEquals(false, $result, 'Should return false if ip is invalid');

        // handleTrustedIpsConfig
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleTrustedIpsConfig',
            [['trust_ip_forward_array' => ['1.2.3.4']]]
        );

        $this->assertEquals(['trust_ip_forward_array' => [['001.002.003.004', '001.002.003.004']]], $result, 'Should
        return comparable array');

        $error = '';

        try {
            PHPUnitUtil::callMethod(
                $bouncer,
                'handleTrustedIpsConfig',
                [['trust_ip_forward_array' => [['001.002.003.004', '001.002.003.004']]]]
            );
        } catch (BouncerException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/config must be an array of string/',
            $error,
            'Should have throw an error'
        );
        if (PHP_VERSION_ID >= 80400 && isset($originalErrorReporting)) {
            // Restore the original error reporting level
            error_reporting($originalErrorReporting);
        }
    }
}
