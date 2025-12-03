<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Plugin;

use Magento\Framework\View\Page\Config;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Layout;
use MediaLounge\Storyblok\Controller\Index\Index as StoryblokIndex;
use MediaLounge\Storyblok\Model\Config as StoryblokConfig;
use Storyblok\{Client, ClientFactory};

class AddHreflang
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ClientFactory $storyblokClientFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly StoryblokConfig $storyblokConfig
    ) {}

    /**
     * @see StoryblokIndex::execute()
     */
    public function afterExecute(
        StoryblokIndex $subject,
        ResultInterface $result
    ): ResultInterface {
        // Only apply to pages
        if (!$result instanceof Layout) {
            return $result;
        }

        $story = $subject->getRequest()->getParam('story');
        if (empty($story) || empty($story['content']['hreflangs'])) {
            return $result;
        }

        $hrefLangPages = $story['content']['hreflangs'];
        if (!is_array($hrefLangPages)) {
            return $result;
        }

        $storyblokClient = $this->getStoryblokClient();
        if (!$storyblokClient) {
            return $result;
        }

        /** @var Config $pageConfig */
        $pageConfig = $result->getConfig(); /** @phpstan-ignore-line */
        $baseUrl = trim($subject->getRequest()->getDistroBaseUrl(), '/'); /** @phpstan-ignore-line */
        foreach ($hrefLangPages as $hrefLangPage) {
            try {
                $relatedStory = $storyblokClient->getStoryByUuid($hrefLangPage);
                if ($relatedStory->getBody()) {
                    $this->prepareHreflang($relatedStory->getBody(), $pageConfig, $baseUrl);
                }
            } catch (\Exception $e) {}
        }

        return $result;
    }

    /**
     * @param mixed[]|\stdClass $relatedStory
     */
    private function prepareHreflang($relatedStory, Config $pageConfig, string $baseUrl): void
    {
        if (empty($relatedStory['story'])) {
            return;
        }
        $story = $relatedStory['story'];

        if (empty($story['published_at'])) {
            return;
        }
        $publishedAt = strtotime($story['published_at']);

        if ($publishedAt > strtotime('now')) {
            return;
        }

        if (empty($story['full_slug'])) {
            return;
        }
        $slug = trim($story['full_slug'], '/');

        if ($this->storyblokConfig->slugPrefix()) {
            $lang = explode('/', $slug)[0] ?? 'x-default';
        } else {
            $lang = 'x-default';
        }
        $lang = ($lang === 'be') ? 'nl-be' : $lang;

        $pageConfig->addRemotePageAsset(
            $baseUrl . '/' . $slug,
            'alternate',
            [
                'attributes' => [
                    'rel' => 'alternate',
                    'hreflang' => $lang
                ]
            ]
        );
    }

    private function getStoryblokClient(): ?Client
    {
        static $storyblokClient = null;

        if (null === $storyblokClient) {
            $storyblokClient = $this->storyblokClientFactory->create([
                'apiKey' => $this->scopeConfig->getValue(
                    'storyblok/general/api_key',
                    ScopeInterface::SCOPE_STORE,
                    $this->storeManager->getStore()->getId()
                )
            ]);
        }

        return $storyblokClient;
    }
}
