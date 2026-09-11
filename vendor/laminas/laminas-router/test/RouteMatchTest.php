<?php

declare(strict_types=1);

namespace LaminasTest\Router;

use Laminas\Router\RouteMatch;
use PHPUnit\Framework\TestCase;

final class RouteMatchTest extends TestCase
{
    public function testParamsAreStored(): void
    {
        $match = new RouteMatch(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $match->getParams());
    }

    public function testMatchedRouteNameIsSet(): void
    {
        $match = new RouteMatch([]);
        $match->setMatchedRouteName('foo');

        $this->assertEquals('foo', $match->getMatchedRouteName());
    }

    public function testSetParam(): void
    {
        $match = new RouteMatch([]);
        $match->setParam('foo', 'bar');

        $this->assertEquals(['foo' => 'bar'], $match->getParams());
    }

    public function testGetParam(): void
    {
        $match = new RouteMatch(['foo' => 'bar']);

        $this->assertEquals('bar', $match->getParam('foo'));
    }

    public function testGetNonExistentParamWithoutDefault(): void
    {
        $match = new RouteMatch([]);

        $this->assertNull($match->getParam('foo'));
    }

    public function testGetNonExistentParamWithDefault(): void
    {
        $match = new RouteMatch([]);

        $this->assertEquals('bar', $match->getParam('foo', 'bar'));
    }
}
