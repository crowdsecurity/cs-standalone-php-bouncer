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
        $logConfigs = array_merge($configs, ['no_rotation' => true]);
        $this->logger = $logger ?: new FileLog($logConfigs, 'php_standalone_bouncer');
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
    public function getHttpMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? '';
    }

    /**
     * @param string $name Ex: "X-Forwarded-For"
     */
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
    public function getRemoteIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function getRequestHeaders(): array
    {
        $allHeaders = [];

        if (function_exists('getallheaders')) {
            // @codeCoverageIgnoreStart
            $allHeaders = getallheaders();
            // @codeCoverageIgnoreEnd
        } else {
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
    public function getRequestHost(): string
    {
        return $_SERVER['HTTP_HOST'] ?? '';
    }

    public function getRequestRawBody(): string
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // Handle multipart/form-data (file uploads)
        if (strpos($contentType, 'multipart/form-data') !== false) {
            return $this->getMultipartRawBody();
        }

        // For all other content types (including application/json, etc.)
        return $this->getRawInput();
    }

    /**
     * Reconstructs the raw input for non-multipart form data requests (GET, POST, PUT, PATCH, DELETE, etc.)
     * without using file_get_contents().
     */
    private function getRawInput(): string
    {
        $input = '';
        $inputStream = fopen('php://input', 'rb');

        if ($inputStream) {
            while (!feof($inputStream)) {
                $input .= fread($inputStream, 8192); // Reading the body in chunks
            }
            fclose($inputStream);
        }

        return $input;
    }

    private function getMultipartRawBody(): string
    {
        $rawBody = '';

        // Extract boundary from Content-Type
        $boundary = substr($_SERVER['CONTENT_TYPE'], strpos($_SERVER['CONTENT_TYPE'], "boundary=") + 9);

        // Rebuild multipart body using $_POST and $_FILES
        if (!empty($_POST)) {
            foreach ($_POST as $key => $value) {
                $rawBody .= "--" . $boundary . "\r\n";
                $rawBody .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
                $rawBody .= $value . "\r\n";
            }
        }

        if (!empty($_FILES)) {
            foreach ($_FILES as $fileKey => $fileArray) {
                $rawBody .= "--" . $boundary . "\r\n";
                $rawBody .= "Content-Disposition: form-data; name=\"{$fileKey}\"; filename=\"{$fileArray['name']}\"\r\n";
                $rawBody .= "Content-Type: {$fileArray['type']}\r\n\r\n";

                // Open the temporary file and read its content in chunks using fopen()
                $fileStream = fopen($fileArray['tmp_name'], 'rb');
                if ($fileStream) {
                    while (!feof($fileStream)) {
                        $rawBody .= fread($fileStream, 8192); // Reading the file in 8KB chunks
                    }
                    fclose($fileStream);
                }
                $rawBody .= "\r\n";
            }
        }

        // End boundary
        $rawBody .= "--" . $boundary . "--\r\n";

        return $rawBody;
    }

    /**
     * The current URI.
     */
    public function getRequestUri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '';
    }

    /**
     * Get current request user agent.
     */
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
