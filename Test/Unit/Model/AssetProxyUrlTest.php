<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Test\Unit\Model;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MediaLounge\Storyblok\Model\AssetProxyUrl;
use MediaLounge\Storyblok\Model\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AssetProxyUrlTest extends TestCase
{
    private Config|MockObject $config;
    private StoreManagerInterface|MockObject $storeManager;
    private AssetProxyUrl $assetProxyUrl;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://www.example.com/');
        $this->storeManager->method('getStore')->willReturn($store);

        $this->config->method('assetHosts')->willReturn(['a.storyblok.com']);

        $this->assetProxyUrl = new AssetProxyUrl($this->config, $this->storeManager);
    }

    public function testRewriteUrlWhenEnabled(): void
    {
        $this->config->method('isAssetProxyEnabled')->willReturn(true);

        $url = 'https://a.storyblok.com/f/12345/600x400/abc123/image.jpg';
        $result = $this->assetProxyUrl->rewriteUrl($url);

        $this->assertStringStartsWith('https://www.example.com/storyblok/asset/proxy/path/', $result);
        $this->assertStringNotContainsString('a.storyblok.com', $result);
    }

    public function testRewriteUrlWhenDisabled(): void
    {
        $this->config->method('isAssetProxyEnabled')->willReturn(false);

        $url = 'https://a.storyblok.com/f/12345/600x400/abc123/image.jpg';
        $result = $this->assetProxyUrl->rewriteUrl($url);

        $this->assertSame($url, $result);
    }

    public function testRewriteUrlNonStoryblok(): void
    {
        $this->config->method('isAssetProxyEnabled')->willReturn(true);

        $url = 'https://cdn.example.com/images/photo.jpg';
        $result = $this->assetProxyUrl->rewriteUrl($url);

        $this->assertSame($url, $result);
    }

    public function testRewriteHtmlWhenEnabled(): void
    {
        $this->config->method('isAssetProxyEnabled')->willReturn(true);

        $html = '<img src="https://a.storyblok.com/f/12345/image.jpg" />';
        $result = $this->assetProxyUrl->rewriteHtml($html);

        $this->assertStringContainsString('storyblok/asset/proxy/path/', $result);
        $this->assertStringNotContainsString('a.storyblok.com', $result);
    }

    public function testRewriteHtmlWhenDisabled(): void
    {
        $this->config->method('isAssetProxyEnabled')->willReturn(false);

        $html = '<img src="https://a.storyblok.com/f/12345/image.jpg" />';
        $result = $this->assetProxyUrl->rewriteHtml($html);

        $this->assertSame($html, $result);
    }

    public function testRewriteHtmlWithProtocolRelativeUrl(): void
    {
        $this->config->method('isAssetProxyEnabled')->willReturn(true);

        $html = '<img src="//a.storyblok.com/f/12345/image.jpg" />';
        $result = $this->assetProxyUrl->rewriteHtml($html);

        $this->assertStringContainsString('storyblok/asset/proxy/path/', $result);
        $this->assertStringNotContainsString('a.storyblok.com', $result);
    }

    public function testIsStoryblokAssetUrl(): void
    {
        $this->assertTrue($this->assetProxyUrl->isStoryblokAssetUrl('https://a.storyblok.com/f/12345/image.jpg'));
        $this->assertTrue($this->assetProxyUrl->isStoryblokAssetUrl('//a.storyblok.com/f/12345/image.jpg'));
        $this->assertFalse($this->assetProxyUrl->isStoryblokAssetUrl('https://cdn.example.com/image.jpg'));
    }

    public function testDecodePathValid(): void
    {
        $originalPath = 'f/12345/600x400/abc123/image.jpg';
        $encoded = $this->assetProxyUrl->encodePath($originalPath);

        $result = $this->assetProxyUrl->decodePath($encoded);

        $this->assertSame($originalPath, $result);
    }

    public function testDecodePathInvalid(): void
    {
        $invalidPath = 'not-a-valid-path';
        $encoded = $this->assetProxyUrl->encodePath($invalidPath);

        $result = $this->assetProxyUrl->decodePath($encoded);

        $this->assertNull($result);
    }

    public function testRoundTrip(): void
    {
        $this->config->method('isAssetProxyEnabled')->willReturn(true);

        $originalUrl = 'https://a.storyblok.com/f/12345/document.pdf';
        $proxyUrl = $this->assetProxyUrl->rewriteUrl($originalUrl);

        $encodedPath = substr($proxyUrl, strrpos($proxyUrl, '/') + 1);
        $decodedPath = $this->assetProxyUrl->decodePath($encodedPath);

        $this->assertSame('f/12345/document.pdf', $decodedPath);
    }

    public function testRoundTripWithTransformationParams(): void
    {
        $this->config->method('isAssetProxyEnabled')->willReturn(true);

        $originalUrl = 'https://a.storyblok.com/f/321820/5504x8256/f3eb5ad2cb/image.jpg/m/360x460/filters:focal(2758x4921:2759x4922)';
        $proxyUrl = $this->assetProxyUrl->rewriteUrl($originalUrl);

        $encodedPath = substr($proxyUrl, strrpos($proxyUrl, '/') + 1);
        $decodedPath = $this->assetProxyUrl->decodePath($encodedPath);

        $this->assertSame(
            'f/321820/5504x8256/f3eb5ad2cb/image.jpg/m/360x460/filters:focal(2758x4921:2759x4922)',
            $decodedPath
        );
    }

    public function testRewriteHtmlWithHtmlEncodedSlashes(): void
    {
        $this->config->method('isAssetProxyEnabled')->willReturn(true);

        $html = '<img src="https://a.storyblok.com/f/321820/108x72/da8922ca9c/image.png&#x2F;m&#x2F;108x72/filters:format(webp)" />';
        $result = $this->assetProxyUrl->rewriteHtml($html);

        $this->assertStringContainsString('storyblok/asset/proxy/path/', $result);
        $this->assertStringNotContainsString('a.storyblok.com', $result);
    }

    public function testRewriteHtmlDecodesAllEntitiesInCapturedPath(): void
    {
        $this->config->method('isAssetProxyEnabled')->willReturn(true);

        $html = '<source srcset="https://a.storyblok.com/f/321820/5504x8256/f3eb5ad2cb/image.jpg&#x2F;m&#x2F;360x460/filters&#x3A;focal&#x28;2758x4921&#x3A;2759x4922&#x29;&#x3A;format&#x28;webp&#x29;" />';
        $result = $this->assetProxyUrl->rewriteHtml($html);

        $this->assertStringContainsString('storyblok/asset/proxy/path/', $result);
        $this->assertStringNotContainsString('a.storyblok.com', $result);

        preg_match('#storyblok/asset/proxy/path/([^\s"]+)#', $result, $matches);
        $decoded = $this->assetProxyUrl->decodePath($matches[1]);

        $this->assertSame(
            'f/321820/5504x8256/f3eb5ad2cb/image.jpg/m/360x460/filters:focal(2758x4921:2759x4922):format(webp)',
            $decoded
        );
    }

    public function testMultiHostRewriteUrl(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('assetHosts')->willReturn(['a.storyblok.com', 'a2.storyblok.com']);
        $config->method('isAssetProxyEnabled')->willReturn(true);

        $proxyUrl = new AssetProxyUrl($config, $this->storeManager);

        $url1 = 'https://a.storyblok.com/f/12345/image.jpg';
        $url2 = 'https://a2.storyblok.com/f/12345/image.jpg';

        $result1 = $proxyUrl->rewriteUrl($url1);
        $result2 = $proxyUrl->rewriteUrl($url2);

        $this->assertStringContainsString('storyblok/asset/proxy/path/', $result1);
        $this->assertStringContainsString('storyblok/asset/proxy/path/', $result2);
    }

    public function testMultiHostRewriteHtml(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('assetHosts')->willReturn(['a.storyblok.com', 'a2.storyblok.com']);
        $config->method('isAssetProxyEnabled')->willReturn(true);

        $proxyUrl = new AssetProxyUrl($config, $this->storeManager);

        $html = '<img src="https://a.storyblok.com/f/1/img.jpg" /><img src="https://a2.storyblok.com/f/2/img.jpg" />';
        $result = $proxyUrl->rewriteHtml($html);

        $this->assertStringNotContainsString('a.storyblok.com', $result);
        $this->assertStringNotContainsString('a2.storyblok.com', $result);
        $this->assertSame(2, substr_count($result, 'storyblok/asset/proxy/path/'));
    }

    public function testMultiHostIsStoryblokAssetUrl(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('assetHosts')->willReturn(['a.storyblok.com', 'a-us.storyblok.com']);

        $proxyUrl = new AssetProxyUrl($config, $this->storeManager);

        $this->assertTrue($proxyUrl->isStoryblokAssetUrl('https://a.storyblok.com/f/123/file.pdf'));
        $this->assertTrue($proxyUrl->isStoryblokAssetUrl('https://a-us.storyblok.com/f/123/file.pdf'));
        $this->assertFalse($proxyUrl->isStoryblokAssetUrl('https://a-ap.storyblok.com/f/123/file.pdf'));
    }

    public function testNonConfiguredHostNotRewritten(): void
    {
        $this->config->method('isAssetProxyEnabled')->willReturn(true);

        $url = 'https://a-us.storyblok.com/f/12345/image.jpg';
        $result = $this->assetProxyUrl->rewriteUrl($url);

        $this->assertSame($url, $result);
    }
}
