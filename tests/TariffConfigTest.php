<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Input\TariffConfig;
use PHPUnit\Framework\TestCase;

final class TariffConfigTest extends TestCase
{
    public function testConfigKeepsFiveThousandFactorAsFiveMillionMmG(): void
    {
        $config = new TariffConfig('test-2026', VolumetricDivisor::fromDimFactorCmKg(5000));

        $this->assertSame(5_000_000, $config->volumetricDivisor->mmG());
    }

    public function testConfigKeepsSixThousandFactorAsSixMillionMmG(): void
    {
        $config = new TariffConfig('test-2026', VolumetricDivisor::fromDimFactorCmKg(6000));

        $this->assertSame(6_000_000, $config->volumetricDivisor->mmG());
    }
}
