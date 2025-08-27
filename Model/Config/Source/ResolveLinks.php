<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ResolveLinks implements OptionSourceInterface
{
    /**
     * Get options for resolve_links configuration
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('Disabled')],
            ['value' => 'url', 'label' => __('URL (Recommended for multilingual sites)')],
            ['value' => 'link', 'label' => __('Link (Additional metadata)')],
            ['value' => 'story', 'label' => __('Story (Full story content)')],
        ];
    }
}