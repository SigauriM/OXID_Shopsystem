<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\ConfigPostalFormat;
use PHPUnit\Framework\TestCase;

final class ConfigPostalFormatTest extends TestCase
{
    public function testSupportsGermanyAndAustriaOnly(): void
    {
        $this->assertTrue(ConfigPostalFormat::supports('DE'));
        $this->assertTrue(ConfigPostalFormat::supports('AT'));
        $this->assertFalse(ConfigPostalFormat::supports('FR'));
        $this->assertFalse(ConfigPostalFormat::supports('de'));
    }

    public function testGermanFiveDigitsPass(): void
    {
        $this->expectNotToPerformAssertions();
        ConfigPostalFormat::assertPostalCode('DE', '01067');
    }

    public function testAustrianFourDigitsPass(): void
    {
        $this->expectNotToPerformAssertions();
        ConfigPostalFormat::assertPostalCode('AT', '1010');
    }

    public function testGermanFourDigitsFail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Postal code does not match the config form for the country.');
        ConfigPostalFormat::assertPostalCode('DE', '1067');
    }

    public function testUnsupportedCountryFailsOnFormNotOnDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Country has no config postal form.');
        ConfigPostalFormat::assertPostalCode('FR', '75001');
    }
}
