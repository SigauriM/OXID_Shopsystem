<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests\Support;

use OxidShipping\Engine\Domain\ClassFloor;
use OxidShipping\Engine\Domain\ClassificationConfig;
use OxidShipping\Engine\Domain\PostalZoneEntry;
use OxidShipping\Engine\Domain\ServedCountries;
use OxidShipping\Engine\Domain\ThresholdTable;
use OxidShipping\Engine\Domain\VolumetricDivisor;
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
        );
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
                ],
                [
                    new PostalZoneEntry('DE', '01067', 'de-01'),
                    new PostalZoneEntry('DE', '20095', 'de-hh'),
                    new PostalZoneEntry('DE', '18565', 'de-forbidden'),
                    new PostalZoneEntry('AT', '1010', 'at-w'),
                ],
            ),
        );
    }
}
