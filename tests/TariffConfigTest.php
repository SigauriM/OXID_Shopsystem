<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\PostalZoneEntry;
use OxidShipping\Engine\Domain\ServedCountries;
use OxidShipping\Engine\Domain\TariffRates;
use OxidShipping\Engine\Domain\TransitEntry;
use OxidShipping\Engine\Domain\TransitTable;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Domain\ZoneConfig;
use OxidShipping\Engine\Domain\ZoneDefinition;
use OxidShipping\Engine\Domain\ZoneDirectory;
use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Engine\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class TariffConfigTest extends TestCase
{
    public function testConfigKeepsFiveThousandFactorAsFiveMillionMmG(): void
    {
        $config = TestConfig::tariff('test-2026', 5000);

        $this->assertSame(5_000_000, $config->volumetricDivisor->mmG());
    }

    public function testConfigKeepsSixThousandFactorAsSixMillionMmG(): void
    {
        $config = TestConfig::tariff('test-2026', 6000);

        $this->assertSame(6_000_000, $config->volumetricDivisor->mmG());
    }

    public function testTariffClassificationTablesMatchTestConfig(): void
    {
        $tables = TestConfig::tariff()->classification;

        $this->assertNull($tables->girth->floor(3000));
        $this->assertSame(ShippingClass::Sperrgut, $tables->girth->floor(3001));
        $this->assertNull($tables->maxLength->floor(2000));
        $this->assertSame(ShippingClass::Sperrgut, $tables->maxLength->floor(2001));
        $this->assertNull($tables->billableWeight->floor(20000));
        $this->assertSame(ShippingClass::Spedition, $tables->billableWeight->floor(20001));
    }

    public function testDirectoryHasKnownIdAndNotUnknown(): void
    {
        $directory = TestConfig::zones()->directory;

        $this->assertTrue($directory->has('de-01'));
        $this->assertFalse($directory->has('de-missing'));
        $ids = [];
        foreach ($directory->definitions() as $definition) {
            $ids[] = $definition->zoneId;
        }
        $this->assertContains('de-island', $ids);
        $this->assertContains('de-forbidden', $ids);
    }

    public function testIslandZoneIdMissingFromDirectoryIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Island surcharge zone id is not in the directory.');
        new TariffConfig(
            'test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            new ZoneConfig(
                ServedCountries::fromCodes(['DE']),
                ZoneDirectory::fromEntries(
                    [new ZoneDefinition('de-hh', false)],
                    [new PostalZoneEntry('DE', '20095', 'de-hh')],
                ),
            ),
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            TestConfig::rates(),
        );
    }

    public function testMissingTransitForALiveZoneIsProgrammerError(): void
    {
        $rates = TestConfig::rates();
        $incomplete = new TariffRates(
            $rates->base,
            $rates->surcharges,
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
            ]),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Transit days are required for every live zone and class.');
        new TariffConfig(
            'test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            TestConfig::zones(),
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            $incomplete,
        );
    }

    public function testValidVersionLabelsAssemble(): void
    {
        $this->assertSame('test-2026', TestConfig::tariff('test-2026')->version);
        $this->assertSame('2026-01-de', TestConfig::tariff('2026-01-de')->version);
    }

    public function testEmptyVersionIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tariff config version is invalid.');
        new TariffConfig(
            '',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            TestConfig::zones(),
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            TestConfig::rates(),
        );
    }

    public function testUntrimmedVersionIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tariff config version is invalid.');
        new TariffConfig(
            ' test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            TestConfig::zones(),
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            TestConfig::rates(),
        );
    }

    public function testVersionLongerThanSixtyFourIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tariff config version is invalid.');
        new TariffConfig(
            str_repeat('a', 65),
            VolumetricDivisor::fromDimFactorCmKg(5000),
            TestConfig::zones(),
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            TestConfig::rates(),
        );
    }

    public function testVersionWithSlashIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tariff config version is invalid.');
        new TariffConfig(
            'foo/bar',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            TestConfig::zones(),
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            TestConfig::rates(),
        );
    }
}
