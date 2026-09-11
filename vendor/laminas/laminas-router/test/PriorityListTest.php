<?php

declare(strict_types=1);

namespace LaminasTest\Router;

use Laminas\Router\PriorityList;
use Laminas\Router\RouteInterface;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function iterator_to_array;

final class PriorityListTest extends TestCase
{
    /** @var PriorityList<string, RouteInterface> */
    private PriorityList $list;

    public function setUp(): void
    {
        $this->list = new PriorityList();
    }

    public function testInsert(): void
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), 0);

        $this->assertCount(1, $this->list);

        $list = iterator_to_array($this->list);
        $this->assertSame(['foo'], array_keys($list));
    }

    public function testRemove(): void
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), 0);
        $this->list->insert('bar', new TestAsset\DummyRoute(), 0);

        $this->assertCount(2, $this->list);

        $this->list->remove('foo');

        $this->assertCount(1, $this->list);
    }

    public function testRemovingNonExistentRouteDoesNotYieldError(): void
    {
        $this->expectNotToPerformAssertions();
        $this->list->remove('foo');
    }

    public function testClear(): void
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), 0);
        $this->list->insert('bar', new TestAsset\DummyRoute(), 0);

        $this->assertCount(2, $this->list);

        $this->list->clear();

        $this->assertCount(0, $this->list);
        $this->assertFalse($this->list->current());
    }

    public function testGet(): void
    {
        $route = new TestAsset\DummyRoute();

        $this->list->insert('foo', $route, 0);

        $this->assertEquals($route, $this->list->get('foo'));
        $this->assertNull($this->list->get('bar'));
    }

    public function testLIFOOnly(): void
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), 0);
        $this->list->insert('bar', new TestAsset\DummyRoute(), 0);
        $this->list->insert('baz', new TestAsset\DummyRoute(), 0);

        $list = iterator_to_array($this->list);

        $this->assertEquals(['baz', 'bar', 'foo'], array_keys($list));
    }

    public function testPriorityOnly(): void
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), 1);
        $this->list->insert('bar', new TestAsset\DummyRoute(), 0);
        $this->list->insert('baz', new TestAsset\DummyRoute(), 2);

        $list = iterator_to_array($this->list);

        $this->assertEquals(['baz', 'foo', 'bar'], array_keys($list));
    }

    public function testLIFOWithPriority(): void
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), 0);
        $this->list->insert('bar', new TestAsset\DummyRoute(), 0);
        $this->list->insert('baz', new TestAsset\DummyRoute(), 1);

        $list = iterator_to_array($this->list);

        $this->assertEquals(['baz', 'bar', 'foo'], array_keys($list));
    }

    public function testPriorityWithNegativesAndNull(): void
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), null);
        $this->list->insert('bar', new TestAsset\DummyRoute(), 1);
        $this->list->insert('baz', new TestAsset\DummyRoute(), -1);

        $list = iterator_to_array($this->list);

        $this->assertEquals(['bar', 'foo', 'baz'], array_keys($list));
    }
}
