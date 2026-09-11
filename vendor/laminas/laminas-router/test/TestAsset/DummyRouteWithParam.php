<?php

declare(strict_types=1);

namespace LaminasTest\Router\TestAsset;

use Laminas\Router\RouteMatch;
use Laminas\Stdlib\RequestInterface;

/**
 * Dummy route.
 */
final class DummyRouteWithParam extends DummyRoute
{
    /**
     * @inheritDoc
     */
    public function match(RequestInterface $request)
    {
        return new RouteMatch(['foo' => 'bar']);
    }

    /**
     * @inheritDoc
     */
    public function assemble(array $params = [], array $options = [])
    {
        return $params['foo'] ?? '';
    }
}
