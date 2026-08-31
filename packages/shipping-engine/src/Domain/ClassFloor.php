<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

use OxidShipping\Engine\ShippingClass;

final readonly class ClassFloor
{
    public function __construct(
        public int $above,
        public ShippingClass $class,
    ) {
        if ($above < 0) {
            throw new \InvalidArgumentException('Threshold above must be 0 or greater.');
        }
        if ($class === ShippingClass::Paket) {
            throw new \InvalidArgumentException('Threshold class must not be Paket.');
        }
    }
}
