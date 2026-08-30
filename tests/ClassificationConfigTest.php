<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\ClassFloor;
use OxidShipping\Engine\Domain\ClassificationConfig;
use OxidShipping\Engine\Domain\ThresholdTable;
use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class ClassificationConfigTest extends TestCase
{
    public function testThreeNamedTablesAreRequiredAndNotAList(): void
    {
        $girth = ThresholdTable::fromEntries([
            new ClassFloor(3000, ShippingClass::Sperrgut),
        ]);
        $maxLength = ThresholdTable::fromEntries([
            new ClassFloor(2000, ShippingClass::Sperrgut),
        ]);
        $billableWeight = ThresholdTable::fromEntries([
            new ClassFloor(20000, ShippingClass::Spedition),
        ]);

        $config = new ClassificationConfig($girth, $maxLength, $billableWeight);

        $this->assertSame($girth, $config->girth);
        $this->assertSame($maxLength, $config->maxLength);
        $this->assertSame($billableWeight, $config->billableWeight);
    }
}
