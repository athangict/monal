<?php

namespace LaminasTest\Mvc\Service;

use Interop\Container\ContainerInterface;
use Laminas\Mvc\Service\ViewManagerFactory;
use Laminas\Mvc\View\Http\ViewManager as HttpViewManager;
use PHPUnit\Framework\TestCase;

class ViewManagerFactoryTest extends TestCase
{
    private function createContainer()
    {
        $http = $this->createMock(HttpViewManager::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with('HttpViewManager')->willReturn($http);
        return $container;
    }

    public function testReturnsHttpViewManager()
    {
        $factory = new ViewManagerFactory();
        $result  = $factory($this->createContainer(), 'ViewManager');
        $this->assertInstanceOf(HttpViewManager::class, $result);
    }
}
