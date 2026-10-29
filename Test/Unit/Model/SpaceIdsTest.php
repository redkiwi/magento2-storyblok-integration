<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Test\Unit\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MediaLounge\Storyblok\Model\Config;
use MediaLounge\Storyblok\Model\SpaceIds;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SpaceIdsTest extends TestCase
{
    private CacheInterface|MockObject $cache;
    private Curl|MockObject $curl;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->curl = $this->createMock(Curl::class);
    }

    public function testTheSpacesOfEveryStoreTokenAreReturnedOnce(): void
    {
        $this->cache->method('load')->willReturnCallback(
            static fn (string $key) => $key === 'storyblok_space_id_' . sha1('token-a') ? '111' : false
        );
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{"space":{"id":222}}');
        $this->curl->expects($this->once())->method('get')->with($this->stringEndsWith('token=token-b'));
        $this->cache->expects($this->once())->method('save')->with('222');

        $ids = $this->createSpaceIds(['default' => 'token-a', 'nl' => 'token-b', 'be' => 'token-b'])->get();

        $this->assertSame(['111', '222'], $ids);
    }

    public function testAFailedLookupIsNotCached(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->curl->method('getStatus')->willReturn(401);
        $this->curl->method('getBody')->willReturn('{"error":"Unauthorized"}');
        $this->cache->expects($this->never())->method('save');

        $this->assertSame([], $this->createSpaceIds(['default' => 'bad-token'])->get());
    }

    public function testAStoreWithoutATokenIsSkipped(): void
    {
        $this->curl->expects($this->never())->method('get');

        $this->assertSame([], $this->createSpaceIds(['default' => ''])->get());
    }

    /**
     * @param array<string, string> $tokensByStoreCode
     */
    private function createSpaceIds(array $tokensByStoreCode): SpaceIds
    {
        $stores = [];
        foreach (array_keys($tokensByStoreCode) as $code) {
            $store = $this->createMock(Store::class);
            $store->method('getCode')->willReturn($code);
            $stores[] = $store;
        }

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);

        $config = $this->createMock(Config::class);
        $config->method('apiKey')->willReturnCallback(
            static fn (?string $code = null) => $tokensByStoreCode[$code] ?? ''
        );

        return new SpaceIds($storeManager, $config, $this->cache, $this->curl, $this->createMock(LoggerInterface::class));
    }
}
