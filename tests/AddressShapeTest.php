<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\AddressShape;
use PHPUnit\Framework\TestCase;

final class AddressShapeTest extends TestCase
{
    public function testPostalCodeIsTrimmed(): void
    {
        $this->assertSame('01067', AddressShape::postalCode('  01067  '));
    }

    public function testCountryIsTrimmedAndUppercased(): void
    {
        $this->assertSame('DE', AddressShape::country('  de  '));
    }

    public function testDeuBecomesDeuAndFailsAlpha2(): void
    {
        $normalised = AddressShape::country('deu');

        $this->assertSame('DEU', $normalised);
        $this->assertSame(0, preg_match(AddressShape::COUNTRY_PATTERN, $normalised));
    }

    public function testDeWithFourDigitPostalCodeIsLegalRequestForm(): void
    {
        $this->assertSame(1, preg_match(AddressShape::POSTAL_CODE_PATTERN, '1010'));
        $this->assertSame(1, preg_match(AddressShape::COUNTRY_PATTERN, 'DE'));
    }
}
