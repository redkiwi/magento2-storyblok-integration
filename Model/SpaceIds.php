<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

use Exception;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The ids of the Storyblok spaces this installation's stores read from, resolved from each store's API token.
 *
 * The union rather than the current store's space: content in one space may reference assets of another.
 */
class SpaceIds
{
    private const API_URL = 'https://api.storyblok.com/v2/cdn/spaces/me?token=';
    private const CACHE_PREFIX = 'storyblok_space_id_';
    private const CACHE_LIFETIME = 604800;
    private const CURL_TIMEOUT = 5;

    /** @var string[]|null */
    private ?array $ids = null;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly CacheInterface $cache,
        private readonly Curl $curl,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * @return string[]
     */
    public function get(): array
    {
        if ($this->ids !== null) {
            return $this->ids;
        }

        $tokens = [];
        foreach ($this->storeManager->getStores() as $store) {
            $token = $this->config->apiKey($store->getCode());
            if ($token !== '') {
                $tokens[$token] = true;
            }
        }

        $ids = [];
        foreach (array_keys($tokens) as $token) {
            $id = $this->resolve((string) $token);
            if ($id !== null) {
                $ids[$id] = true;
            }
        }

        return $this->ids = array_map('strval', array_keys($ids));
    }

    private function resolve(string $token): ?string
    {
        $cacheKey = self::CACHE_PREFIX . sha1($token);
        $cached = $this->cache->load($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $this->curl->setTimeout(self::CURL_TIMEOUT);
            $this->curl->get(self::API_URL . rawurlencode($token));
            $body = json_decode($this->curl->getBody(), true);
        } catch (Exception $e) {
            $this->logger->warning('[Storyblok] Space id lookup failed: ' . $e->getMessage());

            return null;
        }

        $id = is_array($body) ? ($body['space']['id'] ?? null) : null;
        if ($this->curl->getStatus() !== 200 || !is_scalar($id)) {
            $this->logger->warning('[Storyblok] Space id lookup failed', ['status' => $this->curl->getStatus()]);

            return null;
        }

        // A failed lookup is not cached, so a Storyblok hiccup costs one retry per request, not a week.
        $this->cache->save((string) $id, $cacheKey, [], self::CACHE_LIFETIME);

        return (string) $id;
    }
}
