<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

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
}
