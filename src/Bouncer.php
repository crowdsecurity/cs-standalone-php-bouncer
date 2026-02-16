<?php

declare(strict_types=1);

namespace CrowdSecStandalone;

use CrowdSec\Common\Logger\FileLog;
use CrowdSec\RemediationEngine\CacheStorage\CacheStorageException;
use CrowdSec\RemediationEngine\LapiRemediation;
use CrowdSecBouncer\AbstractBouncer;
use CrowdSecBouncer\BouncerException;
use IPLib\Factory;
use Psr\Log\LoggerInterface;

/**
 * The bouncer class for standalone mode.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2021+ CrowdSec
 * @license   MIT License
 */
class Bouncer extends AbstractBouncer
{
    /**
     * @throws BouncerException
     * @throws CacheStorageException
     */
    public function __construct(array $configs, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new FileLog($configs, 'php_standalone_bouncer');
        $configs = $this->handleTrustedIpsConfig($configs);
        $configs['user_agent_version'] = Constants::VERSION;
        $configs['user_agent_suffix'] = Constants::USER_AGENT_SUFFIX;
        $client = $this->handleClient($configs, $this->logger);
        $cache = $this->handleCache($configs, $this->logger);
        $remediation = new LapiRemediation($configs, $client, $cache, $this->logger);

        parent::__construct($configs, $remediation, $this->logger);
    }

    /**
     * The current HTTP method.
     */
    #[\Override]
    public function getHttpMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? '';
    }

    /**
     * @param string $name Ex: "X-Forwarded-For"
     */
    #[\Override]
    public function getHttpRequestHeader(string $name): ?string
    {
        $headerName = 'HTTP_' . str_replace('-', '_', strtoupper($name));
        if (!\array_key_exists($headerName, $_SERVER)) {
            return null;
        }

        return is_string($_SERVER[$headerName]) ? $_SERVER[$headerName] : null;
    }

    /**
     * Get the value of a posted field.
     */
    #[\Override]
    public function getPostedVariable(string $name): ?string
    {
        if (!isset($_POST[$name])) {
            return null;
        }

        return is_string($_POST[$name]) ? $_POST[$name] : null;
    }

    /**
     * @return string The current IP, even if it's the IP of a proxy
     */
    #[\Override]
    public function getRemoteIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    #[\Override]
    public function getRequestHeaders(): array
    {
        $allHeaders = [];

        if (function_exists('getallheaders')) {
            // @codeCoverageIgnoreStart
            $allHeaders = getallheaders() ?: [];
        // @codeCoverageIgnoreEnd
        } else {
            $this->logger->warning(
                'getallheaders() function is not available',
                [
                    'type' => 'GETALLHEADERS_NOT_AVAILABLE',
                    'message' => 'Resulting headers may not be accurate',
                ]
            );
            foreach ($_SERVER as $name => $value) {
                if ('HTTP_' == substr($name, 0, 5)) {
                    $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $allHeaders[$name] = $value;
                } elseif ('CONTENT_TYPE' == $name) {
                    $allHeaders['Content-Type'] = $value;
                }
            }
        }

        return $allHeaders;
    }

    /**
     * Get current request host.
     */
    #[\Override]
    public function getRequestHost(): string
    {
        return $_SERVER['HTTP_HOST'] ?? '';
    }

    #[\Override]
    public function getRequestRawBody(): string
    {
        return $this->buildRequestRawBody(fopen('php://input', 'rb'));
    }

    /**
     * The current URI.
     */
    #[\Override]
    public function getRequestUri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '';
    }

    /**
     * Get current request user agent.
     */
    #[\Override]
    public function getRequestUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * The Standalone bouncer "trust_ip_forward_array" config accepts an array of IPs.
     * This method will return array of comparable IPs array.
     *
     * @param array $configs // ['1.2.3.4']
     *
     * @return array // [['001.002.003.004', '001.002.003.004']]
     *
     * @throws BouncerException
     */
    private function handleTrustedIpsConfig(array $configs): array
    {
        // Convert array of string to array of array with comparable IPs
        if (isset($configs['trust_ip_forward_array']) && \is_array($configs['trust_ip_forward_array'])) {
            $forwardConfigs = $configs['trust_ip_forward_array'];
            $finalForwardConfigs = [];
            foreach ($forwardConfigs as $forwardConfig) {
                if (!\is_string($forwardConfig)) {
                    throw new BouncerException('\'trust_ip_forward_array\' config must be an array of string');
                }
                $parsedString = Factory::parseAddressString($forwardConfig, 3);
                if (!empty($parsedString)) {
                    $comparableValue = $parsedString->getComparableString();
                    $finalForwardConfigs[] = [$comparableValue, $comparableValue];
                }
            }
            $configs['trust_ip_forward_array'] = $finalForwardConfigs;
        }

        return $configs;
    }
}
