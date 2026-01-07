<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Block\Widget;

use Magento\Framework\App\State;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\FileSystem;
use Magento\Widget\Block\BlockInterface;
use MediaLounge\Storyblok\Block\Container;
use MediaLounge\Storyblok\Model\Config;
use MediaLounge\Storyblok\Model\SpaceHashProvider;
use MediaLounge\Storyblok\Model\StoryRepository;
use Psr\Log\LoggerInterface;

class Storyblok extends Container implements BlockInterface
{

    public function __construct(
        FileSystem $viewFileSystem,
        StoryRepository $storyRepository,
        LoggerInterface $logger,
        State $appState,
        SpaceHashProvider $spaceHashProvider,
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($viewFileSystem, $storyRepository, $logger, $appState, $spaceHashProvider, $context, $data);
    }

    public function getSlug(): string
    {
        $slug = $this->getData('slug');

        if (!$slug) {
            $uriParts = explode(
                '/',
                trim(explode('?', $this->getRequest()->getRequestUri() ?? '')[0], '/')
            );
            $slug = array_pop($uriParts);
            if ($slugPrefix = $this->config->slugPrefix()) {
                $slug = $slugPrefix . '/' . $slug;
            }
        }

        return trim($slug, '/');
    }
}
