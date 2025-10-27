<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Plugin;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Block\Html\Breadcrumbs;
use MediaLounge\Storyblok\Model\Config;

class StoryblokBreadcrumbs
{
    private const BREAK_SEGMENTS = ['referer'];

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        public ?array $excludedSegments = []
    ) {}

    public function afterToHtml(
        Breadcrumbs $original,
        $html
    ): string {
        if (!$this->config->showBreadcrumbs()) {
            return $html;
        }

        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $path = trim($original->getRequest()->getRequestUri(), '/');
        $segments = explode('/', $path);

        if (empty(trim($html))  && !empty($path)) {

            $crumb = [
                'label' => __('Home'),
                'link' => $baseUrl,
                'first' => true,
                'last' => false,
                'readonly' => false,
            ];

            $original->addCrumb('home', $crumb);

            if ($this->config->slugPrefix() && str_starts_with($path, $this->config->slugPrefix())) {
                array_shift($segments);
            }

            $uri = '';
            foreach ($segments as $segment) {
                $crumb['first'] = false;
                $crumb['last'] = false;
                $crumb['readonly'] = false;

                $uri .= (empty($uri) ? '' : '/') . $segment;
                $crumb['link'] = $baseUrl . $uri;
                $label = explode('?', $segment);
                $crumb['label'] = str_replace('-', ' ', ucfirst($label[0]));

                if ($segment === end($segments)) {
                    $crumb['last'] = true;
                    $crumb['readonly'] = true;
                    $crumb['link'] = null;
                }

                // Remove segment from breadcrumbs
                if ($this->excludedSegments && in_array($segment, $this->excludedSegments)) {
                    continue;
                }

                // Remove segment and any following segments from breadcrumbs
                if (in_array($segment, self::BREAK_SEGMENTS)) {
                    break;
                }

                $original->addCrumb($segment, $crumb);
            }

            $html = $original->toHtml();
        }

        return $html;
    }
}
