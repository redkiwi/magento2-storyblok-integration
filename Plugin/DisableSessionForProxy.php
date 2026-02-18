<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Plugin;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Session\SessionStartChecker;

/**
 * Prevents session start for asset proxy routes to avoid session file locking.
 *
 * Without this, concurrent browser requests for proxy assets queue behind the
 * PHP session lock, causing HTTP 503 "Session concurrency exceeded" errors.
 *
 * @see \Magento\Paypal\Plugin\TransparentSessionChecker (same pattern)
 */
class DisableSessionForProxy
{
    public function __construct(
        private readonly Http $request
    ) {}

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCheck(SessionStartChecker $subject, bool $result): bool
    {
        if ($result === false) {
            return false;
        }

        if (str_contains((string)$this->request->getPathInfo(), 'storyblok/asset/proxy')) {
            return false;
        }

        return true;
    }
}
