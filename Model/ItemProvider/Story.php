<?php

namespace MediaLounge\Storyblok\Model\ItemProvider;

use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Magento\Sitemap\Model\ItemProvider\ConfigReaderInterface;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use MediaLounge\Storyblok\Model\{ClientFactory, Config};

class Story implements ItemProviderInterface
{
    const STORIES_PER_PAGE = 100;

    public function __construct(
        private readonly ConfigReaderInterface $configReader,
        private readonly SitemapItemInterfaceFactory $itemFactory,
        private readonly ClientFactory $clientFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {}

    public function getItems($storeId)
    {
        $stories = [];
        $page = 1;

        do {
            $response = $this->getStories($page);
            $stories = array_merge($stories, $response->getBody()['stories']);
            $totalPages = ($response->getHeaders() && isset($response->getHeaders()['Total']))
                ? ceil($response->getHeaders()['Total'][0] / self::STORIES_PER_PAGE)
                : 1;
        } while (++$page <= $totalPages);

        $language = $this->config->language();
        $slugPrefix = $this->config->slugPrefix();

        // Exclude pages from other language directories
        if ($slugPrefix) {
            $stories = array_filter(
                $stories,
                function ($item) use ($slugPrefix) {
                    return !preg_match('#^/?[a-z]{2}/#i', $item['full_slug'])
                        || str_starts_with($item['full_slug'], $slugPrefix . '/');
                }
            );
        }

        // Filter out pages with no_index or no_follow
        $stories = array_filter($stories, function ($item) {
            $noIndex = array_key_exists('no_index', $item['content']) && $item['content']['no_index'];
            $noFollow = array_key_exists('no_follow', $item['content']) && $item['content']['no_follow'];

            return !($noIndex || $noFollow);
        });

        $items = array_map(
            function ($item) use ($storeId, $language, $slugPrefix) {
                $link = $item['full_slug'];
                if (!$slugPrefix && $language && str_starts_with($link, $language)) {
                    $link = substr($link, strlen($language) + 1);
                } elseif ($slugPrefix && str_starts_with($link, $slugPrefix)) {
                    $link = substr($link, strlen($slugPrefix) + 1);
                }

                return $this->itemFactory->create([
                    'url' => $link,
                    'updatedAt' => $item['published_at'],
                    'priority' => $this->configReader->getPriority($storeId),
                    'changeFrequency' => $this->configReader->getChangeFrequency($storeId),
                    'custom_label' => $item['custom_label'] ?? null,
                ]);
            },
            $stories
        );

        return $items;
    }

    private function getStories(int $page = 1): \Storyblok\Client
    {
        $response = $this->getClient()->getStories([
            'page' => $page,
            'per_page' => self::STORIES_PER_PAGE,
            'filter_query[component][like]' => 'page'
        ]);

        return $response;
    }

    private function getClient(): \Storyblok\Client
    {
        static $storyblokClient;

        if (!$storyblokClient) {
            $storyblokClient = $this->clientFactory->create();
        }

        return $storyblokClient;
    }
}
