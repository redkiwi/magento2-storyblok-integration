<?php

namespace MediaLounge\Storyblok\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\App\RequestInterface;
use MediaLounge\Storyblok\Model\StoryRepository;

class Router implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly StoryRepository $storyRepository
    ) {}

    public function match(RequestInterface $request): ?ActionInterface
    {
        $identifier = trim($request->getPathInfo(), '/');
        $bypassCache = (bool)$request->getParam('_storyblok');
        $story = $this->storyRepository->getStoryBySlug($identifier, $bypassCache);

        if (!empty($story)) {
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
