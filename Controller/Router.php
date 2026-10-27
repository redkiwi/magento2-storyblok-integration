<?php

namespace MediaLounge\Storyblok\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\App\RequestInterface;
use MediaLounge\Storyblok\Model\Config;
use MediaLounge\Storyblok\Model\StoryRepository;

class Router implements RouterInterface
{
    private const FILE_PATH_PATTERN = '#\.(ico|png|jpe?g|gif|svg|webp|xml|txt|php|js|css|map|json)$#i';

    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly StoryRepository $storyRepository,
        private readonly Config $config
    ) {}

    public function match(RequestInterface $request): ?ActionInterface
    {
        $identifier = trim($request->getPathInfo(), '/');
        if (preg_match(self::FILE_PATH_PATTERN, $identifier)) {
            return null;
        }

        $bypassCache = (bool)$request->getParam('_storyblok');
        $story = $this->storyRepository->getStoryBySlug($identifier, $bypassCache);

        if (!empty($story)) {
            // Check if the content type is excluded
            $excludedTypes = $this->config->excludedContentTypes();
            if (
                !empty($story['content']['component']) &&
                in_array($story['content']['component'], $excludedTypes)
            ) {
                return null;
            }

            $request
                ->setModuleName('storyblok')
                ->setControllerName('index')
                ->setActionName('index')
                ->setParams([
                    'story' => $story
                ]);

            return $this->actionFactory->create(Forward::class, ['request' => $request]);
        }

        return null;
    }
}
