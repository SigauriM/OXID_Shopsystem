<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\PostalZoneEntry;
use OxidShipping\Engine\Domain\ServedCountries;
use OxidShipping\Engine\Domain\ZoneConfig;
use OxidShipping\Engine\Domain\ZoneDefinition;
use OxidShipping\Engine\Domain\ZoneDirectory;
use PHPUnit\Framework\TestCase;

final class ZoneConfigTest extends TestCase
{
    public function testMatchingServedAndDirectoryAssembles(): void
    {
        $config = new ZoneConfig(
            ServedCountries::fromCodes(['DE', 'AT']),
            ZoneDirectory::fromEntries(
                [
                    new ZoneDefinition('de-01', false),
                    new ZoneDefinition('at-w', false),
                ],
                [
                    new PostalZoneEntry('DE', '01067', 'de-01'),
                    new PostalZoneEntry('AT', '1010', 'at-w'),
                ],
            ),
        );

        $this->assertTrue($config->served->has('DE'));
        $this->assertSame(['AT', 'DE'], $config->directory->countries());
    }

    public function testDirectoryCountryOutsideServedIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Directory country is not served.');
        new ZoneConfig(
            ServedCountries::fromCodes(['DE']),
            ZoneDirectory::fromEntries(
                [
                    new ZoneDefinition('de-01', false),
                    new ZoneDefinition('at-w', false),
                ],
                [
                    new PostalZoneEntry('DE', '01067', 'de-01'),
                    new PostalZoneEntry('AT', '1010', 'at-w'),
                ],
            ),
        );
    }

    public function testServedCountryWithoutEntriesIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Served country has no directory entries.');
        new ZoneConfig(
            ServedCountries::fromCodes(['DE', 'AT']),
            ZoneDirectory::fromEntries(
                [new ZoneDefinition('de-01', false)],
                [new PostalZoneEntry('DE', '01067', 'de-01')],
            ),
        );
    }
}
