<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\HeaderProvider\XFrameOptions as XFrameOptionsHeaders;

class XFrameOptions
{
    const VALUE = 'storyblok.com';

    public function __construct(
        private readonly RequestInterface $request
    ) {}

    /**
     * @param XFrameOptionsHeaders $subject
     * @param string $value
     * @return string
     */
    public function afterGetValue(
        XFrameOptionsHeaders $subject,
        $value
    ) {
        if ($this->request->getParam('_storyblok')) {
            return self::VALUE;
        }

        return $value;
    }
}
