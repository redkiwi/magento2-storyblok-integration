<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

use Storyblok\ApiException;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class StoryRepository
{
    private const NOT_FOUND_CACHE_LIFETIME = 900;

    private \Storyblok\Client $storyblokClient;

    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly PrefixSlug $prefixSlug,
        private readonly LinkRepository $linkRepository,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly SpaceHashProvider $spaceHashProvider
    ) {
        $this->storyblokClient = $this->clientFactory->create();
    }

    public function getStoryBySlug(string $slug, bool $bypassCache = false, ?string $storeCode = null): array
    {
        $spaceHash = $this->spaceHashProvider->getHash();
        $slug = ($this->prefixSlug)($slug, $storeCode);
        $language = $this->config->language($storeCode);
        $identifier = "{$spaceHash}_{$slug}_{$language}";

        try {
            $data = null;

            if (!$bypassCache) {
                $data = $this->cache->load($identifier);
            }

            if (!$data || $bypassCache) {
                $data = $this->fetchStory($slug, $language, $identifier, $bypassCache);
            }

            $responseData = $this->serializer->unserialize($data);

            // Always populate LinkRepository with links from the response
            if (!empty($responseData['links'])) {
                $this->linkRepository->addLinks($responseData['links']);
            }

            return $responseData['story'] ?? [];
        } catch (ApiException $e) {
            $this->logger->info("Storyblok API error for slug: {$slug}", [
                'exception' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            if (!$bypassCache) {
                $this->saveNotFound($slug, $identifier);
            }

            return [];
        }
    }

    private function fetchStory(string $slug, string $language, string $identifier, bool $bypassCache): string
    {
        $this->storyblokClient->language($language);
        $response = $this->storyblokClient->getStoryBySlug($slug);
        $responseBody = $response->getBody();
        $data = (string)$this->serializer->serialize($responseBody);

        if (!$bypassCache && !empty($responseBody['story'])) {
            $this->cache->save($data, $identifier, [
                "storyblok_{$responseBody['story']['id']}"
            ]);
        } elseif (!$bypassCache) {
            $this->saveNotFound($slug, $identifier);
        }

        return $data;
    }

    private function saveNotFound(string $slug, string $identifier): void
    {
        $this->cache->save(
            (string)$this->serializer->serialize([]),
            $identifier,
            ["storyblok_slug_{$slug}"],
            self::NOT_FOUND_CACHE_LIFETIME
        );
    }
}
