<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use MediaLounge\Storyblok\Model\AssetProxyUrl;

class AssetProxy implements ArgumentInterface
{
    public function __construct(
        private readonly AssetProxyUrl $assetProxyUrl
    ) {}

    public function getProxiedUrl(string $storyblokUrl): string
    {
        return $this->assetProxyUrl->rewriteUrl($storyblokUrl);
    }
}
