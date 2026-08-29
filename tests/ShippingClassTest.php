<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class ShippingClassTest extends TestCase
{
    public function testGridHasExactlyThreeCases(): void
    {
        $names = array_map(
            static fn (ShippingClass $case): string => $case->name,
            ShippingClass::cases(),
        );

        $this->assertSame(['Paket', 'Sperrgut', 'Spedition'], $names);
    }

    public function testAtLeastOnlyRaisesClass(): void
    {
        $this->assertSame(
            ShippingClass::Spedition,
            ShippingClass::Paket->atLeast(ShippingClass::Spedition),
        );
        $this->assertSame(
            ShippingClass::Spedition,
            ShippingClass::Spedition->atLeast(ShippingClass::Paket),
        );
        $this->assertSame(
            ShippingClass::Sperrgut,
            ShippingClass::Paket->atLeast(ShippingClass::Sperrgut),
        );
    }
}