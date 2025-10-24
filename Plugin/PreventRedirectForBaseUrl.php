<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Plugin;

use Magento\Store\Model\BaseUrlChecker;

class PreventRedirectForBaseUrl
{
    /**
     * @see BaseUrlChecker::execute()
     * @param string $uri
     * @param \Magento\Framework\App\Request\Http $request
     */
    public function afterExecute(
        BaseUrlChecker $subject,
        bool $result,
        $uri,
        $request
    ): bool {
        if ($request->getParam('_storyblok')) {
            return true;
        }

        return $result;
    }
}
