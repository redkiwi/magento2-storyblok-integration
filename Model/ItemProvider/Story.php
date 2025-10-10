<?php

namespace MediaLounge\Storyblok\Model\ItemProvider;

use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Magento\Sitemap\Model\ItemProvider\ConfigReaderInterface;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use MediaLounge\Storyblok\Model\ClientFactory;

class Story implements ItemProviderInterface
{
    const STORIES_PER_PAGE = 100;

    public function __construct(
        private ConfigReaderInterface $configReader,
        private SitemapItemInterfaceFactory $itemFactory,
        private ClientFactory $clientFactory
    ) {}

    public function getItems($storeId)
    {
        $stories = [];
        $page = 1;

        do {
            $response = $this->getStories($page);
            $stories = array_merge($stories, $response->getBody()['stories']);
            $totalPages = ceil($response->getHeaders()['Total'][0] / self::STORIES_PER_PAGE);
        } while (++$page <= $totalPages);

        $items = array_map(
            fn($item) => $this->itemFactory->create([
                'url' => $item['full_slug'],
                'updatedAt' => $item['published_at'],
                'priority' => $this->configReader->getPriority($storeId),
                'changeFrequency' => $this->configReader->getChangeFrequency($storeId)
            ]),
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
