<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\AssetInterface;
use Magento\Store\Model\StoreManagerInterface;

class FixAssetUrlForPreview
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager
    ) {}

    /**
     * @see AssetInterface::getUrl()
     * @param string $url
     * @param \Magento\Framework\App\Request\Http $request
     */
    public function afterGetUrl(
        AssetInterface $subject,
        string $url
    ): string {
        if ($this->request->getParam('_storyblok')) {
            $originalRequestHost = $this->request->getServer()->get('HTTP_HOST');
            $currentStoreHost = parse_url($this->storeManager->getStore()->getBaseUrl(), PHP_URL_HOST);
            if ($originalRequestHost !== $currentStoreHost) {
                $url = str_replace($currentStoreHost, $originalRequestHost, $url);
            }
        }

        return $url;
    }
}
