<?php

declare(strict_types=1);

namespace LaminasTest\Router\Http;

use Laminas\Router\Http\HttpRouterFactory;
use Laminas\Router\RouteInterface;
use Laminas\Router\RoutePluginManager;
use LaminasTest\Router\RouterFactoryTest as TestCase;

final class HttpRouterFactoryTest extends TestCase
{
    public function setUp(): void
    {
        $this->defaultServiceConfig = [
            'factories' => [
                /**
                 * @psalm-return RoutePluginManager<RouteInterface>
                 */
                'RoutePluginManager' => static fn($services): RoutePluginManager => new RoutePluginManager($services),
            ],
        ];

        $this->factory = new HttpRouterFactory();
    }
}
