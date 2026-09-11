<?php

namespace LaminasTest\Mvc\Service;

use Interop\Container\ContainerInterface;
use Laminas\Mvc\Service\ViewPrefixPathStackResolverFactory;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Resolver\PrefixPathStackResolver;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ViewPrefixPathStackResolverFactoryTest extends TestCase
{
    public function testCreateService()
    {
        $serviceLocator = $this->createMock(ServiceLocatorInterface::class);
        $serviceLocator->method('get')->willReturn([
            'view_manager' => [
                'prefix_template_path_stack' => [
                    'album/' => [],
                ],
            ],
        ]);

        $factory  = new ViewPrefixPathStackResolverFactory();
        $resolver = $factory($serviceLocator, 'ViewPrefixPathStackResolver');

        $this->assertInstanceOf(PrefixPathStackResolver::class, $resolver);
    }
}
