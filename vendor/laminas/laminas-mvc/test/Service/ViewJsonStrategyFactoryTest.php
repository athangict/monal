<?php

namespace LaminasTest\Mvc\Service;

use Interop\Container\ContainerInterface;
use Laminas\Mvc\Service\ViewJsonStrategyFactory;
use Laminas\View\Renderer\JsonRenderer;
use Laminas\View\Strategy\JsonStrategy;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ViewJsonStrategyFactoryTest extends TestCase
{
    private function createContainer()
    {
        $renderer  = $this->createMock(JsonRenderer::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with('ViewJsonRenderer')->willReturn($renderer);
        return $container;
    }

    public function testReturnsJsonStrategy()
    {
        $factory = new ViewJsonStrategyFactory();
        $result  = $factory($this->createContainer(), 'ViewJsonStrategy');
        $this->assertInstanceOf(JsonStrategy::class, $result);
    }
}
