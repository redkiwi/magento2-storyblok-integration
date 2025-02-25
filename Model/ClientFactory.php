<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\{ScopeInterface, StoreManagerInterface};

class ClientFactory
{
    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var StoreManagerInteface */
    private $storeManager;

    /** @var \Storyblock\ClientFactory */
    private $clientFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        \Storyblok\ClientFactory $clientFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->clientFactory = $clientFactory;
    }

    public function create(array $data = []): \Storyblok\Client
    {
        $data = array_merge(
            [
                'apiKey' => $this->scopeConfig->getValue(
                    'storyblok/general/api_key',
                    ScopeInterface::SCOPE_STORE,
                    $this->storeManager->getStore()->getId()
                )
            ],
            $data
        );
        $client = $this->clientFactory->create($data);

        return $client;
    }
}
