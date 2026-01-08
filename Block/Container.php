<?php

namespace MediaLounge\Storyblok\Block;

use Magento\Framework\View\FileSystem;
use Magento\Framework\View\Element\AbstractBlock;
use MediaLounge\Storyblok\Block\Container\Element;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template\Context;
use MediaLounge\Storyblok\Model\StoryRepository;
use MediaLounge\Storyblok\Model\SpaceHashProvider;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\State;

class Container extends \Magento\Framework\View\Element\Template implements IdentityInterface
{
    public function __construct(
        private readonly FileSystem $viewFileSystem,
        private readonly StoryRepository $storyRepository,
        private readonly LoggerInterface $logger,
        private readonly State $appState,
        private readonly SpaceHashProvider $spaceHashProvider,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCacheLifetime()
    {
        return parent::getCacheLifetime() ?: 3600;
    }

    public function getIdentities(): array
    {
        if (!empty($this->getSlug())) {
            return ["storyblok_slug_{$this->getSlug()}"];
        } elseif (!empty($this->getData('story')['id'])) {
            return ["storyblok_{$this->getData('story')['id']}"];
        }

        return [];
    }

    public function getCacheKeyInfo(): array
    {
        $info = parent::getCacheKeyInfo();
        $info[] = $this->spaceHashProvider->getHash();

        if (!empty($this->getData('story')['id'])) {
            $info[] = "storyblok_{$this->getData('story')['id']}";
        } elseif (!empty($this->getSlug())) {
            $info[] = "storyblok_slug_{$this->getSlug()}";
        }

        return $info;
    }

    private function getStory(): array
    {
        if (!$this->getData('story')) {
            $slug = $this->getSlug();
            $story = $this->storyRepository->getStoryBySlug($slug);
            $this->setData('story', $story);
        }

        return $this->getData('story');
    }

    private function isArrayOfBlocks(array $data): bool
    {
        return count($data) !== count($data, COUNT_RECURSIVE);
    }

    private function createBlockFromData(array $blockData): ?Element
    {
        $block = $this->getLayout()
            ->createBlock(
                Element::class,
                $this->getNameInLayout()
                    ? $this->getNameInLayout() . '_' . $blockData['_uid']
                    : $blockData['_uid']
            )
            ->setData($blockData);

        $templateName = "MediaLounge_Storyblok::story/{$blockData['component']}.phtml";
        $templatePath = $this->viewFileSystem->getTemplateFileName($templateName);

        if ($templatePath) {
            $block->setTemplate($templateName);
        } else {
            $this->logger->warning(
                'Storyblok template missing',
                ['template' => $templateName]
            );

            if ($this->appState->getMode() !== State::MODE_PRODUCTION) {
                $block->setTemplate('MediaLounge_Storyblok::story/debug.phtml')->addData([
                    'original_template' => $templateName
                ]);
            } else {
                return null;
            }
        }

        $this->appendChildBlocks($block, $blockData);

        return $block;
    }

    private function appendChildBlocks(AbstractBlock $parentBlock, array $blockData): void
    {
        foreach ($blockData as $data) {
            if (is_array($data) && $this->isArrayOfBlocks($data)) {
                foreach ($data as $childData) {
                    // Ignore if rich text editor block
                    if (empty($childData['_uid'])) {
                        continue;
                    }

                    $childBlock = $this->createBlockFromData($childData);

                    if ($childBlock !== null) {
                        $parentBlock->append($childBlock);
                    }
                }
            }
        }
    }

    protected function _toHtml(): string
    {
        $storyData = $this->getStory();

        if ($storyData) {
            $blockData = $storyData['content'] ?? [];
            $parentBlock = $this->createBlockFromData($blockData);

            if ($parentBlock !== null) {
                return $parentBlock->toHtml();
            }
        }

        return '';
    }
}
