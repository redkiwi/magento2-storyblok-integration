<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Test\Unit\Controller\Index;

use PHPUnit\Framework\TestCase;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Result\Page;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Framework\View\Result\PageFactory;
use MediaLounge\Storyblok\Controller\Index\Index;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\View\Element\BlockInterface;
use MediaLounge\Storyblok\Model\Config as StoryblokConfig;
use MediaLounge\Storyblok\Model\HreflangResolver;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;

class IndexTest extends TestCase
{
    private ObjectManagerHelper $objectManagerHelper;
    private Context&MockObject $contextMock;
    private BlockInterface&MockObject $blockMock;
    private Title&MockObject $titleMock;
    private Config&MockObject $configMock;
    private RequestInterface&MockObject $requestMock;
    private PageFactory&MockObject $pageFactoryMock;
    private StoryblokConfig&MockObject $storyblokConfigMock;
    private HreflangResolver&MockObject $hreflangResolverMock;

    protected function setUp(): void
    {
        $this->blockMock = $this->getMockBuilder(BlockInterface::class)
            ->addMethods(['setStory'])
            ->getMockForAbstractClass();

        $layoutMock = $this->getMockForAbstractClass(LayoutInterface::class);
        $layoutMock
            ->expects($this->any())
            ->method('getBlock')
            ->with('storyblok.page')
            ->willReturn($this->blockMock);

        $this->requestMock = $this->getMockForAbstractClass(RequestInterface::class);

        $this->contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->contextMock
            ->expects($this->atLeastOnce())
            ->method('getRequest')
            ->will($this->returnValue($this->requestMock));

        $this->titleMock = $this->createMock(Title::class);

        $this->configMock = $this->createMock(Config::class);
        $this->configMock
            ->expects($this->any())
            ->method('getTitle')
            ->willReturn($this->titleMock);

        $pageMock = $this->createMock(Page::class);
        $pageMock
            ->expects($this->any())
            ->method('getConfig')
            ->willReturn($this->configMock);
        $pageMock
            ->expects($this->any())
            ->method('getLayout')
            ->willReturn($layoutMock);

        $this->pageFactoryMock = $this->createMock(PageFactory::class);
        $this->pageFactoryMock
            ->expects($this->any())
            ->method('create')
            ->willReturn($pageMock);

        $this->storyblokConfigMock = $this->createMock(StoryblokConfig::class);
        $this->hreflangResolverMock = $this->createMock(HreflangResolver::class);
        $this->hreflangResolverMock->method('resolve')->willReturn([]);

        $this->objectManagerHelper = new ObjectManagerHelper($this);
    }

    public function testExecute(): void
    {
        $fixtureStoryArray = require __DIR__ . '../../../_files/story_with_blocks.php';

        $this->requestMock
            ->expects($this->atLeastOnce())
            ->method('getParam')
            ->with('story', null)
            ->willReturn($fixtureStoryArray['story']);

        $this->titleMock
            ->expects($this->atLeastOnce())
            ->method('set')
            ->with($fixtureStoryArray['story']['name'])
            ->willReturn($this->returnSelf());

        $this->blockMock
            ->expects($this->once())
            ->method('setStory')
            ->with($fixtureStoryArray['story'])
            ->willReturn($this->returnSelf());

        $controller = $this->objectManagerHelper->getObject(Index::class, [
            'context' => $this->contextMock,
            'pageFactory' => $this->pageFactoryMock,
            'config' => $this->storyblokConfigMock,
            'hreflangResolver' => $this->hreflangResolverMock,
        ]);

        $controller->execute();
    }

    public function testMissingStory(): void
    {
        $this->expectException(NotFoundException::class);

        $this->requestMock
            ->expects($this->atLeastOnce())
            ->method('getParam')
            ->with('story', null)
            ->willReturn(null);

        $controller = $this->objectManagerHelper->getObject(Index::class, [
            'context' => $this->contextMock,
            'pageFactory' => $this->pageFactoryMock,
            'config' => $this->storyblokConfigMock,
            'hreflangResolver' => $this->hreflangResolverMock,
        ]);

        $controller->execute();
    }

    public function testSetMetaFields(): void
    {
        $fixtureStoryArray = require __DIR__ . '../../../_files/story_with_meta_fields.php';

        $this->requestMock
            ->expects($this->atLeastOnce())
            ->method('getParam')
            ->with('story', null)
            ->willReturn($fixtureStoryArray['story']);

        $this->titleMock
            ->expects($this->atLeastOnce())
            ->method('set')
            ->with($fixtureStoryArray['story']['content']['meta']['title'])
            ->willReturn($this->returnSelf());

        $this->configMock
            ->expects($this->once())
            ->method('setDescription')
            ->with($fixtureStoryArray['story']['content']['meta']['description']);

        $controller = $this->objectManagerHelper->getObject(Index::class, [
            'context' => $this->contextMock,
            'pageFactory' => $this->pageFactoryMock,
            'config' => $this->storyblokConfigMock,
            'hreflangResolver' => $this->hreflangResolverMock,
        ]);

        $controller->execute();
    }
}
