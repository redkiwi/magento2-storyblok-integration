<?php

namespace MediaLounge\Storyblok\ViewModel;

use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MediaLounge\Storyblok\Model\Config;

class ProductSkuSlug implements ArgumentInterface
{
    public function __construct(
        private readonly Http $request,
        private readonly ProductRepository $productRepository,
        private readonly Config $config
    ) {}

    public function __toString(): string
    {;
        $url = "{$_SERVER['REQUEST_URI']}";

        if (
            $this->request->getControllerName() === 'product' &&
            $this->request->getParam('id')
        ) {
            $slug = $this->config->productListSlug();
            $slugPrefix = $this->config->slugPrefix();
            $product = $this->productRepository->getById($this->request->getParam('id'));
            $url = "{$slugPrefix}/{$slug}/{$product->getUrlKey()}";
        }

        return $url;
    }
}
