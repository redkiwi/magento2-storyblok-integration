<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Plugin;

use Magento\Framework\View\Page\Config;
use MediaLounge\Storyblok\Controller\Index\Index as StoryblokIndex;

class StoryblokRobotsTag
{
    public function __construct(
        private readonly Config $pageConfig
    ) {}

    /**
     * @param mixed ...$args
     * @return mixed[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function beforeExecute(
        StoryblokIndex $subject,
        ...$args
    ) {
        $currentRobots = $this->pageConfig->getRobots();

        $story = $subject->getRequest()->getParam('story');
        $newRobots = $this->determineRobotsValue($story);
        if ($newRobots !== $currentRobots) {
            $this->pageConfig->setRobots($newRobots);
        }

        return [$args];
    }

    private function determineRobotsValue(array $story): string
    {
        $index = (array_key_exists('no_index', $story['content']) && $story['content']['no_index'])
            ? 'NOINDEX'
            : 'INDEX';
        $follow = (array_key_exists('no_follow', $story['content']) && $story['content']['no_follow'])
            ? 'NOFOLLOW'
            : 'FOLLOW';

        return "$index,$follow";
    }
}
