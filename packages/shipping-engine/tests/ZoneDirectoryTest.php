<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\PostalZoneEntry;
use OxidShipping\Engine\Domain\UnknownZone;
use OxidShipping\Engine\Domain\ZoneDefinition;
use OxidShipping\Engine\Domain\ZoneDirectory;
use PHPUnit\Framework\TestCase;

final class ZoneDirectoryTest extends TestCase
{
    public function testLookupFindsExactKeyAndUnknownOtherwise(): void
    {
        $directory = ZoneDirectory::fromEntries(
            [new ZoneDefinition('de-01', false)],
            [new PostalZoneEntry('DE', '01067', 'de-01')],
        );

        $known = $directory->lookup('01067', 'DE');
        $this->assertInstanceOf(KnownZone::class, $known);
        $this->assertSame('de-01', $known->zoneId);

        $this->assertInstanceOf(UnknownZone::class, $directory->lookup('99999', 'DE'));
        $this->assertInstanceOf(UnknownZone::class, $directory->lookup('01067', 'AT'));
    }

    public function testUnusedDefinitionIsAllowed(): void
    {
        $directory = ZoneDirectory::fromEntries(
            [
                new ZoneDefinition('de-01', false),
                new ZoneDefinition('de-hh', false),
            ],
            [new PostalZoneEntry('DE', '01067', 'de-01')],
        );

        $this->assertSame('de-hh', $directory->definition('de-hh')->zoneId);
        $this->assertFalse($directory->definition('de-hh')->forbidden);
    }

    public function testCountriesAreUniqueAndSorted(): void
    {
        $directory = ZoneDirectory::fromEntries(
            [
                new ZoneDefinition('de-01', false),
                new ZoneDefinition('at-w', false),
            ],
            [
                new PostalZoneEntry('DE', '01067', 'de-01'),
                new PostalZoneEntry('AT', '1010', 'at-w'),
                new PostalZoneEntry('DE', '20095', 'de-01'),
            ],
        );

        $this->assertSame(['AT', 'DE'], $directory->countries());
    }

    public function testPostalEntriesAreSortedByCountryThenPostalCode(): void
    {
        $directory = ZoneDirectory::fromEntries(
            [
                new ZoneDefinition('de-hh', false),
                new ZoneDefinition('de-01', false),
                new ZoneDefinition('at-w', false),
            ],
            [
                new PostalZoneEntry('DE', '20095', 'de-hh'),
                new PostalZoneEntry('DE', '01067', 'de-01'),
                new PostalZoneEntry('AT', '1010', 'at-w'),
            ],
        );

        $keys = array_map(
            static fn (PostalZoneEntry $entry): string => $entry->country . ':' . $entry->postalCode,
            $directory->postalEntries(),
        );
        $this->assertSame(['AT:1010', 'DE:01067', 'DE:20095'], $keys);
    }

    public function testDuplicatePostalEntryIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate postal zone entry.');
        ZoneDirectory::fromEntries(
            [new ZoneDefinition('de-01', false)],
            [
                new PostalZoneEntry('DE', '01067', 'de-01'),
                new PostalZoneEntry('DE', '01067', 'de-01'),
            ],
        );
    }

    public function testDuplicateDefinitionIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate zone definition.');
        ZoneDirectory::fromEntries(
            [
                new ZoneDefinition('de-01', false),
                new ZoneDefinition('de-01', true),
            ],
            [new PostalZoneEntry('DE', '01067', 'de-01')],
        );
    }

    public function testDanglingEntryIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Postal entry refers to an undefined zone.');
        ZoneDirectory::fromEntries(
            [new ZoneDefinition('de-01', false)],
            [new PostalZoneEntry('DE', '01067', 'de-hh')],
        );
    }

    public function testMissingDefinitionIsLogicException(): void
    {
        $directory = ZoneDirectory::fromEntries(
            [new ZoneDefinition('de-01', false)],
            [new PostalZoneEntry('DE', '01067', 'de-01')],
        );

        $this->expectException(\LogicException::class);
        $directory->definition('missing');
    }

    public function testUnnormalisedPostalKeyIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Postal code must already be normalised.');
        new PostalZoneEntry('DE', ' 01067', 'de-01');
    }

    public function testDefaultAsGermanPostalCodeDoesNotAssemble(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Postal code does not match the config form for the country.');
        new PostalZoneEntry('DE', 'default', 'de-01');
    }

    public function testGermanFourDigitConfigPostalDoesNotAssemble(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Postal code does not match the config form for the country.');
        new PostalZoneEntry('DE', '1067', 'de-01');
    }

    public function testUnnormalisedCountryKeyIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Postal country must already be normalised.');
        new PostalZoneEntry('de', '01067', 'de-01');
    }
}
