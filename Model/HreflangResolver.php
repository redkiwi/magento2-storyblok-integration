<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

/**
 * @phpstan-type HreflangEntry array{url: string, hreflang: string}
 * @phpstan-type StoreMapEntry array{base_url: string, hreflang: string, store: Store}
 * @phpstan-type Alternate array{published?: bool, full_slug?: string, lang?: string}
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

        $storeMap = $this->buildStoreMap($this->config->apiKey());

        $resolved = $this->resolveFromAlternates($alternates, $storeMap);
        $resolved = $this->resolveFromTranslatedSlugs($translatedSlugs, $storeMap, $resolved);

        $hreflangs = array_values($resolved);

        $this->addSelfReferencing($hreflangs, $story, $storeMap);
        $this->addXDefault($hreflangs, $story, $storeMap);

        return $hreflangs;
    }

    /**
     * Resolve hreflangs from dimension alternates (Storyblok Dimensions app).
     *
     * @param Alternate[] $alternates
     * @param array<string, StoreMapEntry> $storeMap
     * @param array<string, HreflangEntry> $resolved
     * @return array<string, HreflangEntry>
     */
    private function resolveFromAlternates(array $alternates, array $storeMap, array $resolved = []): array
    {
        foreach ($alternates as $alternate) {
            if (!($alternate['published'] ?? false)) {
                continue;
            }

            $fullSlug = $alternate['full_slug'] ?? '';
            if (!$fullSlug) {
                continue;
            }

            $lang = $alternate['lang'] ?? '';
            if (!$lang || !isset($storeMap[$lang])) {
                continue;
            }

            $storeInfo = $storeMap[$lang];
            $slug = $this->stripDimensionPrefix($fullSlug, $lang);

            $resolved[$lang] = [
                'url' => rtrim($storeInfo['base_url'], '/') . '/' . ltrim($slug, '/'),
                'hreflang' => $storeInfo['hreflang'],
            ];
        }

        return $resolved;
    }

    /**
     * Resolve hreflangs from translated slugs (Storyblok Translatable Slugs app).
     * Skips languages already resolved by alternates.
     *
     * @param array<string, string> $translatedSlugs language => path
     * @param array<string, StoreMapEntry> $storeMap
     * @param array<string, HreflangEntry> $resolved
     * @return array<string, HreflangEntry>
     */
    private function resolveFromTranslatedSlugs(array $translatedSlugs, array $storeMap, array $resolved = []): array
    {
        foreach ($translatedSlugs as $lang => $path) {
            if (isset($resolved[$lang]) || !isset($storeMap[$lang])) {
                continue;
            }

            $storeInfo = $storeMap[$lang];
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
     * Collect stores belonging to the same Storyblok space, keyed by language.
     *
     * @return array<string, StoreMapEntry>
     */
    private function buildStoreMap(string $currentApiKey): array
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
     * Remove the language dimension prefix since the store base URL already includes it.
     */
    private function stripDimensionPrefix(string $slug, string $lang): string
    {
        $slug = ltrim($slug, '/');
        $prefix = $lang . '/';

        if (str_starts_with($slug, $prefix)) {
            return substr($slug, strlen($prefix));
        }

        return $slug;
    }

    /**
     * Add an entry for the current page so search engines see the full set.
     *
     * @param HreflangEntry[] $hreflangs
     * @param array{full_slug?: string, lang?: string} $story
     * @param array<string, StoreMapEntry> $storeMap
     */
    private function addSelfReferencing(array &$hreflangs, array $story, array $storeMap): void
    {
        $lang = $story['lang'] ?? 'default';
        if ($lang === 'default') {
            $lang = $this->findDefaultLanguage($storeMap);
        }

        if (!$lang || !isset($storeMap[$lang])) {
            return;
        }

        $storeInfo = $storeMap[$lang];
        $slug = $story['full_slug'] ?? '';
        $slug = $this->stripDimensionPrefix($slug, $lang);

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
     * @param array<string, StoreMapEntry> $storeMap
     */
    private function addXDefault(array &$hreflangs, array $story, array $storeMap): void
    {
        $defaultFullSlug = $story['default_full_slug'] ?? ($story['full_slug'] ?? '');
        if (!$defaultFullSlug) {
            return;
        }

        $defaultLang = $this->findDefaultLanguage($storeMap);
        if (!$defaultLang || !isset($storeMap[$defaultLang])) {
            return;
        }

        $storeInfo = $storeMap[$defaultLang];
        $slug = $this->stripDimensionPrefix($defaultFullSlug, $defaultLang);

        $url = rtrim($storeInfo['base_url'], '/') . '/' . ltrim($slug, '/');

        $hreflangs[] = [
            'url' => $url,
            'hreflang' => 'x-default',
        ];
    }

    /**
     * Find the language key that corresponds to the current store.
     *
     * @param array<string, StoreMapEntry> $storeMap
     */
    private function findDefaultLanguage(array $storeMap): string
    {
        $currentStoreId = $this->storeManager->getStore()->getId();

        foreach ($storeMap as $lang => $info) {
            if ((int)$info['store']->getId() === (int)$currentStoreId) {
                return $lang;
            }
        }

        return array_key_first($storeMap) ?? '';
    }
}
