<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MediaLounge\Storyblok\Model\Config;
use MediaLounge\Storyblok\Model\HreflangResolver;

class HreflangResolverTest extends TestCase
{
    private HreflangResolver $resolver;
    private StoreManagerInterface&MockObject $storeManager;
    private Config&MockObject $config;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->config = $this->createMock(Config::class);

        $currentStore = $this->createMock(Store::class);
        $currentStore->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($currentStore);

        $this->resolver = new HreflangResolver(
            $this->storeManager,
            $this->config
        );
    }

    public function testNoAlternatesReturnsEmpty(): void
    {
        $story = ['alternates' => [], 'full_slug' => 'home'];

        $this->assertSame([], $this->resolver->resolve($story));
    }

    public function testUnpublishedAlternatesAreSkipped(): void
    {
        $this->setupStores(['en' => $this->makeStore(1, 'https://example.com/en/', 'en_US', 'en', 'en')]);

        $story = [
            'alternates' => [
                ['full_slug' => 'nl/home', 'published' => false],
            ],
            'full_slug' => 'en/home',
            'default_full_slug' => 'home',
            'lang' => 'default',
            'translated_slugs' => [],
        ];

        $result = $this->resolver->resolve($story);

        $hreflangValues = array_column($result, 'hreflang');
        $this->assertNotContains('nl-nl', $hreflangValues);
    }

    public function testPublishedAlternatesReturnCorrectUrlsAndHreflangs(): void
    {
        $this->setupStores([
            'en' => $this->makeStore(1, 'https://example.com/en/', 'en_US', 'en', 'en'),
            'nl' => $this->makeStore(2, 'https://example.com/nl/', 'nl_NL', 'nl', 'nl'),
            'fr' => $this->makeStore(3, 'https://example.com/fr/', 'fr_FR', 'fr', 'fr'),
        ]);

        $fixture = require __DIR__ . '/../_files/story_with_alternates.php';
        $result = $this->resolver->resolve($fixture['story']);

        $byHreflang = [];
        foreach ($result as $entry) {
            $byHreflang[$entry['hreflang']] = $entry['url'];
        }

        $this->assertSame('https://example.com/nl/home', $byHreflang['nl-nl']);
        $this->assertSame('https://example.com/fr/home', $byHreflang['fr-fr']);
        $this->assertSame('https://example.com/en/home', $byHreflang['en-us']);
        $this->assertSame('https://example.com/en/home', $byHreflang['x-default']);
    }

    public function testStoresWithDifferentApiKeysAreExcluded(): void
    {
        $nlStore = $this->createMock(Store::class);
        $nlStore->method('getId')->willReturn(2);
        $nlStore->method('getCode')->willReturn('nl_store');
        $nlStore->method('getBaseUrl')->willReturn('https://other.com/nl/');

        $enStore = $this->createMock(Store::class);
        $enStore->method('getId')->willReturn(1);
        $enStore->method('getCode')->willReturn('en_store');
        $enStore->method('getBaseUrl')->willReturn('https://example.com/en/');

        $this->storeManager->method('getStores')->willReturn([$enStore, $nlStore]);

        $this->config->method('apiKey')->willReturnCallback(
            fn(?string $storeCode) => $storeCode === 'nl_store' ? 'key-b' : 'key-a'
        );

        $this->config->method('slugPrefix')->willReturnCallback(
            fn(?string $storeCode) => $storeCode === 'nl_store' ? 'nl' : 'en'
        );

        $this->config->method('language')->willReturnCallback(
            fn(?string $storeCode) => $storeCode === 'nl_store' ? 'nl' : 'en'
        );

        $this->config->method('locale')->willReturnCallback(
            fn(?string $storeCode) => $storeCode === 'nl_store' ? 'nl_NL' : 'en_US'
        );

        $story = [
            'alternates' => [
                ['full_slug' => 'nl/home', 'published' => true],
            ],
            'full_slug' => 'en/home',
            'default_full_slug' => 'home',
            'lang' => 'default',
            'translated_slugs' => [],
        ];

        $result = $this->resolver->resolve($story);

        $hreflangValues = array_column($result, 'hreflang');
        $this->assertNotContains('nl-nl', $hreflangValues);
    }

    public function testTranslatedSlugsFillGapsNotCoveredByAlternates(): void
    {
        $this->setupStores([
            'en' => $this->makeStore(1, 'https://example.com/en/', 'en_US', 'en', 'en'),
            'nl' => $this->makeStore(2, 'https://example.com/nl/', 'nl_NL', 'nl', 'nl'),
            'fr' => $this->makeStore(3, 'https://example.com/fr/', 'fr_FR', 'fr', 'fr'),
        ]);

        $story = [
            'alternates' => [
                ['full_slug' => 'nl/home', 'published' => true],
            ],
            'full_slug' => 'en/home',
            'default_full_slug' => 'home',
            'lang' => 'default',
            'translated_slugs' => [
                ['lang' => 'nl', 'path' => 'nl/startpagina', 'slug' => 'home', 'name' => 'Home NL'],
                ['lang' => 'fr', 'path' => 'fr/accueil', 'slug' => 'home', 'name' => 'Home FR'],
            ],
        ];

        $result = $this->resolver->resolve($story);

        $byHreflang = [];
        foreach ($result as $entry) {
            $byHreflang[$entry['hreflang']] = $entry['url'];
        }

        // NL covered by alternates — uses alternate full_slug
        $this->assertSame('https://example.com/nl/home', $byHreflang['nl-nl']);
        // FR not in alternates — filled by translated_slugs
        $this->assertSame('https://example.com/fr/accueil', $byHreflang['fr-fr']);
    }

    public function testXDefaultPresent(): void
    {
        $this->setupStores([
            'en' => $this->makeStore(1, 'https://example.com/en/', 'en_US', 'en', 'en'),
            'nl' => $this->makeStore(2, 'https://example.com/nl/', 'nl_NL', 'nl', 'nl'),
        ]);

        $story = [
            'alternates' => [
                ['full_slug' => 'nl/home', 'published' => true],
            ],
            'full_slug' => 'en/home',
            'default_full_slug' => 'home',
            'lang' => 'default',
            'translated_slugs' => [],
        ];

        $result = $this->resolver->resolve($story);

        $hreflangValues = array_column($result, 'hreflang');
        $this->assertContains('x-default', $hreflangValues);

        $xDefault = array_filter($result, fn($e) => $e['hreflang'] === 'x-default');
        $xDefault = array_values($xDefault)[0];
        $this->assertSame('https://example.com/en/home', $xDefault['url']);
    }

    public function testSelfReferencingEntryIncluded(): void
    {
        $this->setupStores([
            'en' => $this->makeStore(1, 'https://example.com/en/', 'en_US', 'en', 'en'),
            'nl' => $this->makeStore(2, 'https://example.com/nl/', 'nl_NL', 'nl', 'nl'),
        ]);

        $story = [
            'alternates' => [
                ['full_slug' => 'nl/home', 'published' => true],
            ],
            'full_slug' => 'en/home',
            'default_full_slug' => 'home',
            'lang' => 'default',
            'translated_slugs' => [],
        ];

        $result = $this->resolver->resolve($story);

        $hreflangValues = array_column($result, 'hreflang');
        $this->assertContains('en-us', $hreflangValues);
    }

    public function testDimensionPrefixStrippedFromSlugs(): void
    {
        $this->setupStores([
            'en' => $this->makeStore(1, 'https://example.com/en/', 'en_US', 'en', 'en'),
            'nl' => $this->makeStore(2, 'https://example.com/nl/', 'nl_NL', 'nl', 'nl'),
        ]);

        $story = [
            'alternates' => [
                ['full_slug' => 'nl/about/team', 'published' => true],
            ],
            'full_slug' => 'en/about/team',
            'default_full_slug' => 'about/team',
            'lang' => 'default',
            'translated_slugs' => [],
        ];

        $result = $this->resolver->resolve($story);

        $byHreflang = [];
        foreach ($result as $entry) {
            $byHreflang[$entry['hreflang']] = $entry['url'];
        }

        $this->assertSame('https://example.com/nl/about/team', $byHreflang['nl-nl']);
        $this->assertSame('https://example.com/en/about/team', $byHreflang['en-us']);
    }

    public function testTranslatedSlugsOnlyWithoutAlternates(): void
    {
        $this->setupStores([
            'en' => $this->makeStore(1, 'https://example.com/en/', 'en_US', 'en', 'en'),
            'nl' => $this->makeStore(2, 'https://example.com/nl/', 'nl_NL', 'nl', 'nl'),
            'fr' => $this->makeStore(3, 'https://example.com/fr/', 'fr_FR', 'fr', 'fr'),
        ]);

        $story = [
            'alternates' => [],
            'full_slug' => 'en/home',
            'default_full_slug' => 'home',
            'lang' => 'default',
            'translated_slugs' => [
                ['lang' => 'nl', 'path' => 'nl/startpagina', 'slug' => 'home', 'name' => 'Home NL'],
                ['lang' => 'fr', 'path' => 'fr/accueil', 'slug' => 'home', 'name' => 'Home FR'],
            ],
        ];

        $result = $this->resolver->resolve($story);

        $byHreflang = [];
        foreach ($result as $entry) {
            $byHreflang[$entry['hreflang']] = $entry['url'];
        }

        $this->assertSame('https://example.com/nl/startpagina', $byHreflang['nl-nl']);
        $this->assertSame('https://example.com/fr/accueil', $byHreflang['fr-fr']);
        // Self-referencing and x-default come from prefix map
        $this->assertArrayHasKey('en-us', $byHreflang, 'Self-referencing entry');
        $this->assertArrayHasKey('x-default', $byHreflang);
    }

    public function testAlternatesOnlyWithoutTranslatedSlugs(): void
    {
        $this->setupStores([
            'en' => $this->makeStore(1, 'https://example.com/en/', 'en_US', 'en', 'en'),
            'nl' => $this->makeStore(2, 'https://example.com/nl/', 'nl_NL', 'nl', 'nl'),
        ]);

        $story = [
            'alternates' => [
                ['full_slug' => 'nl/home', 'published' => true],
            ],
            'full_slug' => 'en/home',
            'default_full_slug' => 'home',
            'lang' => 'default',
            'translated_slugs' => [],
        ];

        $result = $this->resolver->resolve($story);

        $byHreflang = [];
        foreach ($result as $entry) {
            $byHreflang[$entry['hreflang']] = $entry['url'];
        }

        $this->assertSame('https://example.com/nl/home', $byHreflang['nl-nl']);
        $this->assertArrayHasKey('en-us', $byHreflang);
        $this->assertArrayHasKey('x-default', $byHreflang);
    }

    public function testAlternatesTakePrecedenceOverTranslatedSlugsForSamePrefix(): void
    {
        $this->setupStores([
            'en' => $this->makeStore(1, 'https://example.com/en/', 'en_US', 'en', 'en'),
            'nl' => $this->makeStore(2, 'https://example.com/nl/', 'nl_NL', 'nl', 'nl'),
        ]);

        $story = [
            'alternates' => [
                ['full_slug' => 'nl/home', 'published' => true],
            ],
            'full_slug' => 'en/home',
            'default_full_slug' => 'home',
            'lang' => 'default',
            'translated_slugs' => [
                ['lang' => 'nl', 'path' => 'nl/startpagina', 'slug' => 'home', 'name' => 'Home NL'],
            ],
        ];

        $result = $this->resolver->resolve($story);

        $byHreflang = [];
        foreach ($result as $entry) {
            $byHreflang[$entry['hreflang']] = $entry['url'];
        }

        // Alternates take precedence — uses alternate full_slug, not translated_slugs path
        $this->assertSame('https://example.com/nl/home', $byHreflang['nl-nl']);
    }

    public function testNoAlternatesOrTranslatedSlugsReturnsEmpty(): void
    {
        $story = [
            'alternates' => [],
            'full_slug' => 'home',
            'translated_slugs' => [],
        ];

        $this->assertSame([], $this->resolver->resolve($story));
    }

    public function testAlternatesWithoutLangFieldMatchByPrefix(): void
    {
        $this->setupStores([
            'en' => $this->makeStore(1, 'https://example.com/en/', 'en_US', 'en', 'en'),
            'de' => $this->makeStore(2, 'https://example.com/de/', 'de_DE', 'de', 'de'),
        ]);

        $story = [
            'alternates' => [
                ['full_slug' => 'de/ueber-uns', 'published' => true, 'slug' => 'ueber-uns'],
            ],
            'full_slug' => 'en/about-us',
            'default_full_slug' => 'about-us',
            'lang' => 'default',
            'translated_slugs' => [],
        ];

        $result = $this->resolver->resolve($story);

        $byHreflang = [];
        foreach ($result as $entry) {
            $byHreflang[$entry['hreflang']] = $entry['url'];
        }

        $this->assertSame('https://example.com/de/ueber-uns', $byHreflang['de-de']);
        $this->assertSame('https://example.com/en/about-us', $byHreflang['en-us']);
    }

    public function testStoresWithoutSlugPrefixExcludedFromAlternates(): void
    {
        $nlStore = $this->createMock(Store::class);
        $nlStore->method('getId')->willReturn(2);
        $nlStore->method('getCode')->willReturn('nl_store');
        $nlStore->method('getBaseUrl')->willReturn('https://example.com/nl/');

        $enStore = $this->createMock(Store::class);
        $enStore->method('getId')->willReturn(1);
        $enStore->method('getCode')->willReturn('en_store');
        $enStore->method('getBaseUrl')->willReturn('https://example.com/en/');

        $this->storeManager->method('getStores')->willReturn([$enStore, $nlStore]);

        $this->config->method('apiKey')->willReturn('test-api-key');

        // nl_store has no slug prefix — field translations only
        $this->config->method('slugPrefix')->willReturnCallback(
            fn(?string $storeCode) => $storeCode === 'en_store' ? 'en' : ''
        );

        $this->config->method('language')->willReturnCallback(
            fn(?string $storeCode) => $storeCode === 'nl_store' ? 'nl' : 'en'
        );

        $this->config->method('locale')->willReturnCallback(
            fn(?string $storeCode) => $storeCode === 'nl_store' ? 'nl_NL' : 'en_US'
        );

        $story = [
            'alternates' => [
                ['full_slug' => 'nl/home', 'published' => true],
            ],
            'full_slug' => 'en/home',
            'default_full_slug' => 'home',
            'lang' => 'default',
            'translated_slugs' => [],
        ];

        $result = $this->resolver->resolve($story);

        $hreflangValues = array_column($result, 'hreflang');
        // nl_store has no slug prefix, so nl alternate can't match
        $this->assertNotContains('nl-nl', $hreflangValues);
    }

    /**
     * @param array<string, array{store: Store&MockObject, locale: string, language: string, slugPrefix: string}> $stores
     */
    private function setupStores(array $stores): void
    {
        $storeObjects = [];

        foreach ($stores as $info) {
            $storeObjects[] = $info['store'];
        }

        $this->storeManager->method('getStores')->willReturn($storeObjects);

        $this->config->method('apiKey')->willReturn('test-api-key');

        $this->config->method('locale')->willReturnCallback(
            function (?string $storeCode) use ($stores) {
                foreach ($stores as $info) {
                    if ($info['store']->getCode() === $storeCode) {
                        return $info['locale'];
                    }
                }
                return '';
            }
        );

        $this->config->method('language')->willReturnCallback(
            function (?string $storeCode) use ($stores) {
                foreach ($stores as $info) {
                    if ($info['store']->getCode() === $storeCode) {
                        return $info['language'];
                    }
                }
                return '';
            }
        );

        $this->config->method('slugPrefix')->willReturnCallback(
            function (?string $storeCode) use ($stores) {
                foreach ($stores as $info) {
                    if ($info['store']->getCode() === $storeCode) {
                        return $info['slugPrefix'];
                    }
                }
                return '';
            }
        );
    }

    /**
     * @return array{store: Store&MockObject, locale: string, language: string, slugPrefix: string}
     */
    private function makeStore(int $id, string $baseUrl, string $locale, string $language, string $slugPrefix): array
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getCode')->willReturn($language . '_store');
        $store->method('getBaseUrl')->willReturn($baseUrl);

        return [
            'store' => $store,
            'locale' => $locale,
            'language' => $language,
            'slugPrefix' => $slugPrefix,
        ];
    }
}
