<?php

declare(strict_types=1);

namespace LaminasTest\Router\Http\TestAsset;

use Laminas\Router\Http\HttpRouteMatch;
use Laminas\Stdlib\RequestInterface;

/**
 * Dummy route.
 */
final class DummyRouteWithParam extends DummyRoute
{
    /**
     * @inheritDoc
     */
    public function match(
        RequestInterface $request,
        int|null $pathOffset = null
    ) {
        return new HttpRouteMatch(['foo' => 'bar'], -4);
    }

    /**
     * @inheritDoc
     */
    public function assemble(array $params = [], array $options = [])
    {
        return $params['foo'] ?? '';
    }
}
