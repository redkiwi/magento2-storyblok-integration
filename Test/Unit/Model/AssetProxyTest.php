<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Test\Unit\Model;

use Magento\Framework\HTTP\Client\Curl;
use MediaLounge\Storyblok\Model\AssetProxy;
use MediaLounge\Storyblok\Model\AssetProxyUrl;
use MediaLounge\Storyblok\Model\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AssetProxyTest extends TestCase
{
    private Config|MockObject $config;
    private AssetProxyUrl|MockObject $assetProxyUrl;
    private Curl|MockObject $curl;
    private LoggerInterface|MockObject $logger;
    private AssetProxy $assetProxy;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->assetProxyUrl = $this->createMock(AssetProxyUrl::class);
        $this->curl = $this->createMock(Curl::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->config->method('primaryAssetHost')->willReturn('a.storyblok.com');

        $this->assetProxy = new AssetProxy($this->config, $this->assetProxyUrl, $this->curl, $this->logger);
    }

    public function testFetchValidPath(): void
    {
        $this->assetProxyUrl->method('decodePath')
            ->with('662f31323334352f696d6167652e6a7067')
            ->willReturn('f/12345/image.jpg');

        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('binary-data');
        $this->curl->method('getHeaders')->willReturn(['Content-Type' => 'image/jpeg']);

        $result = $this->assetProxy->fetch('662f31323334352f696d6167652e6a7067');

        $this->assertNotNull($result);
        $this->assertSame('binary-data', $result['body']);
        $this->assertSame('image/jpeg', $result['contentType']);
        $this->assertSame(31536000, $result['cacheMaxAge']);
    }

    public function testFetchInvalidPathReturnsNull(): void
    {
        $this->assetProxyUrl->method('decodePath')
            ->with('invalidhex')
            ->willReturn(null);

        $result = $this->assetProxy->fetch('invalidhex');

        $this->assertNull($result);
    }

    public function testFetchRemoteErrorReturnsNullAndLogsError(): void
    {
        $this->assetProxyUrl->method('decodePath')
            ->with('662f31323334352f696d6167652e6a7067')
            ->willReturn('f/12345/image.jpg');

        $this->curl->method('getStatus')->willReturn(404);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('[Storyblok Asset Proxy Error] Storyblok responded with none 200 response: ', [
                'url' => 'https://a.storyblok.com/f/12345/image.jpg',
                'status' => 404,
            ]);

        $result = $this->assetProxy->fetch('662f31323334352f696d6167652e6a7067');

        $this->assertNull($result);
    }

    public function testFetchCurlExceptionReturnsNullAndLogsError(): void
    {
        $this->assetProxyUrl->method('decodePath')
            ->with('662f31323334352f696d6167652e6a7067')
            ->willReturn('f/12345/image.jpg');

        $this->curl->method('get')->willThrowException(new \Exception('Connection timeout'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('[Storyblok Asset Proxy Error] Error during request', [
                'url' => 'https://a.storyblok.com/f/12345/image.jpg',
                'exception' => 'Connection timeout',
            ]);

        $result = $this->assetProxy->fetch('662f31323334352f696d6167652e6a7067');

        $this->assertNull($result);
    }

    public function testFetchUsesPrimaryHost(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('primaryAssetHost')->willReturn('a-us.storyblok.com');

        $assetProxyUrl = $this->createMock(AssetProxyUrl::class);
        $assetProxyUrl->method('decodePath')->willReturn('f/123/file.pdf');

        $curl = $this->createMock(Curl::class);
        $curl->expects($this->once())
            ->method('get')
            ->with('https://a-us.storyblok.com/f/123/file.pdf');
        $curl->method('getStatus')->willReturn(200);
        $curl->method('getBody')->willReturn('pdf-data');
        $curl->method('getHeaders')->willReturn(['Content-Type' => 'application/pdf']);

        $proxy = new AssetProxy($config, $assetProxyUrl, $curl, $this->logger);
        $result = $proxy->fetch('encoded');

        $this->assertSame('application/pdf', $result['contentType']);
    }
}
