<?php // phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn


declare(strict_types=1);

namespace LaminasTest\Router\TestAsset;

use Laminas\Router\RouteInterface;
use Laminas\Router\RouteStackInterface;
use Laminas\Stdlib\RequestInterface;

/**
 * @template TRoute of RouteInterface
 * @template-implements RouteStackInterface<TRoute>
 */
final class Router implements RouteStackInterface
{
    /**
     * @inheritDoc
     */
    public static function factory($options = [])
    {
        return new static();
    }

    /**
     * @inheritDoc
     */
    public function match(RequestInterface $request)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function assemble(array $params = [], array $options = [])
    {
        return null;
    }

    /** @inheritDoc */
    public function addRoute($name, $route, $priority = null)
    {
        return $this;
    }

    /** @inheritDoc */
    public function addRoutes($routes)
    {
        return $this;
    }

    /** @inheritDoc */
    public function removeRoute($name)
    {
        return $this;
    }

    /** @inheritDoc */
    public function setRoutes($routes)
    {
        return $this;
    }
}
