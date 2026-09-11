<?php

namespace LaminasTest\Mvc\Service;

use Interop\Container\ContainerInterface;
use Laminas\Mvc\Service\ViewFeedStrategyFactory;
use Laminas\View\Renderer\FeedRenderer;
use Laminas\View\Strategy\FeedStrategy;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ViewFeedStrategyFactoryTest extends TestCase
{
    private function createContainer()
    {
        $renderer  = $this->createMock(FeedRenderer::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with('ViewFeedRenderer')->willReturn($renderer);
        return $container;
    }

    public function testReturnsFeedStrategy()
    {
        $factory = new ViewFeedStrategyFactory();
        $result  = $factory($this->createContainer(), 'ViewFeedStrategy');
        $this->assertInstanceOf(FeedStrategy::class, $result);
    }
}
