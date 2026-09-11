<?php

declare(strict_types=1);

namespace LaminasTest\Router\Http;

use Laminas\Http\Request;
use Laminas\Router\Http\HttpRouteMatch;
use Laminas\Router\Http\Scheme;
use Laminas\Stdlib\Request as BaseRequest;
use Laminas\Uri\Http as HttpUri;
use LaminasTest\Router\FactoryTester;
use PHPUnit\Framework\TestCase;

final class SchemeTest extends TestCase
{
    public function testMatching(): void
    {
        $request = new Request();
        $request->setUri('https://example.com/');

        $route = new Scheme('https');
        $match = $route->match($request);

        $this->assertInstanceOf(HttpRouteMatch::class, $match);
    }

    public function testNoMatchingOnDifferentScheme(): void
    {
        $request = new Request();
        $request->setUri('http://example.com/');

        $route = new Scheme('https');
        $match = $route->match($request);

        $this->assertNull($match);
    }

    public function testAssembling(): void
    {
        $uri   = new HttpUri();
        $route = new Scheme('https');
        $path  = $route->assemble([], ['uri' => $uri]);

        $this->assertEquals('', $path);
        $this->assertEquals('https', $uri->getScheme());
    }

    public function testNoMatchWithoutUriMethod(): void
    {
        $route   = new Scheme('https');
        $request = new BaseRequest();

        $this->assertNull($route->match($request));
    }

    public function testGetAssembledParams(): void
    {
        $route = new Scheme('https');
        $route->assemble(['foo' => 'bar']);

        $this->assertEquals([], $route->getAssembledParams());
    }

    public function testFactory(): void
    {
        $tester = new FactoryTester($this);
        $tester->testFactory(
            Scheme::class,
            [
                'scheme' => 'Missing "scheme" in options array',
            ],
            [
                'scheme' => 'http',
            ]
        );
    }
}
