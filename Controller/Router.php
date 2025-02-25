<?php

namespace MediaLounge\Storyblok\Controller;

use Storyblok\ApiException;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\SerializerInterface;
use MediaLounge\Storyblok\Model\{ClientFactory, PrefixSlug};

class Router implements RouterInterface
{
    /**
     * @var ActionFactory
     */
    private $actionFactory;

    /**
     * @var \Storyblok\Client
     */
    private $storyblokClient;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var PrefixSlug
     */
    private $prefixSlug;

    public function __construct(
        ActionFactory $actionFactory,
        ClientFactory $clientFactory,
        CacheInterface $cache,
        SerializerInterface $serializer,
        PrefixSlug $prefixSlug
    ) {
        $this->actionFactory = $actionFactory;
        $this->storyblokClient = $clientFactory->create();
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->prefixSlug = $prefixSlug;
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        $identifier = trim($request->getPathInfo(), '/');
        $identifier = ($this->prefixSlug)($identifier);

        try {
            $data = $this->cache->load($identifier);

            if (!$data || $request->getParam('_storyblok')) {
                $response = $this->storyblokClient->getStoryBySlug($identifier);
                $data = $this->serializer->serialize($response->getBody());

                if (!$request->getParam('_storyblok') && !empty($response->getBody()['story'])) {
                    $this->cache->save($data, $identifier, [
                        "storyblok_{$response->getBody()['story']['id']}"
                    ]);
                }
            }

            $data = $this->serializer->unserialize($data);

            if (!empty($data['story'])) {
                $request
                    ->setModuleName('storyblok')
                    ->setControllerName('index')
                    ->setActionName('index')
                    ->setParams([
                        'story' => $data['story']
                    ]);

                return $this->actionFactory->create(Forward::class, ['request' => $request]);
            }
        } catch (ApiException $e) {
            return null;
        }

        return null;
    }
}
