<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\ClassFloor;
use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class ClassFloorTest extends TestCase
{
    public function testZeroAboveIsAllowed(): void
    {
        $floor = new ClassFloor(0, ShippingClass::Sperrgut);

        $this->assertSame(0, $floor->above);
        $this->assertSame(ShippingClass::Sperrgut, $floor->class);
    }

    public function testNegativeAboveIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Threshold above must be 0 or greater.');
        new ClassFloor(-1, ShippingClass::Sperrgut);
    }

    public function testPaketClassIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Threshold class must not be Paket.');
        new ClassFloor(3000, ShippingClass::Paket);
    }
}
