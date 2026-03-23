<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Controller\Index;

use Magento\Framework\View\Result\Page;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\App\Action\HttpGetActionInterface;
use MediaLounge\Storyblok\Model\Config;
use MediaLounge\Storyblok\Model\HreflangResolver;

class Index extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly Config $config,
        private readonly HreflangResolver $hreflangResolver
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $story = $this->getRequest()->getParam('story', null);

        if (!$story) {
            throw new NotFoundException(__('Story parameter is missing.'));
        }

        /** @var Page $resultPage */
        $resultPage = $this->pageFactory->create();
        $resultPage = $this->setMetaFields($resultPage, $story);
        $resultPage = $this->addHreflangLinks($resultPage, $story);

        $resultPage
            ->getLayout()
            ->getBlock('storyblok.page')
            ->setStory($story);

        return $resultPage;
    }

    private function setMetaFields(Page $resultPage, array $story): Page
    {
        $metaTitle = '';
        $metaDescription = '';

        foreach ($story['content'] as $data) {
            if (is_array($data) && $this->isMetaFieldsBlock($data)) {
                $metaTitle = $data['title'];
                $metaDescription = $data['description'];
            }
        }

        if ($metaTitle) {
            $resultPage
                ->getConfig()
                ->getTitle()
                ->set($metaTitle);
        } else {
            $resultPage
                ->getConfig()
                ->getTitle()
                ->set($story['name']);
        }

        if ($metaDescription) {
            $resultPage->getConfig()->setDescription($metaDescription);
        }

        $resultPage = $this->addCanonicalLink($resultPage, $story);

        return $resultPage;
    }

    private function addHreflangLinks(Page $resultPage, array $story): Page
    {
        if (!$this->config->addHreflang()) {
            return $resultPage;
        }

        $hreflangs = $this->hreflangResolver->resolve($story);

        foreach ($hreflangs as $hreflang) {
            $resultPage->getConfig()->addRemotePageAsset(
                $hreflang['url'],
                'alternate',
                [
                    'attributes' => [
                        'rel' => 'alternate',
                        'hreflang' => $hreflang['hreflang'],
                    ],
                ]
            );
        }

        return $resultPage;
    }

    private function isMetaFieldsBlock(array $data): bool
    {
        return !empty($data['plugin']) && $data['plugin'] === 'meta-fields';
    }

    private function addCanonicalLink(Page $resultPage, array $story): Page
    {
        if (!$this->config->addCanonical($resultPage, $story)) {
            return $resultPage;
        }

        $baseUrl = trim($this->getRequest()->getDistroBaseUrl(), '/');
        $slug = trim($this->getRequest()->getPathInfo(), '/');

        $canonicalUrl = $baseUrl . '/' . $slug;
        if (!empty($story['content']['metadata_canonical'])) {
            $storyMetaCanonicalUrl = $story['content']['metadata_canonical'];
            if (!str_contains($storyMetaCanonicalUrl, 'http')) {
                $canonicalUrl = $baseUrl . '/' . trim($storyMetaCanonicalUrl, '/');
            } else {
                $canonicalUrl = trim($storyMetaCanonicalUrl, '/');
            }
        }

        $resultPage->getConfig()->addRemotePageAsset(
            $canonicalUrl,
            'canonical',
            [
                'attributes' => [
                    'rel' => 'canonical'
                ]
            ]
        );

        return $resultPage;
    }
}
