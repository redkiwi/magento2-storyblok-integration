<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

use Exception;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class AssetProxy
{
    private const CACHE_MAX_AGE = 31536000;
    private const CURL_TIMEOUT = 10;
    private const LOG_PREFIX = '[Storyblok Asset Proxy Error]';

    public function __construct(
        private readonly Config $config,
        private readonly AssetProxyUrl $assetProxyUrl,
        private readonly Curl $curl,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * @return array{body: string, contentType: string, cacheMaxAge: int}|null
     */
    public function fetch(string $encodedPath): ?array
    {
        $decodedPath = $this->assetProxyUrl->decodePath($encodedPath);
        if ($decodedPath === null) {
            $raw = @hex2bin($encodedPath);
            $this->logger->error(self::LOG_PREFIX . ' Invalid encoded path', [
                'encodedPath' => $encodedPath,
                'decoded' => $raw !== false ? $raw : '(decode failed)',
            ]);
            return null;
        }

        $remoteUrl = 'https://' . $this->config->primaryAssetHost() . '/' . $decodedPath;

        try {
            $this->curl->setTimeout(self::CURL_TIMEOUT);
            $this->curl->get($remoteUrl);
        } catch (Exception $e) {
            $this->logger->error(self::LOG_PREFIX. ' Error during request', [
                'url' => $remoteUrl,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }

        $status = $this->curl->getStatus();
        if ($status !== 200) {
            $this->logger->error(self::LOG_PREFIX. ' Storyblok responded with none 200 response: ', [
                'url' => $remoteUrl,
                'status' => $status,
            ]);
            return null;
        }

        return [
            'body' => $this->curl->getBody(),
            'contentType' => $this->getResponseHeader('content-type'),
            'cacheMaxAge' => self::CACHE_MAX_AGE,
        ];
    }

    private function getResponseHeader(string $name): string
    {
        $headers = $this->curl->getHeaders();
        $nameLower = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === $nameLower) {
                return (string)$value;
            }
        }

        return '';
    }
}
