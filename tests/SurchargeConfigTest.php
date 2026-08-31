<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\IndoorSurchargeRate;
use OxidShipping\Engine\Domain\IslandSurchargeRate;
use OxidShipping\Engine\Domain\SurchargeConfig;
use PHPUnit\Framework\TestCase;

final class SurchargeConfigTest extends TestCase
{
    public function testTenAndTwentyAssemble(): void
    {
        $config = new SurchargeConfig(
            new IslandSurchargeRate(10, 800, ['de-island']),
            new IndoorSurchargeRate(20, 1500),
        );

        $this->assertSame(10, $config->island->priority);
        $this->assertSame(20, $config->indoor->priority);
        $this->assertSame(['de-island'], $config->island->zoneIds);
    }

    public function testZeroAndNegativePrioritiesAssembleWhenDistinct(): void
    {
        $config = new SurchargeConfig(
            new IslandSurchargeRate(0, 800, []),
            new IndoorSurchargeRate(-1, 1500),
        );

        $this->assertSame(0, $config->island->priority);
        $this->assertSame(-1, $config->indoor->priority);
    }

    public function testDuplicatePriorityIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate surcharge priority.');
        new SurchargeConfig(
            new IslandSurchargeRate(10, 800, []),
            new IndoorSurchargeRate(10, 1500),
        );
    }

    public function testEmptyIslandZoneListAssembles(): void
    {
        $rate = new IslandSurchargeRate(10, 800, []);

        $this->assertSame([], $rate->zoneIds);
    }

    public function testIslandZoneIdsAreSortedLexicographically(): void
    {
        $rate = new IslandSurchargeRate(10, 800, ['de-b', 'de-a']);

        $this->assertSame(['de-a', 'de-b'], $rate->zoneIds);
    }

    public function testDuplicateIslandZoneIdIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Island surcharge zone ids must be unique.');
        new IslandSurchargeRate(10, 800, ['de-island', 'de-island']);
    }

    public function testInvalidIslandZoneIdIsTheSameFamilyAsZoneIdAssert(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone id does not match the required pattern.');
        new IslandSurchargeRate(10, 800, ['DE-island']);
    }

    public function testIslandCentsZeroIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Island surcharge cents must be 1 or greater.');
        new IslandSurchargeRate(10, 0, []);
    }

    public function testIndoorCentsZeroIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Indoor surcharge cents must be 1 or greater.');
        new IndoorSurchargeRate(20, 0);
    }
}
