<?php

declare(strict_types=1);

namespace LaminasTest\Router;

use ArrayIterator;
use Laminas\Router\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Helper to test route factories.
 */
final class FactoryTester
{
    /**
     * Create a new factory tester.
     */
    public function __construct(
        /**
         * Test case to call assertions to.
         */
        protected TestCase $testCase
    ) {
    }

    /**
     * Test a factory.
     */
    public function testFactory(string $classname, array $requiredOptions, array $options): void
    {
        $factory = sprintf('%s::factory', $classname);

        // Test that the factory does not allow a scalar option.
        try {
            $factory(0);
            $this->testCase->fail('An expected exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->testCase->assertStringContainsString(
                'factory expects an array or Traversable set of options',
                $e->getMessage()
            );
        }

        // Test required options.
        foreach ($requiredOptions as $option => $exceptionMessage) {
            $testOptions = $options;

            unset($testOptions[$option]);

            try {
                $factory($testOptions);
                $this->testCase->fail('An expected exception was not thrown');
            } catch (InvalidArgumentException $e) {
                $this->testCase->assertStringContainsString($exceptionMessage, $e->getMessage());
            }
        }

        // Create the route, will throw an exception if something goes wrong.
        $factory($options);

        // Try the same with an iterator.
        $factory(new ArrayIterator($options));
    }
}
