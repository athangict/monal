<?php

namespace LaminasTest\Mvc\Service;

use Interop\Container\ContainerInterface;
use Laminas\Http\Request as HttpRequest;
use Laminas\Mvc\Service\RequestFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class RequestFactoryTest extends TestCase
{
    public function testFactoryCreatesHttpRequest()
    {
        $factory = new RequestFactory();
        $container = $this->createMock(ContainerInterface::class);
        $request = $factory($container, 'Request');
        $this->assertInstanceOf(HttpRequest::class, $request);
    }
}
