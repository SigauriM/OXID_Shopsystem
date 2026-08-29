<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\PostalZoneEntry;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Domain\RejectReason;
use OxidShipping\Engine\Domain\ServedCountries;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Domain\ZoneConfig;
use OxidShipping\Engine\Domain\ZoneDefinition;
use OxidShipping\Engine\Domain\ZoneDirectory;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class QuoteEngineZoneTest extends TestCase
{
    private QuoteEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new QuoteEngine();
    }

    public function testDresdenResolvesToKnownZoneWithoutRejections(): void
    {
        $result = $this->quote('01067', 'DE');

        $this->assertInstanceOf(KnownZone::class, $result->destination);
        $this->assertSame('de-01', $result->destination->zoneId);
        $this->assertSame([], $result->rejections);
    }

    public function testUnknownGermanPostalIsUnknownZoneNotKnown(): void
    {
        $result = $this->quote('99999', 'DE');

        $this->assertInstanceOf(Rejected::class, $result->destination);
        $this->assertSame(RejectReason::UnknownZone, $result->destination->reason);
    }

    public function testSwitzerlandWithUnknownPostalIsCountryNotServedNotValidationFailed(): void
    {
        $result = $this->quote('8001', 'CH');

        $this->assertInstanceOf(Rejected::class, $result->destination);
        $this->assertSame(RejectReason::CountryNotServed, $result->destination->reason);
    }

    public function testForbiddenGermanPostalIsZoneForbidden(): void
    {
        $result = $this->quote('18565', 'DE');

        $this->assertInstanceOf(Rejected::class, $result->destination);
        $this->assertSame(RejectReason::ZoneForbidden, $result->destination->reason);
    }

    public function testGermanFourDigitPostalMatchingViennaIsUnknownZone(): void
    {
        $result = $this->quote('1010', 'DE');

        $this->assertInstanceOf(Rejected::class, $result->destination);
        $this->assertSame(RejectReason::UnknownZone, $result->destination->reason);
    }

    public function testShortGermanPostalOnRequestIsUnknownZone(): void
    {
        $result = $this->quote('1067', 'DE');

        $this->assertInstanceOf(Rejected::class, $result->destination);
        $this->assertSame(RejectReason::UnknownZone, $result->destination->reason);
    }

    public function testAddressRejectExpandsToEveryPiece(): void
    {
        $result = $this->engine->quote(new QuoteRequest(
            lines: [
                new OrderLine('line-a', 100, 100, 100, 1, 2),
                new OrderLine('line-b', 100, 100, 100, 1, 2),
            ],
            postalCode: '99999',
            country: 'DE',
            indoor: false,
            config: TestConfig::tariff(),
        ));

        $this->assertInstanceOf(Quote::class, $result);
        $this->assertCount(4, $result->rejections);
        $this->assertSame(
            [[0, 0], [0, 1], [1, 0], [1, 1]],
            array_map(
                static fn ($rejection): array => [$rejection->lineIndex, $rejection->pieceIndex],
                $result->rejections,
            ),
        );
        foreach ($result->rejections as $rejection) {
            $this->assertSame(RejectReason::UnknownZone, $rejection->rejected->reason);
        }
    }

    public function testUnrelatedDirectoryKeyDoesNotStandInForDresden(): void
    {
        $config = new TariffConfig(
            'test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            new ZoneConfig(
                ServedCountries::fromCodes(['DE']),
                ZoneDirectory::fromEntries(
                    [new ZoneDefinition('de-hh', false)],
                    [new PostalZoneEntry('DE', '20095', 'de-hh')],
                ),
            ),
        );

        $result = $this->engine->quote(new QuoteRequest(
            lines: [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            postalCode: '01067',
            country: 'DE',
            indoor: false,
            config: $config,
        ));

        $this->assertInstanceOf(Quote::class, $result);
        $this->assertInstanceOf(Rejected::class, $result->destination);
        $this->assertSame(RejectReason::UnknownZone, $result->destination->reason);
    }

    private function quote(string $postalCode, string $country): Quote
    {
        $result = $this->engine->quote(new QuoteRequest(
            lines: [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            postalCode: $postalCode,
            country: $country,
            indoor: false,
            config: TestConfig::tariff(),
        ));

        $this->assertInstanceOf(Quote::class, $result);

        return $result;
    }
}
