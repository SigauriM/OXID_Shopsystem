<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\ServedCountries;
use PHPUnit\Framework\TestCase;

final class ServedCountriesTest extends TestCase
{
    public function testCodesAreUniqueAndSorted(): void
    {
        $fromDeAt = ServedCountries::fromCodes(['DE', 'AT']);
        $fromAtDe = ServedCountries::fromCodes(['AT', 'DE']);

        $this->assertSame(['AT', 'DE'], $fromDeAt->codes());
        $this->assertSame(['AT', 'DE'], $fromAtDe->codes());
        $this->assertTrue($fromDeAt->has('DE'));
        $this->assertFalse($fromDeAt->has('CH'));
    }

    public function testEmptyListIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Served country list must not be empty.');
        ServedCountries::fromCodes([]);
    }

    public function testDuplicateIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Served country list must not contain duplicates.');
        ServedCountries::fromCodes(['DE', 'DE']);
    }

    public function testFranceFailsOnConfigFormNotOnMissingEntries(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Served country has no config postal form.');
        ServedCountries::fromCodes(['FR']);
    }
}
