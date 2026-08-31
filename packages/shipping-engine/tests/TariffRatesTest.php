<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\BaseRateConfig;
use OxidShipping\Engine\Domain\IndoorSurchargeRate;
use OxidShipping\Engine\Domain\IslandSurchargeRate;
use OxidShipping\Engine\Domain\SurchargeConfig;
use OxidShipping\Engine\Domain\TariffRates;
use OxidShipping\Engine\Domain\TransitEntry;
use OxidShipping\Engine\Domain\TransitTable;
use OxidShipping\Engine\Domain\WeightRateStep;
use OxidShipping\Engine\Domain\WeightRateTable;
use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class TariffRatesTest extends TestCase
{
    public function testRatesHoldBaseSurchargesAndTransit(): void
    {
        $base = new BaseRateConfig(
            WeightRateTable::fromEntries([new WeightRateStep(1000, 400)]),
            WeightRateTable::fromEntries([new WeightRateStep(20000, 1500)]),
            WeightRateTable::fromEntries([new WeightRateStep(2000, 2800)]),
        );
        $surcharges = new SurchargeConfig(
            new IslandSurchargeRate(10, 800, []),
            new IndoorSurchargeRate(20, 1500),
        );
        $transit = TransitTable::fromEntries([
            new TransitEntry(ShippingClass::Paket, 'de-01', 2),
        ]);

        $rates = new TariffRates($base, $surcharges, $transit);

        $this->assertSame($base, $rates->base);
        $this->assertSame($surcharges, $rates->surcharges);
        $this->assertSame($transit, $rates->transit);
    }
}
