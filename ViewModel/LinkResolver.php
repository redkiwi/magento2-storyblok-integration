<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use MediaLounge\Storyblok\Model\LinkRepository;

class LinkResolver implements ArgumentInterface
{
    public function __construct(
        private readonly LinkRepository $linkRepository,
        private readonly StoreManagerInterface $storeManager
    ) {}

    public function getResolvedLink(array $link): string
    {
        // Handle direct URLs
        if (!empty($link['url'])) {
            return $link['url'];
        }

        // Handle story links with linktype
        if (!empty($link['linktype']) && $link['linktype'] === 'story' && !empty($link['id'])) {
            $resolvedLink = $this->linkRepository->getLinkByUuid($link['id']);
            if ($resolvedLink && !empty($resolvedLink['full_slug'])) {
                return $this->buildStoreUrl($resolvedLink['full_slug']);
            }
        }

        // Handle cached_url as fallback
        if (!empty($link['cached_url'])) {
            return $this->buildStoreUrl($link['cached_url']);
        }

        return '';
    }

    private function buildStoreUrl(string $slug): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $slug = ltrim($slug, '/');
        return rtrim($baseUrl, '/') . '/' . $slug;
    }
}