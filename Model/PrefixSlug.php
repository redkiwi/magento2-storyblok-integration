<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class PrefixSlug implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config,
    ) {}

    public function __invoke(string $slug, ?string $storeCode = null): string
    {
        $prefix = $this->config->slugPrefix($storeCode);

        if ($prefix) {
            $slug = "{$prefix}/{$slug}";
        }

        return $slug;
    }
}
