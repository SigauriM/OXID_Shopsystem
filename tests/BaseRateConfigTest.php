<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\BaseRateConfig;
use OxidShipping\Engine\Domain\WeightRateStep;
use OxidShipping\Engine\Domain\WeightRateTable;
use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class BaseRateConfigTest extends TestCase
{
    public function testForReturnsTheNamedTableForEachClass(): void
    {
        $paket = WeightRateTable::fromEntries([new WeightRateStep(1000, 400)]);
        $sperrgut = WeightRateTable::fromEntries([new WeightRateStep(20000, 1500)]);
        $spedition = WeightRateTable::fromEntries([new WeightRateStep(2000, 2800)]);
        $config = new BaseRateConfig($paket, $sperrgut, $spedition);

        $this->assertSame($paket, $config->for(ShippingClass::Paket));
        $this->assertSame($sperrgut, $config->for(ShippingClass::Sperrgut));
        $this->assertSame($spedition, $config->for(ShippingClass::Spedition));
    }
}
