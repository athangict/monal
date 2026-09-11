<?php

declare(strict_types=1);

namespace LaminasTest\Router;

use ArrayIterator;
use Laminas\Router\Exception\InvalidArgumentException;
use Laminas\Router\Exception\RuntimeException;
use Laminas\Router\Http\Chain;
use Laminas\Router\Http\Hostname;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Method;
use Laminas\Router\Http\Placeholder;
use Laminas\Router\Http\Regex;
use Laminas\Router\Http\Scheme;
use Laminas\Router\Http\Segment;
use Laminas\Router\RouteMatch;
use Laminas\Router\RoutePluginManager;
use Laminas\Router\SimpleRouteStack;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SimpleRouteStackTest extends TestCase
{
    public function testSetRoutePluginManager(): void
    {
        $routes = new RoutePluginManager(new ServiceManager());
        $stack  = new SimpleRouteStack();
        $stack->setRoutePluginManager($routes);

        $this->assertEquals($routes, $stack->getRoutePluginManager());
    }

    public function testAddRoutesWithInvalidArgument(): void
    {
        $stack = new SimpleRouteStack();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('addRoutes expects an array or Traversable set of routes');
        $stack->addRoutes('foo');
    }

    public function testAddRoutesAsArray(): void
    {
        $stack = new SimpleRouteStack();
        $stack->addRoutes([
            'foo' => new TestAsset\DummyRoute(),
        ]);

        $this->assertInstanceOf(RouteMatch::class, $stack->match(new Request()));
    }

    public function testAddRoutesAsTraversable(): void
    {
        $stack = new SimpleRouteStack();
        $stack->addRoutes(new ArrayIterator([
            'foo' => new TestAsset\DummyRoute(),
        ]));

        $this->assertInstanceOf(RouteMatch::class, $stack->match(new Request()));
    }

    public function testSetRoutesWithInvalidArgument(): void
    {
        $stack = new SimpleRouteStack();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('addRoutes expects an array or Traversable set of routes');
        $stack->setRoutes('foo');
    }

    public function testSetRoutesAsArray(): void
    {
        $stack = new SimpleRouteStack();
        $stack->setRoutes([
            'foo' => new TestAsset\DummyRoute(),
        ]);

        $this->assertInstanceOf(RouteMatch::class, $stack->match(new Request()));

        $stack->setRoutes([]);

        $this->assertNull($stack->match(new Request()));
    }

    public function testSetRoutesAsTraversable(): void
    {
        $stack = new SimpleRouteStack();
        $stack->setRoutes(new ArrayIterator([
            'foo' => new TestAsset\DummyRoute(),
        ]));

        $this->assertInstanceOf(RouteMatch::class, $stack->match(new Request()));

        $stack->setRoutes(new ArrayIterator([]));

        $this->assertNull($stack->match(new Request()));
    }

    public function testremoveRouteAsArray(): void
    {
        $stack = new SimpleRouteStack();
        $stack->addRoutes([
            'foo' => new TestAsset\DummyRoute(),
        ]);

        $this->assertEquals($stack, $stack->removeRoute('foo'));
        $this->assertNull($stack->match(new Request()));
    }

    public function testAddRouteWithInvalidArgument(): void
    {
        $stack = new SimpleRouteStack();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route definition must be an array or Traversable object');
        /** @psalm-suppress InvalidArgument we're explicitly verifying runtime type checks here */
        $stack->addRoute('foo', 'bar');
    }

    public function testAddRouteAsArrayWithoutOptions(): void
    {
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', [
            'type' => TestAsset\DummyRoute::class,
        ]);

        $this->assertInstanceOf(RouteMatch::class, $stack->match(new Request()));
    }

    public function testAddRouteAsArrayWithOptions(): void
    {
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', [
            'type'    => TestAsset\DummyRoute::class,
            'options' => [],
        ]);

        $this->assertInstanceOf(RouteMatch::class, $stack->match(new Request()));
    }

    public function testAddRouteAsArrayWithoutType(): void
    {
        $stack = new SimpleRouteStack();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "type" option');
        $stack->addRoute('foo', []);
    }

    public function testAddRouteAsArrayWithPriority(): void
    {
        $stack = new SimpleRouteStack();

        $stack->addRoute('foo', [
            'type'     => TestAsset\DummyRouteWithParam::class,
            'priority' => 2,
        ])->addRoute('bar', [
            'type'     => TestAsset\DummyRoute::class,
            'priority' => 1,
        ]);

        $this->assertEquals('bar', $stack->match(new Request())->getParam('foo'));
    }

    public function testAddRouteWithPriority(): void
    {
        $stack = new SimpleRouteStack();

        $route           = new TestAsset\DummyRouteWithParam();
        $route->priority = 2;
        $stack->addRoute('baz', $route);

        $stack->addRoute('foo', [
            'type'     => TestAsset\DummyRoute::class,
            'priority' => 1,
        ]);

        $this->assertEquals('bar', $stack->match(new Request())->getParam('foo'));
    }

    public function testAddRouteAsTraversable(): void
    {
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', new ArrayIterator([
            'type' => TestAsset\DummyRoute::class,
        ]));

        $this->assertInstanceOf(RouteMatch::class, $stack->match(new Request()));
    }

    public function testAssemble(): void
    {
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', new TestAsset\DummyRoute());
        $this->assertEquals('', $stack->assemble([], ['name' => 'foo']));
    }

    public function testAssembleWithoutNameOption(): void
    {
        $stack = new SimpleRouteStack();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "name" option');
        $stack->assemble();
    }

    public function testAssembleNonExistentRoute(): void
    {
        $stack = new SimpleRouteStack();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Route with name "foo" not found');
        $stack->assemble([], ['name' => 'foo']);
    }

    public function testDefaultParamIsAddedToMatch(): void
    {
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', new TestAsset\DummyRoute());
        $stack->setDefaultParam('foo', 'bar');

        $this->assertEquals('bar', $stack->match(new Request())->getParam('foo'));
    }

    public function testDefaultParamDoesNotOverrideParam(): void
    {
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', new TestAsset\DummyRouteWithParam());
        $stack->setDefaultParam('foo', 'baz');

        $this->assertEquals('bar', $stack->match(new Request())->getParam('foo'));
    }

    public function testDefaultParamIsUsedForAssembling(): void
    {
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', new TestAsset\DummyRouteWithParam());
        $stack->setDefaultParam('foo', 'bar');

        $this->assertEquals('bar', $stack->assemble([], ['name' => 'foo']));
    }

    public function testDefaultParamDoesNotOverrideParamForAssembling(): void
    {
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', new TestAsset\DummyRouteWithParam());
        $stack->setDefaultParam('foo', 'baz');

        $this->assertEquals('bar', $stack->assemble(['foo' => 'bar'], ['name' => 'foo']));
    }

    public function testFactory(): void
    {
        $tester = new FactoryTester($this);
        $tester->testFactory(
            SimpleRouteStack::class,
            [],
            [
                'route_plugins'  => new RoutePluginManager(new ServiceManager()),
                'routes'         => [],
                'default_params' => [],
            ]
        );
    }

    public function testGetRoutes(): void
    {
        $stack = new SimpleRouteStack();
        $this->assertInstanceOf('Traversable', $stack->getRoutes());
    }

    public function testGetRouteByName(): void
    {
        $stack = new SimpleRouteStack();
        $route = new TestAsset\DummyRoute();
        $stack->addRoute('foo', $route);

        $this->assertEquals($route, $stack->getRoute('foo'));
    }

    public function testHasRoute(): void
    {
        $stack = new SimpleRouteStack();
        $this->assertEquals(false, $stack->hasRoute('foo'));

        $stack->addRoute('foo', new TestAsset\DummyRoute());
        $this->assertEquals(true, $stack->hasRoute('foo'));
    }

    /** @return array<class-string, array{0: array, 1: int}> */
    public static function routeTypeProvider(): array
    {
        $routePlugins = new RoutePluginManager(new ServiceManager());
        return [
            Chain::class       => [
                [
                    'type'     => Chain::class,
                    'priority' => 1,
                    'options'  => [
                        'routes'        => [],
                        'route_plugins' => $routePlugins,
                    ],
                ],
                1,
            ],
            Hostname::class    => [
                [
                    'type'     => Hostname::class,
                    'options'  => [
                        'route'    => 'www.example.com',
                        'defaults' => [
                            'controller' => 'SomeController',
                            'action'     => 'index',
                        ],
                    ],
                    'priority' => 5,
                ],
                5,
            ],
            Literal::class     => [
                [
                    'type'     => Literal::class,
                    'options'  => [
                        'route'    => '/blah',
                        'defaults' => [
                            'controller' => 'SomeController',
                            'action'     => 'index',
                        ],
                    ],
                    'priority' => 10,
                ],
                10,
            ],
            Method::class      => [
                [
                    'type'     => Method::class,
                    'options'  => [
                        'route' => '/duck',
                        'verb'  => 'QUACK',
                    ],
                    'priority' => 20,
                ],
                20,
            ],
            Placeholder::class => [
                [
                    'type'     => Placeholder::class,
                    'options'  => [],
                    'priority' => 30,
                ],
                30,
            ],
            Regex::class       => [
                [
                    'type'     => Regex::class,
                    'options'  => [
                        'regex' => '/(?<foo>[^/]+)',
                        'spec'  => '/%foo%',
                    ],
                    'priority' => 40,
                ],
                40,
            ],
            Scheme::class      => [
                [
                    'type'     => Scheme::class,
                    'options'  => [
                        'scheme' => 'carrots',
                    ],
                    'priority' => 50,
                ],
                50,
            ],
            Segment::class     => [
                [
                    'type'     => Segment::class,
                    'options'  => [
                        'route' => '/mushrooms',
                    ],
                    'priority' => 60,
                ],
                60,
            ],
        ];
    }

    #[DataProvider('routeTypeProvider')]
    public function testSimpleRouteStackSetsPriorityForAllKnownRouteTypes(array $routeSpec, int $expectedPriority): void
    {
        $router = new SimpleRouteStack();
        $router->addRoute('name', $routeSpec);

        $route = $router->getRoute('name');
        self::assertNotNull($route);
        self::assertEquals($expectedPriority, $route->priority);
    }
}
