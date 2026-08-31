<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests\Support;

use OxidShipping\Engine\Domain\BaseRateConfig;
use OxidShipping\Engine\Domain\ClassFloor;
use OxidShipping\Engine\Domain\ClassificationConfig;
use OxidShipping\Engine\Domain\IndoorSurchargeRate;
use OxidShipping\Engine\Domain\IslandSurchargeRate;
use OxidShipping\Engine\Domain\OrderWeightThreshold;
use OxidShipping\Engine\Domain\PostalZoneEntry;
use OxidShipping\Engine\Domain\ServedCountries;
use OxidShipping\Engine\Domain\SurchargeConfig;
use OxidShipping\Engine\Domain\TariffRates;
use OxidShipping\Engine\Domain\ThresholdTable;
use OxidShipping\Engine\Domain\TransitEntry;
use OxidShipping\Engine\Domain\TransitTable;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Domain\WeightRateStep;
use OxidShipping\Engine\Domain\WeightRateTable;
use OxidShipping\Engine\Domain\ZoneConfig;
use OxidShipping\Engine\Domain\ZoneDefinition;
use OxidShipping\Engine\Domain\ZoneDirectory;
use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\ShippingClass;

final class TestConfig
{
    public static function tariff(
        string $version = 'test-2026',
        int $dimFactorCmKg = 5000,
    ): TariffConfig {
        return new TariffConfig(
            $version,
            VolumetricDivisor::fromDimFactorCmKg($dimFactorCmKg),
            self::zones(),
            self::classification(),
            self::orderWeightThreshold(),
            self::rates(),
        );
    }

    /**
     * @param list<string> $islandZoneIds
     */
    public static function rates(array $islandZoneIds = ['de-island']): TariffRates
    {
        return new TariffRates(
            new BaseRateConfig(
                WeightRateTable::fromEntries([
                    new WeightRateStep(1000, 400),
                    new WeightRateStep(20000, 600),
                    new WeightRateStep(3_375_000_000, 900),
                ]),
                WeightRateTable::fromEntries([
                    new WeightRateStep(20000, 1500),
                    new WeightRateStep(3_375_000_000, 2000),
                ]),
                WeightRateTable::fromEntries([
                    new WeightRateStep(2000, 2800),
                    new WeightRateStep(20000, 3000),
                    new WeightRateStep(40000, 4000),
                    new WeightRateStep(3_375_000_000, 6000),
                ]),
            ),
            new SurchargeConfig(
                new IslandSurchargeRate(10, 800, $islandZoneIds),
                new IndoorSurchargeRate(20, 1500),
            ),
            TransitTable::fromEntries([
                new TransitEntry(ShippingClass::Paket, 'de-01', 2),
                new TransitEntry(ShippingClass::Sperrgut, 'de-01', 3),
                new TransitEntry(ShippingClass::Spedition, 'de-01', 5),
                new TransitEntry(ShippingClass::Paket, 'de-hh', 2),
                new TransitEntry(ShippingClass::Sperrgut, 'de-hh', 3),
                new TransitEntry(ShippingClass::Spedition, 'de-hh', 5),
                new TransitEntry(ShippingClass::Paket, 'at-w', 2),
                new TransitEntry(ShippingClass::Sperrgut, 'at-w', 3),
                new TransitEntry(ShippingClass::Spedition, 'at-w', 5),
                new TransitEntry(ShippingClass::Paket, 'de-island', 4),
                new TransitEntry(ShippingClass::Sperrgut, 'de-island', 6),
                new TransitEntry(ShippingClass::Spedition, 'de-island', 7),
            ]),
        );
    }

    public static function orderWeightThreshold(): OrderWeightThreshold
    {
        return new OrderWeightThreshold(40000);
    }

    public static function classification(): ClassificationConfig
    {
        return new ClassificationConfig(
            ThresholdTable::fromEntries([
                new ClassFloor(3000, ShippingClass::Sperrgut),
                new ClassFloor(3600, ShippingClass::Spedition),
            ]),
            ThresholdTable::fromEntries([
                new ClassFloor(2000, ShippingClass::Sperrgut),
                new ClassFloor(4000, ShippingClass::Spedition),
            ]),
            ThresholdTable::fromEntries([
                new ClassFloor(20000, ShippingClass::Spedition),
            ]),
        );
    }

    public static function zones(): ZoneConfig
    {
        return new ZoneConfig(
            ServedCountries::fromCodes(['DE', 'AT']),
            ZoneDirectory::fromEntries(
                [
                    new ZoneDefinition('de-01', false),
                    new ZoneDefinition('de-hh', false),
                    new ZoneDefinition('de-forbidden', true),
                    new ZoneDefinition('at-w', false),
                    new ZoneDefinition('de-island', false),
                ],
                [
                    new PostalZoneEntry('DE', '01067', 'de-01'),
                    new PostalZoneEntry('DE', '20095', 'de-hh'),
                    new PostalZoneEntry('DE', '18565', 'de-forbidden'),
                    new PostalZoneEntry('AT', '1010', 'at-w'),
                    new PostalZoneEntry('DE', '27498', 'de-island'),
                ],
            ),
        );
    }
}
