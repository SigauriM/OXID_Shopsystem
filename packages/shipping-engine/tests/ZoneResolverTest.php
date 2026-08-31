<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\PostalZoneEntry;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Domain\RejectReason;
use OxidShipping\Engine\Domain\ServedCountries;
use OxidShipping\Engine\Domain\ZoneConfig;
use OxidShipping\Engine\Domain\ZoneDefinition;
use OxidShipping\Engine\Domain\ZoneDirectory;
use OxidShipping\Engine\Zone\ZoneResolver;
use PHPUnit\Framework\TestCase;

final class ZoneResolverTest extends TestCase
{
    private ZoneResolver $resolver;

    private ZoneConfig $zones;

    protected function setUp(): void
    {
        $this->resolver = new ZoneResolver();
        $this->zones = new ZoneConfig(
            ServedCountries::fromCodes(['DE', 'AT']),
            ZoneDirectory::fromEntries(
                [
                    new ZoneDefinition('de-01', false),
                    new ZoneDefinition('de-forbidden', true),
                    new ZoneDefinition('at-w', false),
                ],
                [
                    new PostalZoneEntry('DE', '01067', 'de-01'),
                    new PostalZoneEntry('DE', '18565', 'de-forbidden'),
                    new PostalZoneEntry('AT', '1010', 'at-w'),
                ],
            ),
        );
    }

    public function testKnownAllowedZone(): void
    {
        $result = $this->resolver->resolve('01067', 'DE', $this->zones);

        $this->assertInstanceOf(KnownZone::class, $result);
        $this->assertSame('de-01', $result->zoneId);
    }

    public function testUnknownPostalCode(): void
    {
        $result = $this->resolver->resolve('99999', 'DE', $this->zones);

        $this->assertInstanceOf(Rejected::class, $result);
        $this->assertSame(RejectReason::UnknownZone, $result->reason);
    }

    public function testForbiddenZone(): void
    {
        $result = $this->resolver->resolve('18565', 'DE', $this->zones);

        $this->assertInstanceOf(Rejected::class, $result);
        $this->assertSame(RejectReason::ZoneForbidden, $result->reason);
    }

    public function testUnservedCountryWithUnknownPostalIsCountryNotServed(): void
    {
        $result = $this->resolver->resolve('8001', 'CH', $this->zones);

        $this->assertInstanceOf(Rejected::class, $result);
        $this->assertSame(RejectReason::CountryNotServed, $result->reason);
    }
}
