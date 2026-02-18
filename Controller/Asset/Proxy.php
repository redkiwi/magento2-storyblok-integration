<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Controller\Asset;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use MediaLounge\Storyblok\Model\AssetProxy;

class Proxy implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly AssetProxy $assetProxy
    ) {}

    public function execute(): ResultInterface
    {
        $encodedPath = $this->request->getParam('path');
        $asset = $encodedPath ? $this->assetProxy->fetch($encodedPath) : null;

        if ($asset === null) {
            return $this->create404();
        }

        /** @var \Magento\Framework\Controller\Result\Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHttpResponseCode(200);
        $result->setHeader('Content-Type', $asset['contentType'] ?: 'application/octet-stream');
        $result->setHeader('Cache-Control', 'public, max-age=' . $asset['cacheMaxAge']);
        $result->setHeader('X-Storyblok-Proxy', '1');
        $result->setContents($asset['body']);

        return $result;
    }

    private function create404(): ResultInterface
    {
        /** @var \Magento\Framework\Controller\Result\Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHttpResponseCode(404);
        $result->setContents('');

        return $result;
    }
}
