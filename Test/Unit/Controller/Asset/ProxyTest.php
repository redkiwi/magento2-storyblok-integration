<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Test\Unit\Controller\Asset;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use MediaLounge\Storyblok\Controller\Asset\Proxy;
use MediaLounge\Storyblok\Model\AssetProxy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProxyTest extends TestCase
{
    private RequestInterface|MockObject $request;
    private ResultFactory|MockObject $resultFactory;
    private AssetProxy|MockObject $assetProxy;
    private Proxy $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->resultFactory = $this->createMock(ResultFactory::class);
        $this->assetProxy = $this->createMock(AssetProxy::class);

        $this->controller = new Proxy(
            $this->request,
            $this->resultFactory,
            $this->assetProxy
        );
    }

    private function createRawResult(): Raw|MockObject
    {
        $result = $this->createMock(Raw::class);
        $result->method('setHttpResponseCode')->willReturnSelf();
        $result->method('setHeader')->willReturnSelf();
        $result->method('setContents')->willReturnSelf();
        return $result;
    }

    public function testMissingPathReturns404(): void
    {
        $this->request->method('getParam')->with('path')->willReturn(null);

        $result = $this->createRawResult();
        $result->expects($this->once())->method('setHttpResponseCode')->with(404);
        $this->resultFactory->method('create')->with(ResultFactory::TYPE_RAW)->willReturn($result);

        $this->controller->execute();
    }

    public function testInvalidPathReturns404(): void
    {
        $this->request->method('getParam')->with('path')->willReturn('aW52YWxpZA');
        $this->assetProxy->method('fetch')->with('aW52YWxpZA')->willReturn(null);

        $result = $this->createRawResult();
        $result->expects($this->once())->method('setHttpResponseCode')->with(404);
        $this->resultFactory->method('create')->with(ResultFactory::TYPE_RAW)->willReturn($result);

        $this->controller->execute();
    }

    public function testValidPathReturnsAsset(): void
    {
        $this->request->method('getParam')->with('path')->willReturn('encoded123');
        $this->assetProxy->method('fetch')->with('encoded123')->willReturn([
            'body' => 'asset-binary-data',
            'contentType' => 'image/jpeg',
            'cacheMaxAge' => 31536000,
        ]);

        $result = $this->createRawResult();
        $result->expects($this->once())->method('setHttpResponseCode')->with(200);
        $result->expects($this->once())->method('setContents')->with('asset-binary-data');
        $this->resultFactory->method('create')->with(ResultFactory::TYPE_RAW)->willReturn($result);

        $this->controller->execute();
    }
}
