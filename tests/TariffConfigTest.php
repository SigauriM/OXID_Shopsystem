<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

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
}
