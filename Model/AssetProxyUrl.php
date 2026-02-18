<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

use Magento\Store\Model\StoreManagerInterface;

class AssetProxyUrl
{
    private const PROXY_ROUTE = 'storyblok/asset/proxy/path/';

    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {}

    public function rewriteUrl(string $storyblokUrl): string
    {
        if (!$this->config->isAssetProxyEnabled()) {
            return $storyblokUrl;
        }

        $path = $this->extractPath($storyblokUrl);
        if ($path === null) {
            return $storyblokUrl;
        }

        $encodedPath = $this->encodePath($path);
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();

        return rtrim($baseUrl, '/') . '/' . self::PROXY_ROUTE . $encodedPath;
    }

    public function rewriteHtml(string $html): string
    {
        if (!$this->config->isAssetProxyEnabled()) {
            return $html;
        }

        $hostPattern = $this->buildHostPattern();
        $baseUrl = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
        $self = $this;

        return (string)preg_replace_callback(
            '#(https?:)?//' . $hostPattern . '(/f/[^\s"\'<>]+)#',
            function (array $matches) use ($baseUrl, $self): string {
                $path = ltrim(end($matches), '/');
                $path = html_entity_decode($path, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $encodedPath = $self->encodePath($path);
                return $baseUrl . '/' . self::PROXY_ROUTE . $encodedPath;
            },
            $html
        );
    }

    public function isStoryblokAssetUrl(string $url): bool
    {
        $hostPattern = $this->buildHostPattern();

        return (bool)preg_match('#(https?:)?//' . $hostPattern . '/f/#', $url);
    }

    public function decodePath(string $encodedPath): ?string
    {
        $decoded = $this->decodeBin($encodedPath);
        if ($decoded === '' || !str_starts_with($decoded, 'f/')) {
            return null;
        }

        return $decoded;
    }

    public function encodePath(string $data): string
    {
        return bin2hex($data);
    }

    private function decodeBin(string $data): string
    {
        $decoded = @hex2bin($data);

        return $decoded !== false ? $decoded : '';
    }

    private function extractPath(string $url): ?string
    {
        $hostPattern = $this->buildHostPattern();
        if (preg_match('#(https?:)?//' . $hostPattern . '/(.+)$#', $url, $matches)) {
            return end($matches);
        }

        return null;
    }

    private function buildHostPattern(): string
    {
        $hosts = $this->config->assetHosts();
        $escaped = array_map(fn(string $h) => preg_quote($h, '#'), $hosts);

        return '(?:' . implode('|', $escaped) . ')';
    }
}
