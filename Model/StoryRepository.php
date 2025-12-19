<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

use Storyblok\ApiException;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class StoryRepository
{
    private \Storyblok\Client $storyblokClient;

    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly PrefixSlug $prefixSlug,
        private readonly LinkRepository $linkRepository,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
        $this->storyblokClient = $this->clientFactory->create();
    }

    public function getStoryBySlug(string $slug, bool $bypassCache = false): array
    {
        $identifier = ($this->prefixSlug)($slug);

        try {
            $data = null;
            $language = $this->config->language();

            if (!$bypassCache) {
                $data = $this->cache->load("{$identifier}_{$language}");
            }

            if (!$data || $bypassCache) {
                $this->storyblokClient->language($language);
                $response = $this->storyblokClient->getStoryBySlug($identifier);
                $responseBody = $response->getBody();
                $data = $this->serializer->serialize($responseBody);

                if (!$bypassCache && !empty($responseBody['story'])) {
                    $this->cache->save($data, "{$identifier}_{$language}", [
                        "storyblok_{$responseBody['story']['id']}"
                    ]);
                }
            }

            $responseData = $this->serializer->unserialize($data);

            // Always populate LinkRepository with links from the response
            if (!empty($responseData['links'])) {
                $this->linkRepository->addLinks($responseData['links']);
            }

            return $responseData['story'] ?? [];
        } catch (ApiException $e) {
            return [];
        }
    }
}
