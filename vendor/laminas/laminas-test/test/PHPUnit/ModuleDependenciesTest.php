<?php

declare(strict_types=1);

namespace LaminasTest\Test\PHPUnit;

use Laminas\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
use LaminasTest\Test\ExpectedExceptionTrait;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * @group      Laminas_Test
 */
class ModuleDependenciesTest extends AbstractHttpControllerTestCase
{
    use ExpectedExceptionTrait;

    public function testDependenciesModules(): void
    {
        $this->setApplicationConfig(
            include __DIR__ . '/../_files/application.config.with.dependencies.php'
        );
        $sm = $this->getApplicationServiceLocator();
        $this->assertTrue($sm->has('FooObject'));
        $this->assertTrue($sm->has('BarObject'));

        $this->assertModulesLoaded(['Foo', 'Bar']);
        $this->expectedException(ExpectationFailedException::class);
        $this->assertModulesLoaded(['Foo', 'Bar', 'Unknown']);
    }

    public function testBadDependenciesModules(): void
    {
        $this->setApplicationConfig(
            include __DIR__ . '/../_files/application.config.with.dependencies.disabled.php'
        );
        $sm = $this->getApplicationServiceLocator();
        $this->assertFalse($sm->has('FooObject'));
        $this->assertTrue($sm->has('BarObject'));

        $this->assertNotModulesLoaded(['Foo']);
        $this->expectedException(ExpectationFailedException::class);
        $this->assertNotModulesLoaded(['Foo', 'Bar']);
    }

    /**
     * Test that 'assertModulesLoaded()' can detect modules that are loaded in a module's init() method
     */
    public function testLoadedModulesUsingModuleInit(): void
    {
        $this->setApplicationConfig(
            include __DIR__ . '/../_files/application.config.with.modules.init.php'
        );
        $sm = $this->getApplicationServiceLocator();

        // Check that modules loaded and created the below services
        $this->assertTrue($sm->has('FooObject'));
        $this->assertTrue($sm->has('QuxObject'));

        // Assert for the module loaded via Module init() method
        $this->assertModulesLoaded(['Foo']);
    }
}
