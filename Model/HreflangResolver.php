<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

/**
 * @phpstan-type HreflangEntry array{url: string, hreflang: string}
 * @phpstan-type StoreMapEntry array{base_url: string, hreflang: string, store: Store}
 * @phpstan-type Alternate array{published?: bool, full_slug?: string}
 * @phpstan-type TranslatedSlug array{lang?: string, path?: string}
 */
class HreflangResolver
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {}

    /**
     * Build hreflang entries from a story's alternates and/or translated slugs.
     *
     * @param array{alternates?: Alternate[], translated_slugs?: TranslatedSlug[], full_slug?: string, default_full_slug?: string, lang?: string} $story
     * @return HreflangEntry[]
     */
    public function resolve(array $story): array
    {
        $alternates = $story['alternates'] ?? [];
        $translatedSlugs = $this->buildTranslatedSlugsLookup($story['translated_slugs'] ?? []);

        if (!$alternates && !$translatedSlugs) {
            return [];
        }

        $currentApiKey = $this->config->apiKey();
        $prefixMap = $this->buildPrefixMap($currentApiKey);
        $languageMap = $this->buildLanguageMap($currentApiKey);

        $resolved = $this->resolveFromAlternates($alternates, $prefixMap);
        $resolved = $this->resolveFromTranslatedSlugs($translatedSlugs, $languageMap, $resolved);

        $hreflangs = array_values($resolved);

        $this->addSelfReferencing($hreflangs, $story, $prefixMap);
        $this->addXDefault($hreflangs, $story, $prefixMap);

        return $hreflangs;
    }

    /**
     * Resolve hreflangs from dimension alternates (Storyblok Dimensions app).
     * Matches the folder prefix from `full_slug` against store slug prefixes.
     *
     * @param Alternate[] $alternates
     * @param array<string, StoreMapEntry> $prefixMap
     * @param array<string, HreflangEntry> $resolved
     * @return array<string, HreflangEntry>
     */
    private function resolveFromAlternates(array $alternates, array $prefixMap, array $resolved = []): array
    {
        foreach ($alternates as $alternate) {
            if (!($alternate['published'] ?? false)) {
                continue;
            }

            $fullSlug = $alternate['full_slug'] ?? '';
            if (!$fullSlug) {
                continue;
            }

            $prefix = $this->extractPrefix($fullSlug);
            if (!$prefix || !isset($prefixMap[$prefix])) {
                continue;
            }

            $storeInfo = $prefixMap[$prefix];
            $slug = $this->stripDimensionPrefix($fullSlug, $prefix);

            $resolved[$prefix] = [
                'url' => rtrim($storeInfo['base_url'], '/') . '/' . ltrim($slug, '/'),
                'hreflang' => $storeInfo['hreflang'],
            ];
        }

        return $resolved;
    }

    /**
     * Resolve hreflangs from translated slugs (Storyblok Translatable Slugs app).
     * Matches the `lang` field against store language config.
     * Skips keys already present in resolved.
     *
     * @param array<string, string> $translatedSlugs language => path
     * @param array<string, StoreMapEntry> $languageMap
     * @param array<string, HreflangEntry> $resolved
     * @return array<string, HreflangEntry>
     */
    private function resolveFromTranslatedSlugs(array $translatedSlugs, array $languageMap, array $resolved = []): array
    {
        foreach ($translatedSlugs as $lang => $path) {
            if (isset($resolved[$lang]) || !isset($languageMap[$lang])) {
                continue;
            }

            $storeInfo = $languageMap[$lang];
            $slug = $this->stripDimensionPrefix($path, $lang);

            $resolved[$lang] = [
                'url' => rtrim($storeInfo['base_url'], '/') . '/' . ltrim($slug, '/'),
                'hreflang' => $storeInfo['hreflang'],
            ];
        }

        return $resolved;
    }

    /**
     * Index translated slugs by language for quick lookup.
     *
     * @param TranslatedSlug[] $translatedSlugs
     * @return array<string, string> language => path
     */
    private function buildTranslatedSlugsLookup(array $translatedSlugs): array
    {
        $byLang = [];
        foreach ($translatedSlugs as $entry) {
            $lang = $entry['lang'] ?? '';
            $path = $entry['path'] ?? '';
            if ($lang && $path) {
                $byLang[$lang] = $path;
            }
        }
        return $byLang;
    }

    /**
     * Collect stores keyed by slug prefix (for folder-based dimensions / alternates).
     * Only includes stores that have a slug prefix configured.
     *
     * @return array<string, StoreMapEntry>
     */
    private function buildPrefixMap(string $currentApiKey): array
    {
        $map = [];

        foreach ($this->storeManager->getStores() as $store) {
            $storeCode = $store->getCode();

            if ($this->config->apiKey($storeCode) !== $currentApiKey) {
                continue;
            }

            $prefix = $this->config->slugPrefix($storeCode);
            if (!$prefix) {
                continue;
            }

            $map[$prefix] = [
                'base_url' => $store->getBaseUrl(UrlInterface::URL_TYPE_LINK),
                'hreflang' => strtolower(str_replace('_', '-', $this->config->locale($storeCode))),
                'store' => $store,
            ];
        }

        return $map;
    }

    /**
     * Collect stores keyed by language (for field-level translations / translated slugs).
     *
     * @return array<string, StoreMapEntry>
     */
    private function buildLanguageMap(string $currentApiKey): array
    {
        $map = [];

        foreach ($this->storeManager->getStores() as $store) {
            $storeCode = $store->getCode();

            if ($this->config->apiKey($storeCode) !== $currentApiKey) {
                continue;
            }

            $language = $this->config->language($storeCode);
            $locale = $this->config->locale($storeCode);

            $map[$language] = [
                'base_url' => $store->getBaseUrl(UrlInterface::URL_TYPE_LINK),
                'hreflang' => strtolower(str_replace('_', '-', $locale)),
                'store' => $store,
            ];
        }

        return $map;
    }

    /**
     * Extract the first path segment as the folder dimension prefix.
     */
    private function extractPrefix(string $slug): string
    {
        $slug = ltrim($slug, '/');
        $slashPos = strpos($slug, '/');

        return $slashPos !== false ? substr($slug, 0, $slashPos) : '';
    }

    /**
     * Remove the dimension prefix since the store base URL already includes it.
     */
    private function stripDimensionPrefix(string $slug, string $prefix): string
    {
        $slug = ltrim($slug, '/');
        $prefix = $prefix . '/';

        if (str_starts_with($slug, $prefix)) {
            return substr($slug, strlen($prefix));
        }

        return $slug;
    }

    /**
     * Add an entry for the current page so search engines see the full set.
     *
     * @param HreflangEntry[] $hreflangs
     * @param array{full_slug?: string} $story
     * @param array<string, StoreMapEntry> $prefixMap
     */
    private function addSelfReferencing(array &$hreflangs, array $story, array $prefixMap): void
    {
        $fullSlug = $story['full_slug'] ?? '';
        $prefix = $this->extractPrefix($fullSlug) ?: $this->findCurrentStorePrefix($prefixMap);

        if (!$prefix || !isset($prefixMap[$prefix])) {
            return;
        }

        $storeInfo = $prefixMap[$prefix];
        $slug = $this->stripDimensionPrefix($fullSlug, $prefix);

        $url = rtrim($storeInfo['base_url'], '/') . '/' . ltrim($slug, '/');

        $hreflangs[] = [
            'url' => $url,
            'hreflang' => $storeInfo['hreflang'],
        ];
    }

    /**
     * Add the x-default entry pointing to the story's default language URL.
     *
     * @param HreflangEntry[] $hreflangs
     * @param array{default_full_slug?: string, full_slug?: string} $story
     * @param array<string, StoreMapEntry> $prefixMap
     */
    private function addXDefault(array &$hreflangs, array $story, array $prefixMap): void
    {
        $defaultFullSlug = $story['default_full_slug'] ?? ($story['full_slug'] ?? '');
        if (!$defaultFullSlug) {
            return;
        }

        $prefix = $this->extractPrefix($defaultFullSlug) ?: $this->findCurrentStorePrefix($prefixMap);

        if (!$prefix || !isset($prefixMap[$prefix])) {
            return;
        }

        $storeInfo = $prefixMap[$prefix];
        $slug = $this->stripDimensionPrefix($defaultFullSlug, $prefix);

        $url = rtrim($storeInfo['base_url'], '/') . '/' . ltrim($slug, '/');

        $hreflangs[] = [
            'url' => $url,
            'hreflang' => 'x-default',
        ];
    }

    /**
     * Find the slug prefix for the current store.
     *
     * @param array<string, StoreMapEntry> $prefixMap
     */
    private function findCurrentStorePrefix(array $prefixMap): string
    {
        $currentStoreId = $this->storeManager->getStore()->getId();

        foreach ($prefixMap as $prefix => $info) {
            if ((int)$info['store']->getId() === (int)$currentStoreId) {
                return $prefix;
            }
        }

        return array_key_first($prefixMap) ?? '';
    }
}
