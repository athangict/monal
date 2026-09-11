<?php

namespace LaminasTest\Mvc\Service;

use Interop\Container\ContainerInterface;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\Service\ResponseFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ResponseFactoryTest extends TestCase
{
    public function testFactoryCreatesHttpResponse()
    {
        $container = $this->createMock(ContainerInterface::class);
        $factory = new ResponseFactory();
        $response = $factory($container, 'Response');
        $this->assertInstanceOf(HttpResponse::class, $response);
    }
}
