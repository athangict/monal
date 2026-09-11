<?php

declare(strict_types=1);

namespace LaminasTest\Hydrator\Strategy;

use Laminas\Hydrator\Strategy\BackedEnumStrategy;
use Laminas\Hydrator\Strategy\Exception\InvalidArgumentException;
use LaminasTest\Hydrator\Strategy\TestAsset\TestBackedEnum;
use LaminasTest\Hydrator\Strategy\TestAsset\TestUnitEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackedEnumStrategy::class)]
final class BackedEnumStrategyTest extends TestCase
{
    public function testExtractInvalidValueThrowsException(): void
    {
        $strategy = new BackedEnumStrategy(TestBackedEnum::class);
        self::expectException(InvalidArgumentException::class);
        $strategy->extract(TestUnitEnum::One);
    }

    public function testExtractExtractsValue(): void
    {
        $strategy = new BackedEnumStrategy(TestBackedEnum::class);
        $actual   = $strategy->extract(TestBackedEnum::One);
        self::assertSame('one', $actual);
    }

    public function testHydrateEnumReturnsEnum(): void
    {
        $expected = TestBackedEnum::Two;
        $strategy = new BackedEnumStrategy(TestBackedEnum::class);
        $actual   = $strategy->hydrate($expected, null);
        self::assertSame(TestBackedEnum::Two, $actual);
    }

    public function testHydrateNonScalarThrowsException(): void
    {
        $strategy = new BackedEnumStrategy(TestBackedEnum::class);
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Value must be string or int; array provided");
        $strategy->hydrate([], null);
    }

    public function testHydrateNonCaseThrowsException(): void
    {
        $strategy = new BackedEnumStrategy(TestBackedEnum::class);
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Value 'three' is not a valid scalar value for " . TestBackedEnum::class);
        $strategy->hydrate('three', null);
    }

    public function testHydrateValueReturnsEnum(): void
    {
        $strategy = new BackedEnumStrategy(TestBackedEnum::class);
        $actual   = $strategy->hydrate('two', null);
        self::assertSame(TestBackedEnum::Two, $actual);
    }
}
